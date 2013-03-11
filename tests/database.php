<?php

include realpath(dirname(__FILE__)) . "/settings.php";


function db_create($select_db = false) {
	global $db, $db_settings;
	/* Database */
	$db = new mysqli(
		$db_settings['host'],
		$db_settings['username'],
		$db_settings['password'],
		$select_db ? $db_settings['database'] : "",
		$db_settings['port']
	);
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

	if(!$db->multi_query($contents)) {
		echo "Failed to execute query: {$db->error}\n";
	}
}

function db_close() {
	global $db;
	$db->close();
}
