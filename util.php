<?php

function connect_db(){
    $config = parse_ini_file(dirname(__FILE__) . "/settings.cfg", true);

    $host = getenv('DB_HOST');
    $host = $host ? $host : 'localhost';
    
    $user = $config['db']['db_user'];
    $user = $user ? $user : getenv('MYSQL_USER');

    $pass = $config['db']['db_pass'];
    $pass = $pass ? $pass : getenv('MYSQL_PASSWORD');

    $db = $config['db']['db_name'];
    $db = $db ? $db : getenv('MYSQL_DATABASE');

    $connection = new mysqli($host, $user, $pass, $db);
    
    if (!$connection) {
      die("Not connected : " . $connection->connect_error);
    }

    return $connection;
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