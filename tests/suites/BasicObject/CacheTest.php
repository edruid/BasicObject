<?php

/**
 * @group cache
 */
class CacheTest extends DatabaseTestCase {
	public function setUp() {
		DatabaseTestCase::setUp();
		BasicObject::invalidate_cache();
	}

	public function assertPreConditions() {
		global $cache;
		if($cache == false) {
			echo "Trying to run cached tests with cache disabled\n";
			exit(-1);
		}
	}

	public function testFromField() {
	}
}
