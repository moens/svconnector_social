<?php

require_once('jsonpath.php');

$json = file_get_contents('http://search.twitter.com/search.json?q=%22gallup+new+mexico%22+OR+%22gallup+nm%22+OR+gallupnm+OR+livegallup&result_type=recent');

// print_r($json);

$jarray = json_decode($json, true);

// print_r($jarray);

$items = jsonPath($jarray, "$.results[*]");

print_r($items);

//$json = json_encode($items);

//print_r($json);
?>
