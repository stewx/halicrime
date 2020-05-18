<?php

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer packages
require 'vendor/autoload.php';

include 'util.php';

$logging = new Logging;
$config = parse_ini_file(dirname(__FILE__) . "/settings.cfg", true);
$STATIC_API_KEY = getenv('GOOGLE_STATIC_KEY');
$SITE_DOMAIN = getenv('SITE_DOMAIN');
$FROM_EMAIL = getenv('FROM_EMAIL');
$INTERVAL = '7 DAY';

$connection = connect_db();

// Get active subscriptions
$logging->debug("Looking up subscriptions");
$subscriptions = $connection->query("SELECT * FROM `subscriptions` WHERE `activated` = 1 AND `unsubscribed` = 0");

if (!$subscriptions) {
  $message = "Invalid query: " . $connection->error;
  $logging->error($message);
  die($message);
}

$rowcount = mysqli_num_rows($subscriptions);

$logging->debug("Subscription count: $rowcount");

// For each subscription, check if there have been any matching events since the last run
while ($subscription = mysqli_fetch_assoc($subscriptions)) {
  $query = "SELECT *
    FROM `events`
    WHERE `date_added` > DATE_SUB(CURDATE(), INTERVAL $INTERVAL)
    AND ( 6371 * acos( cos( radians({$subscription['latitude']}) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians({$subscription['latitude']}) ) * sin( radians( `latitude` ) ) ) )  < ({$subscription['radius']} / 1000)
    ORDER BY `date` DESC
  ";

  $matching_events = $connection->query($query);
  
  $events = array();

  while($event = mysqli_fetch_assoc($matching_events)){
    $events[] = $event;
  }

  if (!empty($events)) {
    sendNotification($subscription, $events);
  }
}

mysqli_close($connection);

