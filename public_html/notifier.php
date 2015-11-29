<?php

include 'util.php';

$config = parse_ini_file(dirname(dirname(__FILE__)) . "/settings.cfg", true);
$STATIC_API_KEY = $config['google_maps']['static_api_key'];
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
    SELECT *
    FROM `events`  INNER JOIN `event_types` ON `events`.`event_type_id` = `event_types`.`code`
    WHERE `date` > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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
  
  // Look up YTD numbers
  $ytd_stats = getYTD($subscription);
  $prev_stats = getPrev($subscription);
  
  
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
  
  $table_style = "font-family: Helvetica, Arial, Sans-Serif; text-align: left;";
  $td_style = "padding-right: 15px; white-space: nowrap; font-size: 14px;";
  $th_style = "border-bottom: 2px solid #ddd;";
  $odd_td_style = "";
  $img_style= "border: 1px solid gray; margin-bottom: 20px; margin-top: 5px;";
  $container_style = "padding: 20px; margin: 20px 0; border: 1px solid #BBB; border-radius: 10px;";
  
  $summary_table = "<table style=\"$table_style\">";
  $summary_table .= "
    <thead>
      <tr>
        <th style=\"$td_style $th_style\">Type</th>
        <th style=\"$td_style $th_style\">This Week</th>
        <th style=\"$td_style $th_style\">Last Week</th>
        <th style=\"$td_style $th_style\">&plusmn;</th>
        <th style=\"$td_style $th_style\">YTD</th>
      </tr>
    </thead>
  ";
  echo "Summarizing frequencies...\n";
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
    
    $summary_table .= "
    <tr>
      <td style=\"$td_style\">$event_type</td>
      <td style=\"$td_style\">$current_count</td>
      <td style=\"$td_style\">$prev_count</td>
      <td style=\"$td_style\">$change_count</td>
      <td style=\"$td_style\">$ytd_count</td>
    </tr>
    ";
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
  foreach ($events as $event) {
    echo "Found event of type {$event['name']}\n";
    $date = date("l, M jS", strtotime($event['date']));
    $event_type = $event['name'];
    $street_name = ucwords(strtolower($event['street_name']));
    $map_image_url = saveImage($event['id'], $event['latitude'], $event['longitude'], $event['event_type']);
    $detail_table .= "
    <tr>
      <td style=\"$td_style\">$date</td>
      <td style=\"$td_style\">$event_type</td>
      <td style=\"$td_style\">$street_name</td>
    </tr>
    <tr>
      <td colspan=\"3\" style=\"$td_style\"><img style=\"$img_style\" alt=\"Map\" src=\"$map_image_url\"></td>
    </tr>
    ";
  }
  $detail_table .= "</table>";
  
  $message = <<<EOT
    <h2 style="font-family: Helvetica, Arial, Sans-Serif;">Halicrime</h2>
    <p style="font-family: Helvetica, Arial, Sans-Serif;">Here are the events that happened.</p>  
    
    <div style="$container_style">
      <h3 style="font-family: Helvetica, Arial, Sans-Serif; margin-top: 0;">Summary</h3>
      $summary_table
    </div>
    
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
function getPrev($subscription) {
  // TODO: Figure out what the correct prev period is
  $query = "
  SELECT `name`, COUNT(*) as 'count'
  FROM `events` INNER JOIN `event_types` ON `events`.`event_type_id` = `event_types`.`code`
  WHERE `date` BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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
  
  $filename = "img/map_snapshots/event_$id.png";
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
