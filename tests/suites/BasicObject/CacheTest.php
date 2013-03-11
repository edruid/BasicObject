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
			Model1::from_id($id);
		}

		$this->assertEquals($num_queries, CountingDB::$queries);
	}

	public function testQueryReductionSelection() {
		CountingDB::$queries = 0;

		$m1 = Blueprint::make('Model1');

		Model1::selection(array('int1' => $m1->int1));
		$num_queries = CountingDB::$queries;
		for($i=0; $i<100; ++$i) {
			Model1::selection(array('int1' => $m1->int1));
		}

		$this->assertEquals($num_queries, CountingDB::$queries);
	}

	public function testQueryReductionCount() {
		CountingDB::$queries = 0;

		$m1 = Blueprint::make('Model1');

		Model1::count(array('int1' => $m1->int1));
		$num_queries = CountingDB::$queries;
		for($i=0; $i<100; ++$i) {
			Model1::count(array('int1' => $m1->int1));
		}

		$this->assertEquals($num_queries, CountingDB::$queries);
	}

	public function testQueryReductionSum() {
		CountingDB::$queries = 0;

		$m1 = Blueprint::make('Model1');

		Model1::sum('int1', array('int1' => $m1->int1));
		$num_queries = CountingDB::$queries;
		for($i=0; $i<100; ++$i) {
			Model1::sum('int1', array('int1' => $m1->int1));
		}

		$this->assertEquals($num_queries, CountingDB::$queries);
	}
}
