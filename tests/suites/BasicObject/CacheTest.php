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

	public function testQueryReductionFromId() {
		CountingDB::$queries = 0;

		$m1 = Blueprint::make('Model1');
		$id = $m1->id;

		Model1::from_id($id);
		$num_queries = CountingDB::$queries;
		for($i=0; $i<100; ++$i) {
			$m = Model1::from_id($id);
			$this->assertEquals($m1, $m);
		}

		$this->assertEquals($num_queries, CountingDB::$queries);
	}
}
