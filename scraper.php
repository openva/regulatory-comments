<?php

/*
 * The directory to which we're saving the JSON files, one per comment.
 */
define('OUTPUT_DIR', 'json');
date_default_timezone_set('America/New_York');

/*
 * Given a string that consists of HTML, extract each field and return an object of relevant
 * fields.
 */
function extract_comment($html)
{
  
	if (empty($html))
	{
		return FALSE;
	}
	
	/*
	 * If a 404 is returned (it's actually a 200 -- the error is found only within the page
	 * content), then return false.
	 */
	if (strpos($html, 'Error: Invalid comment id specified!') !== FALSE)
	{
		return FALSE;
	}
	
	$comment = new stdClass();
	
	/*
	 * The agency and board names are surrounded by the same HTML, so capture them in the order in
	 * which they appear.
	 */
	preg_match_all('/<div style="float: left">(\s+)(.*)(\s+)<\/div>/', $html, $matches);
	if (!empty($matches[2][0]))
	{
		$comment->agency->name = trim($matches[2][0]);
	}
	if (!empty($matches[2][1]))
	{
		$comment->board->name = trim($matches[2][1]);
	}
	
	preg_match('/ViewAgency\.cfm\?AgencyNumber=([0-9]+)"/', $html, $matches);
	if (!empty($matches[1]))
	{
		$comment->agency->number = $matches[1];
	}
	
	preg_match('/ViewBoard\.cfm\?BoardID=([0-9]+)"/', $html, $matches);
	if (!empty($matches[1]))
	{
		$comment->board->id = $matches[1];
	}
	
	preg_match('/ViewChapter\.cfm\?ChapterID=([0-9]+)"/', $html, $matches);
	if (!empty($matches[1]))
	{
		$comment->chapter->id = $matches[1];
	}
	
	preg_match('/<div style="float: left; font-weight: bold;">(\s+)(.*)(\s+)<span /', $html, $matches);
	if (!empty($matches[1]))
	{
		$comment->chapter->name = trim($matches[2]);
	}
	
	preg_match('/222288">(\s*)\[(.*)\]<\/span>/', $html, $matches);
	if (!empty($matches[2]))
	{
		$comment->citation = html_entity_decode(str_replace('&nbsp;', ' ', $matches[2]), 0);
		$comment->citation = str_replace(' &#8209; ', '‑', $comment->citation);
	}
	
	preg_match('/Commenter:<\/strong>([^<]+)/', $html, $matches);
	if (!empty($matches[1]))
	{
		$comment->commenter = preg_replace('/\v/', '', $matches[1]);
		$comment->commenter = str_replace('&nbsp;', ' ', (trim(preg_replace('/(\h{2,})/', ' ', $comment->commenter))));
	}

	preg_match('/padding: 4px">(.*)<\/div>/', $html, $matches);
	if (!empty($matches[1]))
	{
		$comment->timestamp = date('c', strtotime(str_replace('&nbsp;', ' ', $matches[1])));
	}
	
	preg_match('/href="viewaction\.cfm\?actionid=([0-9]+)" class="linkblack">(.*)<\/a>/', $html, $matches);
	if (isset($matches[1]))
	{
		$comment->action->name = $matches[2];
		$comment->action->id = $matches[1];
	}
	
	preg_match('/href="viewstage\.cfm\?stageid=([0-9]+)"(( class="linkblack")?)>(.*)<\/a>/', $html, $matches);
	if (isset($matches[1]))
	{
		$comment->stage->name = $matches[4];
		$comment->stage->id = $matches[1];
	}
	
	preg_match('/Comment&nbsp;Period<\/strong><\/td>(\s+)<td>(\s+)Ends (.*)(\s+)<\/td>/', $html, $matches);
	if (isset($matches[3]))
	{
		$comment->period_ends = date('c', strtotime($matches[3]));
	}
	
	preg_match('/<br><br>(\s+)<strong>(.*)<\/strong>/', $html, $matches);
	if (!empty($matches[2]))
	{
		$comment->title = $matches[2];
	}
	
	/*
	 * Perform a greedy, case-insensitive match, to get all paragraphs.
	 */
	preg_match('/<div style="clear: right">&nbsp;<\/div>(.+?)<\/div>/s', $html, $matches);

	if (!empty($matches[1]))
	{
		$comment->comment = trim(str_replace('&nbsp;', ' ', $matches[1]));
		$comment->comment = str_replace('<!--- MSIE Browser --->', '', $comment->comment);
		
		/*
		 * Pipe this HTML through HTML Tidy to clean it up.
		 */
		$tidy = new tidy;
		$config = array(
			'show-body-only' => TRUE,
			'drop-font-tags' => TRUE
			);
		$tidy->parseString($comment->comment, $config);
		$tidy->cleanRepair();
		if (empty($tidy->errorBuffer))
		{
			$comment->comment = $tidy;
		}
 
	}
	
	return $comment;
	
}

/*
 * Define the base URL that we'll be iterating on.
 */
$base_url = 'http://townhall.virginia.gov/L/viewcomments.cfm?commentid=';

/*
 * Define the minimum and maximum comment ID. A huge maximum number is given to keep this from
 * running out of control.
 */
$min = 1;
$max = 100000;

/*
 * Maintain a count of the number of failed attempts to retrieve a comment. We only really care
 * about sequential failures, to determine when we've finished indexing all comments, so we
 * reset this to 0 with each successful retrieval.
 */
$failures = 0;

/*
 * Iterate through every comment.
 */
for ($i = $min; $i <= $max; $i++)
{
	
	/*
	 * Assemble the URL.
	 */
	$url = $base_url . $i;
	
	/*
	 * Get the HTML found at that URL.
	 */
	$html = file_get_contents($url);
	
	/*
	 * If we successfully retrieve that HTML.
	 */
	if ($html !== FALSE)
	{
		
		/*
		 * Pull the comment data out of the HTML, storing it as an object.
		 */
		$comment = extract_comment($html);
		
		if ($comment === FALSE)
		{
			$failures++;
			continue;
		}
		
		/*
		 * Append the comment's ID and URL.
		 */
		$comment->id = $i;
		$comment->url = $url;
		
		echo $comment->url."\n";
		
		/*
		 * Turn the object into JSON.
		 */
		$comment = json_encode($comment);
		
		/*
		 * Write the comment JSON to a file.
		 */
		file_put_contents(OUTPUT_DIR . '/' . str_pad($i, 5, '0', STR_PAD_LEFT) . '.json', $comment);
		
		/*
		 * Set our count of consecutive failures to 0, since we just had a successful download.
		 */
		if ($failures > 0)
		{
			$failures = 0;
		}
		
	}
	
	else
	{
		$failures++;
	}
	
	/*
	 * If we've had 40 consecutive failed attempts to retrieve comments, stop running.
	 */
	if ($failures >= 40)
	{
		die('<p>After 20 consecutive failed attempts to retrieve data (from 20 consecutive '
			. 'URLs), aborting further attempts. The last URL tried was '. $url .'</p>');
	}
	
}
