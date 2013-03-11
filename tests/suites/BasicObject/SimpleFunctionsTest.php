<?php

class SimpleFunctionsTest extends DatabaseTestCase {
	public function testFirst() {
		$m1 = Blueprint::make('Model1', array('int1' => 1, 'str1' => 'firsttest'));
		$m2 = Blueprint::make('Model1', array('int1' => 2, 'str1' => 'firsttest'));

		$this->assertEquals($m1->id, Model1::first(array('str1' => 'firsttest', '@order' => 'int1'))->id);
		$this->assertEquals($m2->id, Model1::first(array('str1' => 'firsttest', '@order' => 'int1:desc'))->id);
	}

	public function testOne() {
		$m1 = Blueprint::make('Model1');

		$obj = Model1::one(array('int1' => $m1->int1));

		$this->assertEquals($m1->id, $obj->id);
	}

	/**
	 * @expectedException Exception
	 */ 
	public function testOneFailsCorrectly() {
		$m1 = Blueprint::make('Model1', array('str1' => 'testonefail'));
		$m1 = Blueprint::make('Model1', array('str1' => 'testonefail'));

		Model1::one(array('str1' => 'testonefail'));
	}

	public function testDuplicate() {
		$m1 = Blueprint::make('Model1');
		$m2 = $m1->duplicate();

		$this->assertNull($m2->id);
		$this->assertEquals($m1->int1, $m2->int1);
		$this->assertEquals($m1->str1, $m2->str1);

		$m2->int1++;
		$this->assertEquals($m1->int1 + 1, $m2->int1);

		$m2->commit();

		$this->assertNotNull($m2->id);

		$this->assertNotEquals($m1->id, $m2->id);
	}

	public function testOutputHTMLSpecialChars() {
		$html = "<html>Foobar \" <script foo='bar'>derp;</script> </html>";
		$m1 = Blueprint::make('Model1', array('str1' => $html));

		BasicObject::$output_htmlspecialchars = false;

		$m1 = Model1::from_id($m1->id);

		$this->assertEquals($html, $m1->str1);

		BasicObject::$output_htmlspecialchars = true;
		$escaped = htmlspecialchars($html, ENT_QUOTES, 'utf-8');
		$this->assertEquals($escaped, $m1->str1);

		BasicObject::$output_htmlspecialchars = false;
	}

	public function testDefaultOrder() {
		$key = "ordertest";
		$m1 = Blueprint::make('Model1', array('int1' => 1, 'str1' => $key));
		$m2 = Blueprint::make('Model1', array('int1' => 2, 'str1' => $key));

		Model1Ordered::$order = "int1";
		$selection = Model1Ordered::selection(array('str1' => $key));
		$this->assertEquals($m1->id, $selection[0]->id);
		$this->assertEquals($m2->id, $selection[1]->id);

		Model1Ordered::$order = "int1:desc";
		$selection = Model1Ordered::selection(array('str1' => $key));
		$this->assertEquals($m2->id, $selection[0]->id);
		$this->assertEquals($m1->id, $selection[1]->id);
	}

}
