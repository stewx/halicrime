<?php

include 'db.php';

$SITE_DOMAIN = 'halicrime.stewartrand.com';

connect_db();

// Get active subscriptions
$subscriptions = mysql_query("SELECT * FROM `subscriptions` WHERE `activated` = 1");

if (!$subscriptions) {
  die("Invalid query: " . mysql_error());
}

// For each subscription, check if there have been any matching events since the last run
while ($subscription = mysql_fetch_assoc($subscriptions)) {
  
  $query = "
    SELECT *,
    ( 6371 * acos( cos( radians({$subscription['latitude']}) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians({$subscription['latitude']}) ) * sin( radians( `latitude` ) ) ) ) AS `distance`
    FROM `events`
    WHERE `date` > DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    HAVING distance < ({$subscription['radius']} / 1000)
  ";
  
  $matching_events = mysql_query($query);
  
  $events = array();
  while($event = mysql_fetch_assoc($matching_events)){
    $events[] = $event;
  }
  
  send_notification($subscription, $events);
  
}


// Send email
function send_notification($subscription, $events) {
  global $SITE_DOMAIN;
  
  // Track number of occurrences of each type of event
  $frequencies = array();
  foreach ($events as $event) {
    if (isset($frequencies[$event['event_type']])) {
      $frequencies[$event['event_type']]++;
    }
    else {
      $frequencies[$event['event_type']] = 1;
    }
  }  
  
  $events_table = "<table>";
  $events_table .= "
    <thead>
      <tr>
        <th>Type</th>
        <th>Count</th>
      </tr>
    </thead>
  ";
  foreach ($frequencies as $event_type => $count) {
    $events_table .= "
    <tr>
      <td>$event_type</td>
      <td>$count</td>
    </tr>
    ";
  }
  $events_table .= "</table>";
  
  $message = <<<EOT
    <h2 style="font-family: Helvetica, Arial, Sans-Serif;">Halicrime</h2>
    <p style="font-family: Helvetica, Arial, Sans-Serif;">Here are the events that happened.</p>  
    $events_table
    <p style="font-family: Helvetica, Arial, Sans-Serif;"><a href="http://$SITE_DOMAIN/unsubscribe.php?guid={$subscription['guid']}">Unsubscribe</a></p>  
EOT;
  global $SITE_DOMAIN;
  $to = $subscription['email'];
  $subject = "Halicrime Alerts";
  $headers = "From: Halicrime <subscribe@halicrime.ca>\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
  
  

  mail($to, $subject, $message, $headers);
    
  
}



?>
