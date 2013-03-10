<?php

class DatabaseTestCase extends PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {
		db_select_database();
		BasicObject::enable_structure_cache(MC::get_instance());
		//make sure cache is only on when explicitly set
		BasicObject::disable_cache();
	}

	public function setUp() {
		BasicObject::$output_htmlspecialchars = false;
		db_clean();
	}
}
