<?php

include 'util.php';

$config = parse_ini_file(dirname(__FILE__) . "/settings.cfg", true);
$STATIC_API_KEY = $config['google_maps']['static_api_key'];
$SITE_DOMAIN = 'halicrime.stewartrand.com';

connect_db();

// Get active subscriptions
echo "Looking up subscriptions\n";
$subscriptions = mysql_query("SELECT * FROM `subscriptions` WHERE `activated` = 1 AND `unsubscribed` = 0");

if (!$subscriptions) {
  die("Invalid query: " . mysql_error());
}

// For each subscription, check if there have been any matching events since the last run
while ($subscription = mysql_fetch_assoc($subscriptions)) {
  echo "Looking up recent events.";
  $query = "
    SELECT *
    FROM `events`  INNER JOIN `event_types` ON `events`.`event_type_id` = `event_types`.`code`
    WHERE `date_added` > DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND ( 6371 * acos( cos( radians({$subscription['latitude']}) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians({$subscription['latitude']}) ) * sin( radians( `latitude` ) ) ) )  < ({$subscription['radius']} / 1000)
    ORDER BY `date` DESC
  ";
  
  $matching_events = mysql_query($query);
  
  $events = array();
  while($event = mysql_fetch_assoc($matching_events)){
    $events[] = $event;
  }
  
  sendNotification($subscription, $events);
  
}


// Send email
function sendNotification($subscription, $events) {
  global $SITE_DOMAIN;
  
  $earliest_event_query = "
    SELECT `date`
    FROM `events`  INNER JOIN `event_types` ON `events`.`event_type_id` = `event_types`.`code`
    WHERE `date_added` > DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY `events`.`date` ASC
LIMIT 1";
  $earliest_res = mysql_query($earliest_event_query);
  $row = mysql_fetch_assoc($earliest_res);
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
    if (isset($frequencies[$event['name']])) {
      $frequencies[$event['name']]++;
    }
    else {
      $frequencies[$event['name']] = 1;
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
  echo "Summarizing frequencies...\n";
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
  
  echo "Building detail table...\n";
  $row_counter = 0;
  foreach ($events as $event) {
    echo "Found event of type {$event['name']}\n";
    $date = date("l, M jS", strtotime($event['date']));
    $event_type = $event['name'];
    $street_name = ucwords(strtolower($event['street_name']));
    $map_image_url = saveImage($event['id'], $event['latitude'], $event['longitude'], $event['event_type']);
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
      <td colspan=\"3\" style=\"$td_style\"><img style=\"$img_style\" alt=\"Map\" src=\"$map_image_url\"></td>
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
  global $SITE_DOMAIN;
  $to = $subscription['email'];
  $subject = "Halicrime Alerts";
  $headers = "From: Halicrime <subscribe@halicrime.ca>\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
  
  

  mail($to, $subject, $message, $headers);
    
  
}

/* 
 Get event type counts for the previous period
*/
function getPrev($subscription, $start_date, $end_date) {
  echo "Checking previous period.\n";
  $start_date_string = $start_date->format("Y-m-d");
  $end_date_string = $end_date->format("Y-m-d");
  $query = "
  SELECT `name`, COUNT(*) as 'count'
  FROM `events` INNER JOIN `event_types` ON `events`.`event_type_id` = `event_types`.`code`
  WHERE `date` BETWEEN '$start_date_string' AND '$end_date_string'
  AND ( 6371 * acos( cos( radians( {$subscription['latitude']} ) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians( {$subscription['latitude']} ) ) * sin( radians( `latitude` ) ) ) ) < ({$subscription['radius']} / 1000)
  GROUP BY `name`
  ";
  
  $result = mysql_query($query);
  $stats = array();
  while ($row = mysql_fetch_assoc($result)) {
    $stats[$row['name']] = $row['count'];
  }
  
  return $stats;
}

/*
  Get year-to-date crime type counts for the area specified
*/
function getYTD($subscription) {
  echo "Getting YTD stats.\n";
  $query = "
  SELECT `name`, COUNT(*) as 'count'
  FROM `events` INNER JOIN `event_types` ON `events`.`event_type_id` = `event_types`.`code`
  WHERE YEAR(`date`) = YEAR(CURDATE())
  AND ( 6371 * acos( cos( radians( {$subscription['latitude']} ) ) * cos( radians( `latitude`) ) * cos( radians( `longitude` ) - radians({$subscription['longitude']}) ) + sin( radians( {$subscription['latitude']} ) ) * sin( radians( `latitude` ) ) ) ) < ({$subscription['radius']} / 1000)
  GROUP BY `name`
  ";
  
  $result = mysql_query($query);
  $stats = array();
  while ($row = mysql_fetch_assoc($result)) {
    $stats[$row['name']] = $row['count'];
  }
  
  return $stats;
  
}

/*
  Download image of map at location and save to server. Returns saved file location.
*/
function saveImage($id, $latitude, $longitude, $basic_event_type){ 
  global $SITE_DOMAIN;
  global $STATIC_API_KEY;
  
  $filename = "public_html/img/map_snapshots/event_$id.png";
  $disk_location = dirname(__FILE__) . "/" . $filename;
  // If we already have the image, don't re-download it
  if (file_exists($disk_location)) {
    echo "Image already downloaded.\n";
    return "http://$SITE_DOMAIN/$filename";
  }
  
  echo "Saving image\n";
  $icon_url = "http://$SITE_DOMAIN/" . getIcon($basic_event_type);
  $map_params = array(
    'center' => "$latitude,$longitude",
    'zoom' => 14,
    'size' => '400x150',
    'markers' => "icon:$icon_url|$latitude,$longitude",
    'key' => $STATIC_API_KEY,
  );
  $base_url = "https://maps.googleapis.com/maps/api/staticmap";
  $url = $base_url . '?' . http_build_query($map_params);  
  file_put_contents($disk_location, fopen($url, 'r'));
  
  echo "Saved to $filename\n";
  return "http://$SITE_DOMAIN/$filename";
}

function getIcon($crime_type){
  $icon_path = '';
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



?>