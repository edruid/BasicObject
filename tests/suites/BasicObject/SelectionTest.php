<?php

class SelectionTest extends DatabaseTestCase {
	public function testAutomaticJoin() {
	}

	public function testManualJoin() {
	}

	public function testOrder() {
		$key = "ordertest";
		$m1 = Blueprint::make('Model1', array('int1' => 1, 'str1' => $key));
		$m2 = Blueprint::make('Model1', array('int1' => 2, 'str1' => $key));

		$selection = Model1::selection(array('str1' => $key, '@order' => 'int1'));
		$this->assertEquals($selection[0]->id, $m1->id);
		$this->assertEquals($selection[1]->id, $m2->id);

		$selection = Model1::selection(array('str1' => $key, '@order' => 'int1:desc'));
		$this->assertEquals($selection[0]->id, $m2->id);
		$this->assertEquals($selection[1]->id, $m1->id);
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage No such column 'foobar' in table 'model1' (value 'bar')
	 */
	public function testUnknowColumn() {
		Model1::selection(array('foobar' => 'bar'));
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage No such table 'foobar'
	 */
	public function testInvalidJoin() {
		Model1::selection(array('foobar.foo' => 'a'));
	}

	/* TODO: Add much more tests */
}
