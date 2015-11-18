<?php

$SITE_DOMAIN = 'halicrime.stewartrand.com';

function connect_db(){
    $config = parse_ini_file("../settings.cfg", true);

    $connection=mysql_connect(localhost, $config['db']['db_user'], $config['db']['db_pass']);
    if (!$connection) {
      die("Not connected : " . mysql_error());
    }

    // Set the active mySQL database
    $db_selected = mysql_select_db($config['db']['db_name'], $connection);
    if (!$db_selected) {
      die ("Can\'t use db : " . mysql_error());
    }
}

function getGUID() {
    mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
    $charid = strtoupper(md5(uniqid(rand(), true)));
    $hyphen = chr(45);// "-"
    $uuid = substr($charid, 0, 8).$hyphen
        .substr($charid, 8, 4).$hyphen
        .substr($charid,12, 4).$hyphen
        .substr($charid,16, 4).$hyphen
        .substr($charid,20,12);
    return $uuid;
}

function get_events($form) {
    $bounds = explode(',', $form['bounds']); 
    $southwest = array(
        'lng' => $bounds[0],
        'lat' => $bounds[1]
    );
    $northeast = array(
        'lng' => $bounds[2],
        'lat' => $bounds[3]
    );
    
    $query = sprintf("
        SELECT *
        FROM `events`
        WHERE
            `latitude` BETWEEN {$southwest['lat']} AND {$northeast['lat']}
            AND `longitude` BETWEEN {$southwest['lng']} AND {$northeast['lng']}
            AND `date` > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LIMIT 1000
    
    ");
    $result = mysql_query($query);

    if (!$result) {
      die("Invalid query: " . mysql_error());
    }

    $events = array();
    while ($row = @mysql_fetch_assoc($result)){
      $events[] = $row;
    }

    header("Content-type: application/json");
    $response_data = array(
        'events' => $events
    );
    
    echo json_encode($response_data);
}

function subscribe($form) {
    $guid = getGUID();
    $name = $form['name'] ? "\"{$form['name']}\"" : "NULL";
    $query = sprintf("
    INSERT INTO `subscriptions` (`guid`, `created`, `lat`, `lng`, `radius`, `name`, `email`)
    VALUES ('$guid', NOW(), {$form['center']['lat']}, {$form['center']['lng']}, {$form['radius']}, {$name}, '{$form['email']}')
    ");
    $result = mysql_query($query);
    
    if (!$result) {
      die("Invalid query: " . mysql_error());
    }
    
    send_confirmation_email($form['email'], $guid);    
}

function send_confirmation_email($email_address, $guid) {
  global $SITE_DOMAIN;
  $to = $email_address;
  $subject = "Confirm your crime alert subscription";
  $headers = "From: Halicrime <subscribe@halicrime.ca>\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
  
  $message = <<<EOT
   <h2 style="font-family: Helvetica, Arial, Sans-Serif;">Halicrime</h2>
    <p style="font-family: Helvetica, Arial, Sans-Serif;">Please confirm you want to receive crime alerts for the area of Halifax you selected.</p>
    <p style="font-family: Helvetica, Arial, Sans-Serif;">You can unsubscribe at any time.</p>
    <a style="font-family: Helvetica, Arial, Sans-Serif;
    display: inline-block;
    padding: 6px 12px;
    margin-bottom: 0;
    font-size: 14px;
    font-weight: 400;
    line-height: 1.42857143;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    -ms-touch-action: manipulation;
    touch-action: manipulation;
    cursor: pointer;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    background-image: none;
    border: 1px solid transparent;
    border-radius: 4px;
    color: #fff;
    background-color: #11A914;
    border-color: #267700;
    " href="http://$SITE_DOMAIN/confirm.php?guid=$guid">Confirm Subscription</a>
    <p style="font-family: Helvetica, Arial, Sans-Serif;">If you did not request to receive crime alerts from Halicrime, you may ignore this message.</p>   
   
EOT;

  mail($to, $subject, $message, $headers);
}

// Main code

connect_db();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_json = file_get_contents('php://input');
    $form_data = json_decode($raw_json, true);
    if ($form_data['action'] === 'subscribe') {
        subscribe($form_data);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['action'] === 'get_events') {
        get_events($_GET);
    }    
    
}






?>