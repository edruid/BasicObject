<?php

class DatabaseTestCase extends PHPUnit_Framework_TestCase {
	protected $backupGlobalsBlacklist = array('db');

	public static function setUpBeforeClass() {
		db_create(true);
		BasicObject::enable_structure_cache(MC::get_instance());
		//make sure cache is only on when explicitly set
		BasicObject::disable_cache();
	}

	public function setUp() {
		BasicObject::$output_htmlspecialchars = false;
		db_clean();
	}

	public static function tearDownAfterClass() {
		BasicObject::clear_structure_cache(MC::get_instance());
		db_close();
	}
}
