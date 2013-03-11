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

	public function testEditAndLoad() {
		$m1 = Blueprint::make('Model1');
		$m1 = Model1::from_id($m1->id);
		$val = $m1->int1 + 10;
		$m1->int1 = $val;
		$m1->commit();

		$m1 = Model1::from_id($m1->id);
		$this->assertEquals($val, $m1->int1);

	}
}
