<?php

include '../util.php';

$config = parse_ini_file(dirname(dirname(__FILE__)) . "/settings.cfg", true);
$STATIC_API_KEY = $config['google_maps']['static_api_key'];
$SITE_DOMAIN = 'halicrime.stewartrand.com';

connect_db();


function cancelSubscription($guid) {
  // Get active subscriptions
  $update = mysql_query("UPDATE `subscriptions` SET `unsubscribed` = 1 WHERE `guid` = '$guid'");

  if (!$update) {
    die("Failed to unsubscribe: " . mysql_error());
  }  
  
    
  // Show confirmation page to user
  include 'unsubscribed.html';
  die();
  
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['guid']) {
        cancelSubscription($_GET['guid']);
    }    
}


?>
