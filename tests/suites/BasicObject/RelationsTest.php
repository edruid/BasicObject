<?php

class RelationTest extends DatabaseTestCase {

	public function testGetOtherModel() {
		$m1 = Blueprint::make('Model1');
		$m2 = Blueprint::make('Model2');
		$m1->model2_id = $m2->id;
		$m1->commit();

		$m2_ret = $m1->Model2();
		$this->assertEquals($m2, $m2_ret);
	}

	/**
	 * @depends testGetOtherModel
	 */
	public function testSetOtherModel() {
		$m1 = Blueprint::make('Model1');
		$m2 = Blueprint::make('Model2');
	
		$m1->Model2 = $m2;

		$this->assertEquals($m2->id, $m1->model2_id);
		$this->assertEquals($m2, $m1->Model2());

		$m1->commit();
	}
}
