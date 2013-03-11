<?php

require_once dirname(__FILE__) . "/../includes.php";

class BasicTest extends DatabaseTestCase {

	public function testInsert() {
		$model1 = new Model1();
		$model1->int1 = 1;
		$model1->str1 = "Test";
		$model1->commit();

		$this->assertNotNull($model1->id);

		$id = $model1->id;
		unset($model1);

		$model1 = Model1::from_id($id);

		$this->assertEquals($model1->int1, 1);
		$this->assertEquals($model1->str1, "Test");
	}
}

