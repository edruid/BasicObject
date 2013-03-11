<?php

require_once 'PEAR.php';

include realpath(dirname(__FILE__)) . "/database.php";

include realpath(dirname(__FILE__)) . "/MC.php";
include realpath(dirname(__FILE__)) . "/../BasicObject.php";
include realpath(dirname(__FILE__)) . "/../ValidatingBasicObject.php";
include realpath(dirname(__FILE__)) . "/Blueprint.php";
include realpath(dirname(__FILE__)) . "/DatabaseTestCase.php";

foreach(glob(realpath(dirname(__FILE__)) . "/models/*.php") as $filename) {
	require_once $filename;
}
