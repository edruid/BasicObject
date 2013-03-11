<?php

require_once dirname(__FILE__) . "/../includes.php";

class BasicTest extends DatabaseTestCase {

	public function testFromId() {
		global $db;
		if(!$db->query("INSERT INTO `model1` SET `int1` = 5, `str1` = 'foobar'")) {
			throw new Exception("Failed to insert model by manual query: ".$db->error);
		}
		$id = $db->insert_id;
		$obj = Model1::from_id($id);
		$this->assertNotNull($obj);
		$this->assertEquals($obj->int1, 5);
		$this->assertEquals($obj->str1, "foobar");
	}

	/**
	 * @depends testFromId
	 */
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

	/**
	 * @depends testFromId
	 */
	public function testArrayInsert() {
		$model1 = new Model1(array('int1'=>1, 'str1' => "Test"));
		$model1->commit();

		$this->assertNotNull($model1->id);

		$id = $model1->id;
		unset($model1);

		$model1 = Model1::from_id($id);

		$this->assertEquals($model1->int1, 1);
		$this->assertEquals($model1->str1, "Test");
	}

}

