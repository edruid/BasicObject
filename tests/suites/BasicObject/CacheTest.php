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

	public function testStructureCacheFill() {
		$sc_vars = $this->getStructureCacheVariables();

		Model1::from_id(1);
		Model1::selection(array('model2.int1' => 1));
		$vals = array();
		foreach($sc_vars as $v => $prop) {
			$vals[$v] = $prop->getValue();
			$this->assertNotEmpty($vals[$v]);
		}
	}

	public function testStructureCacheRestore() {
		$sc_vars = $this->getStructureCacheVariables();

		Model1::from_id(1);
		Model1::selection(array('model2.int1' => 1));
		$vals = array();
		foreach($sc_vars as $v => $prop) {
			$vals[$v] = $prop->getValue();
			$prop->setValue(array());
		}

		BasicObject::enable_structure_cache(MC::get_instance(), "bo_unit_test_");
		foreach($sc_vars as $v => $prop) {
			$this->assertEquals($vals[$v], $prop->getValue());
		}
	}

	public function testClearStructureCache() {
		$sc_vars = $this->getStructureCacheVariables();
		Model1::from_id(1);
		Model1::selection(array('model2.int1' => 1));
		BasicObject::clear_structure_cache(MC::get_instance(), "bo_unit_test_");

		BasicObject::enable_structure_cache(MC::get_instance(), "bo_unit_test_");
		foreach($sc_vars as $v => $prop) {
			$this->assertEmpty($prop->getValue());
		}
	}

	public function testClearStructureCachePrefixSeparation() {
		$sc_vars = $this->getStructureCacheVariables();
		Model1::from_id(1);
		Model1::selection(array('model2.int1' => 1));

		BasicObject::clear_structure_cache(MC::get_instance(), "bo_unit_test2_");

		BasicObject::enable_structure_cache(MC::get_instance(), "bo_unit_test_");
		foreach($sc_vars as $v => $prop) {
			$this->assertNotEmpty($prop->getValue());
		}
	}

	private function getStructureCacheVariables() {
		$ret = array();
		$vars = array('column_ids', 'connection_table', 'tables', 'columns');
		foreach($vars as $var) {
			$ret[$var] = new ReflectionProperty('BasicObject', $var);
			$ret[$var]->setAccessible(true);
		}
		return $ret;
	}
}
