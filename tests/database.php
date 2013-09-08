<?php

$settings_file = realpath(dirname(__FILE__)) . "/settings.php";

if(file_exists($settings_file)) {
	include $settings_file;
} else {
	echo "\033[0;31m";
	echo "Please configure test database in settings.php (see settings.php.sample)\n";
	echo "\033[0;37m";
	exit(-1);
}

/*
 * Setup database for testing
 */
function db_init() {
	global $db, $db_settings;
	/* Database */
	$db = new CountingDB(
		$db_settings['host'],
		$db_settings['username'],
		$db_settings['password'],
		"",
		$db_settings['port']
	);

	$db->query("DROP DATABASE `{$db_settings['database']}`");
	$db->query("CREATE DATABASE `{$db_settings['database']}`");
	db_select_database();
	db_run_file("db.sql");
	BasicObject::clear_structure_cache(MC::get_instance(), "bo_unit_test_");
}

function db_select_database() {
	global $db, $db_settings;
	$db->select_db($db_settings['database']);
}

function db_run_file($filename) {
	global $db;
	$handle = fopen(realpath(dirname(__FILE__) . "/" . $filename ), "r");
	$contents = fread($handle, filesize($filename));
	fclose($handle);

	if(!$db->multi_query($contents)) {
		throw new Exception("Failed to execute query: {$db->error}\n");
	}


	do {
		$result = $db->use_result();
		if($result) $result->free();
	} while($db->more_results() && $db->next_result());
}

function db_query($query) {
	global $db;
	if(!$db->query($db)) {
		throw new Exception("Failed execute manual query '$query': ".$db->error);
	}
}

function db_close() {
	global $db, $db_settings;
	$db->close();
}

/**
 * Counting database class
 */

class CountingDB extends MySQLi {

	public static $queries = 0;

	public function __construct($host, $username, $password, $database, $port) {
		parent::__construct($host, $username, $password, $database, $port);
	}	

	public function prepare($query) {
		return new CountingStatement($this, $query);
	}
}

class CountingStatement extends mysqli_stmt {
	public function __construct($db, $query) {
		parent::__construct($db, $query);
	}

	public function execute() {
		++CountingDB::$queries;
		return parent::execute();
	}
}
