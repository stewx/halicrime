<?php

function connect_db(){
    $config = parse_ini_file(dirname(dirname(__FILE__)) . "/settings.cfg", true);

    $connection = mysql_connect('localhost', $config['db']['db_user'], $config['db']['db_pass']);
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

?>