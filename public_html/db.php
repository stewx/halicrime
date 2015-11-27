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

?>