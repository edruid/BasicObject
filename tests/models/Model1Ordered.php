<?php

class Model1Ordered extends BasicObject {

	public static $order = "int1";

	protected static function default_order() {
		return self::$order;
	}

	protected static function table_name() {
		return 'model1';
	}
}
