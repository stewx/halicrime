<?php


// Code here to set subscription as active in DB
include 'db.php';
connect_db();

function confirm_subscription($guid) {
  
  $result = mysql_query("SELECT * FROM `subscriptions` WHERE `guid` = '$guid'");
  if (mysql_num_rows($result) == 0) {
    die("Couldn't find subscription.");
  }
  
  $query = "
    UPDATE `subscriptions` 
    SET `activated` = 1
    WHERE `guid` = '$guid'
  ";
  $result = mysql_query($query);
    
  if (!$result) {
    die("Invalid query: " . mysql_error());
  }
  
  // Show confirmation page to user
  include 'confirmed.html';
  die();
  
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['guid']) {
        confirm_subscription($_GET['guid']);
    }    
    
}


?>