// Send email
function sendNotification($subscription, $events) {
  global $SITE_DOMAIN;
  global $FROM_EMAIL;
  global $connection;
  global $INTERVAL;
  global $logging;
  
  $earliest_res = $connection->query("SELECT `date`
    FROM `events`
    WHERE `date_added` > DATE_SUB(CURDATE(), INTERVAL $INTERVAL)
    ORDER BY `events`.`date` ASC
    LIMIT 1");
  $row = mysqli_fetch_assoc($earliest_res);
  $earliest_date = new DateTime($row['date']);
  
  $end_date = clone $earliest_date;
  $end_date->sub(new DateInterval("P1D"));
  $start_date = clone $earliest_date;
  $start_date->sub(new DateInterval("P7D"));

  // Look up YTD numbers
  $ytd_stats = getYTD($subscription);
  $prev_stats = getPrev($subscription, $start_date, $end_date);
  
  
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
  
  $event_types_found = array_keys(array_merge($frequencies, $prev_stats, $ytd_stats));
  
  $table_style = "font-family: Helvetica, Arial, Sans-Serif; text-align: left; border-spacing: 0; border: 1px solid #CCC; border-radius: 5px;";
  $td_style = "padding: 8px; padding-right: 15px; white-space: nowrap; font-size: 14px;";
  $th_style = "border-bottom: 2px solid #ddd;";
  $odd_td_style = "";
  $img_style= "border: 1px solid gray; margin-bottom: 20px; margin-top: 5px;";
  
  $summary_table = "<table style=\"$table_style\">";
  $summary_table .= "
    <thead>
      <tr>
        <th style=\"$td_style $th_style\">Type</th>
        <th style=\"$td_style $th_style\">This Week</th>
        <th style=\"$td_style $th_style\">Last Week</th>
        <th style=\"$td_style $th_style\">+/-</th>
        <th style=\"$td_style $th_style\">YTD</th>
      </tr>
    </thead>
  ";

  $row_counter = 0;
  foreach ($event_types_found as $event_type) {
    if (isset($frequencies[$event_type])) {
      $current_count = $frequencies[$event_type];
    } else {
      $current_count = 0;
    }
    
    if (isset($prev_stats[$event_type])) {
      $prev_count = $prev_stats[$event_type];
    } else {
      $prev_count = 0;
    }
    
    if (isset($ytd_stats[$event_type])) {
      $ytd_count = $ytd_stats[$event_type];
    } else {
      $ytd_count = 0;
    }
    
    $change_count = $current_count - $prev_count;
    if ($change_count > 0) {
      $change_count = "+" . $change_count;
    } elseif ($change_count == 0) {
      $change_count = "";
    }
    
    if (($row_counter % 2) == 1) {
      $row_style = "background-color: #EEEEEE;";
    } else {
      $row_style= "";
    }
    
    $summary_table .= "
    <tr style=\"$row_style\">
      <td style=\"$td_style\">$event_type</td>
      <td style=\"$td_style\">$current_count</td>
      <td style=\"$td_style\">$prev_count</td>
      <td style=\"$td_style\">$change_count</td>
      <td style=\"$td_style\">$ytd_count</td>
    </tr>
    ";
    $row_counter++;
  }
  $summary_table .= "</table>";
  
  $detail_table = "<table style=\"$table_style\">";
  $detail_table .= "
    <thead>
      <tr>
        <th style=\"$td_style $th_style\">Date</th>
        <th style=\"$td_style $th_style\">Type</th>
        <th style=\"$td_style $th_style\">Location</th>
      </tr>
    </thead>
  ";
  
  $row_counter = 0;
  $image_ids = array();
  foreach ($events as $event) {
    $date = date("l, M jS", strtotime($event['date']));
    $event_type = $event['event_type'];
    $street_name = ucwords(strtolower($event['street_name']));

    # get google map image filename
    $map_image = saveImage($event['id'], $event['latitude'], $event['longitude'], $event['event_type']);

    # id is just the basename without extension
    # see adding images: https://gist.github.com/andrewflash/7611200
    $image_id = basename($map_image, '.png');
    $image_ids[$map_image] = $image_id;

    if (($row_counter % 2) == 1) {
      $row_style = "background-color: #EEEEEE;";
    } else {
      $row_style= "";
    }
    $detail_table .= "
    <tr style=\"$row_style\">
      <td style=\"$td_style\">$date</td>
      <td style=\"$td_style\">$event_type</td>
      <td style=\"$td_style\">$street_name</td>
    </tr>
    <tr style=\"$row_style\">
      <td colspan=\"3\" style=\"$td_style\"><img style=\"$img_style\" alt=\"Map\" src=\"cid:$image_id\"></td>
    </tr>
    ";
    $row_counter ++;
  }
  $detail_table .= "</table>";
  
  $message = <<<EOT
    <h2 style="font-family: Helvetica, Arial, Sans-Serif;">Halicrime</h2>
    <p style="font-family: Helvetica, Arial, Sans-Serif;">Here are the events that happened.</p>  
    
    <h3 style="font-family: Helvetica, Arial, Sans-Serif; margin-top: 0;">Summary</h3>
    $summary_table
    
    <h3 style="font-family: Helvetica, Arial, Sans-Serif;">Details</h3>
    
    $detail_table
    
    <p style="font-family: Helvetica, Arial, Sans-Serif;"><a href="http://$SITE_DOMAIN/unsubscribe.php?guid={$subscription['guid']}">Unsubscribe</a></p>
EOT;

  // php mailer helps with adding images to mail
  $mail = new PHPMailer(true);

  try {
    $to = $subscription['email'];
    $subject = "Halicrime Alerts";
  
    $mail->setFrom($FROM_EMAIL, 'Halicrime');
    $mail->addAddress($subscription['email']);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    // add images
    foreach($image_ids as $image => $image_id) {
      // path, cid, name
      $mail->addEmbeddedImage($image, $image_id, $image_id);
    }
    $mail->Body = $message;
    $mail->AltBody = 'stay tuned for a plain text email';
    
    $mail->send();
    $logging->debug("email sent");
  } catch (Exception $e) {
    $logging->error("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
  }
}

/* 
 Get event type counts for the previous period
*/
function getPrev($subscription, $start_date, $end_date) {
  global $connection;
  global $logging;

  $start_date_string = $start_date->format("Y-m-d");
  $end_date_string = $end_date->format("Y-m-d");
  $query = "SELECT `event_type`, COUNT(*) as 'count'
    FROM `events`
    WHERE `date` BETWEEN '$start_date_string' AND '$end_date_string'
    AND ( 6371 * acos( cos( radians( {$subscription['latitude']} ) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians( {$subscription['latitude']} ) ) * sin( radians( `latitude` ) ) ) ) < ({$subscription['radius']} / 1000)
    GROUP BY `event_type`";

  $result = $connection->query($query);

  if (!$result) {
    $message = "Invalid query: " . $connection->error;
    $logging->error($message);
    return;
  }

  $stats = array();
  while ($row = $result->fetch_assoc()) {
    $stats[$row['event_type']] = $row['count'];
  }

  $result->free_result();
  
  return $stats;
}

/*
  Get year-to-date crime type counts for the area specified
*/
function getYTD($subscription) {
  global $connection;
  global $logging;

  $query = "SELECT `event_type`, COUNT(*) as 'count'
    FROM `events`
    WHERE YEAR(`date`) = YEAR(CURDATE())
    AND ( 6371 * acos( cos( radians( {$subscription['latitude']} ) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians( {$subscription['latitude']} ) ) * sin( radians( `latitude` ) ) ) ) < ({$subscription['radius']} / 1000)
    GROUP BY `event_type`";
  
  $result = $connection->query($query);

  if (!$result) {
    $message = "Invalid query: " . $connection->error;
    $logging->error($message);
    return;
  }

  $stats = array();
  while ($row = $result->fetch_assoc()) {
    $stats[$row['event_type']] = $row['count'];
  }
  $result->free_result();
  
  return $stats;
  
}

/*
  Download image of map at location and save to server. Returns saved file location.
*/
function saveImage($id, $latitude, $longitude, $basic_event_type){ 
  global $SITE_DOMAIN;
  global $STATIC_API_KEY;
  global $logging;
  
  $filename = "img/map_snapshots/event_$id.png";
  $disk_location = dirname(__FILE__) . "/public_html/" . $filename;
  // If we already have the image, don't re-download it
  if (file_exists($disk_location)) {
    $logging->debug("Image already downloaded.");
    return $disk_location;
  }
  
  $logging->debug("Saving image");
  $icon_url = "http://$SITE_DOMAIN/" . getIcon($basic_event_type);
  $icon = "icon:$icon_url";
  
  if (preg_match('/localhost/', $SITE_DOMAIN) == 1) {
    $icon = "color:red";
  }

  $map_params = array(
    'center' => "$latitude,$longitude",
    'zoom' => 14,
    'size' => '400x150',
    'markers' => "$icon|$latitude,$longitude",
    'key' => $STATIC_API_KEY,
  );
  $base_url = "https://maps.googleapis.com/maps/api/staticmap";
  $url = $base_url . '?' . http_build_query($map_params);

  // make sure dir exists
  $dirname = dirname($disk_location);
  if (!is_dir($dirname)) {
    mkdir($dirname, 0755, true);
  }
  file_put_contents($disk_location, curl_get_contents($url));
  
  $logging->debug("Saved to $filename");
  return $disk_location;
}

function curl_get_contents($url)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  $data = curl_exec($ch);
  curl_close($ch);
  return $data;
}

function getIcon($crime_type){
  $icon_path = 'crime.png';
  switch ($crime_type) {
      case 'ASSAULT':
          $icon_path = 'assault.png';
          break;
      case 'THEFT OF VEHICLE':
          $icon_path = 'theftofmotorvehicle.png';
          break;
      case 'BREAK AND ENTER':
          $icon_path = 'breakandenter.png';
          break;
      case 'THEFT FROM VEHICLE':
          $icon_path = 'theftfrommotorvehicle.png';
          break;
      case 'ROBBERY':
          $icon_path = 'robbery.png';
          break;
  }
  return "img/" . $icon_path;
}