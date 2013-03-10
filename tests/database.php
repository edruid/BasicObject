<?php

include realpath(dirname(__FILE__)) . "/settings.php";

global $db;

/* Database */
$db = new mysqli(
	$db_settings['host'],
	$db_settings['username'],
	$db_settings['password'],
	"",
	$db_settings['port']
);

function db_prepare_testing() {
	db_select_database();
}

function db_select_database() {
	global $db, $db_settings;
	$db->select_db($db_settings['database']);
}

function db_clean() {
	global $db;
	$db->query("TRUNCATE TABLE *");
}

function db_run_file($filename) {
	global $db;
	$handle = fopen(realpath(dirname(__FILE__) . "/" . $filename ), "r");
	$contents = fread($handle, filesize($filename));
	fclose($handle);

	$db->multi_query($contents);
}
