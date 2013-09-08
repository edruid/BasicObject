<?php

class DatabaseTestCase extends PHPUnit_Framework_TestCase {
	protected $backupGlobalsBlacklist = array('db');

	public static function setUpBeforeClass() {
		global $cache;
		db_init();
		BasicObject::enable_structure_cache(MC::get_instance(), "bo_unit_test_");
		if($cache) {
			BasicObject::enable_cache();
		} else {
			BasicObject::disable_cache();
		}
	}

	public function setUp() {
		BasicObject::$output_htmlspecialchars = false;
	}

	public static function tearDownAfterClass() {
		BasicObject::clear_structure_cache(MC::get_instance(), "bo_unit_test_");
		db_close();
	}
}
