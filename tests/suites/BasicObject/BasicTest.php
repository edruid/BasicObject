<?php

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

	/**
	 * @depends testInsert
	 */
	public function testIsset() {
		$model = Blueprint::make('Model1', false);
		$this->assertTrue(isset($model->id), 'id');
		$this->assertTrue(isset($model->int1), 'int1');
		$this->assertTrue(isset($model->str1), 'str1');
		$this->assertFalse(isset($model->foobar));
	}

	/**
	 * @depends testInsert
	 */
	public function testDelete() {
		$model = Blueprint::make('Model1');
		$id = $model->id;

		$model->delete();

		$model = Model1::from_id($id);
		$this->assertNull($model);
	}

	/**
	 * @depends testInsert
	 */
	public function testSelection() {
		$key = 'selection_test';
		$models = array(
			Blueprint::make('Model1', array('str1' => $key)),
			Blueprint::make('Model1', array('str1' => $key)),
		);
		$res = Model1::selection(array('str1' => $key));
		$this->assertCount(count($models), $res);

		$this->assertTrue(compare_result($res, $models));
	}

	/**
	 * @depends testSelection
	 */
	public function testSum() {
		$sum = 0;
		$key = "sumtest";
		for($i = 0; $i < 100; ++$i) {
			Blueprint::make('Model1', array('str1' => $key, 'int1' => $i));
			$sum += $i;
		}
		$this->assertEquals(Model1::sum('int1', array('str1' => $key)), $sum);
	}

	/**
	 * @depends testSelection
	 */
	public function testCount() {
		$key = "counttest";
		for($i = 0; $i < 50; ++$i) {
			Blueprint::make('Model1', array('str1' => $key));
		}
		$this->assertEquals(Model1::count(array('str1' => $key)), 50);
	}

}

