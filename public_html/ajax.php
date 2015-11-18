<?php

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