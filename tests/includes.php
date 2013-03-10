<?php
include realpath(dirname(__FILE__)) . "/database.php";

include realpath(dirname(__FILE__)) . "/../BasicObject.php";
include realpath(dirname(__FILE__)) . "/../ValidatingBasicObject.php";

function __autoload($class)
{
	$root = realpath(dirname(__FILE__));
	if(file_exists($root.'/models/'.$class.'.php')){
		require_once $root.'/models/'.$class.'.php';
	}
}

