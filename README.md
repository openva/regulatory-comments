# Regulatory Comments Scraper

Extracts public comments on proposed Virginia regulations, as submitted to the [Virginia Regulatory Townhall](http://townhall.virginia.gov/), and saves each comment as a JSON file.

## Instructions

* Create a `json` directory where the program can save files.
* Run `parser.php`, either by invoking it from a public URL or at the command line (`php parser.php`).

## To Do

* Support resumable downloads—rather than re-scraping everything, allow it to pick up where it left off. (This can be done presently by changing `$min` to a value other than `1`—specifically, the ID with which the parser should begin.) 
