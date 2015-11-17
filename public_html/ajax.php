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

function get_events($form) {
    $bounds = $form['bounds']; 
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
    
}

// Main code

connect_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['action'] == 'get_events') {
        get_events($_GET);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'subscribe') {
        subscribe($_POST);
    }
}




?>