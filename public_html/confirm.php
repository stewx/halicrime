<?php

// Code here to set subscription as active in DB
include '../util.php';

function confirmSubscription($guid) {
  $connection = connect_db();
  
  $result = $connection->query("SELECT * FROM `subscriptions` WHERE `guid` = '$guid'");

  if (mysqli_num_rows($result) == 0) {
    die("Couldn't find subscription.");
  }

  $query = "
    UPDATE `subscriptions` 
    SET `activated` = 1
    WHERE `guid` = '$guid'
  ";
  $result = $connection->query($query);
    
  if (!$result) {
    die("Invalid query: " . $connection->error);
  }

  mysqli_close($connection);
  
  // Show confirmation page to user
  include 'confirmed.php';
  die();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['guid']) {
        confirmSubscription($_GET['guid']);
    }
}