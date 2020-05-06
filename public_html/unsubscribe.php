<?php

include '../util.php';

$config = parse_ini_file(dirname(dirname(__FILE__)) . "/settings.cfg", true);
$STATIC_API_KEY = $config['google_maps']['static_api_key'];
$SITE_DOMAIN = getenv('SITE_DOMAIN');

function cancelSubscription($guid) {
  $connection = connect_db();
  // Get active subscriptions
  $update = $connection->query("UPDATE `subscriptions` SET `unsubscribed` = 1 WHERE `guid` = '$guid'");

  if (!$update) {
    die("Failed to unsubscribe: " . $connection->error);
  }
    
  // Show confirmation page to user
  include 'unsubscribed.php';
  die();
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['guid']) {
        cancelSubscription($_GET['guid']);
    }    
}
