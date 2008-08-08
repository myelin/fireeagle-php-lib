<?php

require(dirname(__FILE__)."/../lib/fireeagle.php");

echo "Testing Fire Eagle PHP library\n\n";

try {
  $fe = FireEagle::from_fireeaglerc(getenv("HOME")."/.fireeaglerc");
} catch (FireEagleException $e) {
  switch ($e->getCode()) {
  case FireEagleException::CONFIG_READ_ERROR:
    echo "Failed to read ~/.fireeaglerc.  Please paste your consumer key and secret into ~/.fireeaglerc, in the following format:

consumer_key = YNqmPYEMEOzA
consumer_secret = 7W6t7UwHXhe7UtAVo2KO5VGbK6I1UjOS

(You can use that key and secret if you're just running the tests.)
";
    break;
  default: throw $e;
  }
}

echo "Getting a request token\n";
$req = $fe->request_token();

$auth_url = $fe->authorize($req);
echo "Please authorize the app at this URL:\n  $auth_url\nThen press ENTER.\n";
fgets(fopen("php://stdin", "r"), 10);

echo "Getting an access token\n";
$acc = $fe->access_token();
var_dump($acc);

echo "Reading your location\n";
var_dump($fe->user());

echo "Looking up a location\n";
var_dump($fe->lookup(array("q" => "500 3rd st, san francisco, ca")));
