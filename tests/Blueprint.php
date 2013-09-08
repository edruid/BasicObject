<?php

/**
 * Creates fresh instances from blueprints in blueprint folder
 */
class Blueprint {

	private static $blueprints_dir = "blueprints/";
	private static $sn = 0;

	/**
	 * Create a new instance of $class from a blueprint
	 * @param $class The class name of the model to create
	 * @param Extra arguments:
	 *	A string: Specify a named blueprint to use
	 *	A boolean: Toggle commit (default: true)
	 *	A array: Override/Set values in the model
	 */
	public static function make($class) {
		$set = array();
		$name = "default";
		$commit = true;

		$args = func_get_args();
		for($i=1; $i<count($args); ++$i) {
			if(is_array($args[$i])) {
				$set = $args[$i];
			} else if(is_string($args[$i])) {
				$name = $args[$i];
			} else if(is_bool($args[$i])) {
				$commit = $args[$i];
			} else {
				throw new BlueprintException("Unknown argument {$args[$i]} to Blueprint::make($class)");
			}
		}

		if(!class_exists($class)) {
			throw new BlueprintException("Unknown class $class given to Blueprint::make");
		}

		$blueprint = self::find_blueprint($class);

		return $blueprint->create($set, $name, $commit);
	}

	private static $blueprints = array();

	private static function find_blueprint($class) {

		if(isset(Blueprint::$blueprints[$class])) return Blueprint::$blueprints[$class];

		$dir = realpath(dirname(__FILE__) . "/" . Blueprint::$blueprints_dir) . "/" ;
		if(file_exists("$dir/$class.json")) {
			Blueprint::$blueprints[$class] = new Blueprint($class, "$dir/$class.json");
			return Blueprint::$blueprints[$class];
		} else {
			throw new BlueprintException("Couldn't find blueprint: $dir/$class.json");
		}
	}

	private $class_name, $data;

	private function create($set, $name, $commit) {
		if(!isset($this->data[$name])) {
			throw new BlueprintException("Unknow blueprint '$name' for {$this->class_name}");
		}
		$data = array_merge($this->data[$name], $set);

		$attr = array(
			'sn' => Blueprint::$sn++
		);

		$class_name = $this->class_name;

		$obj = new $class_name;

		foreach($data as $key => $value) {
			if(is_string($value)) {
				$obj->$key = preg_replace_callback("/#\{(.+?)\}/", function($matches) use ($attr, $data) {
					$k = $matches[1];
					$replace = false;
					if(isset($attr[$k])) {
						$replace = $attr[$k];
					} else {
						$replace = $obj->$k;
					}
					return $replace;
				}, $value);
			} else if(is_array($value)) {
				$blueprint = "default";
				$class = $key;
				$values = array();
				if(isset($value['blueprint'])) {
					$blueprint = $value['blueprint'];
					unset($value['blueprint']);
				}

				if(isset($value['class'])) {
					$class = $value['class'];
					unset($value['class']);
				}

				if(isset($value['values'])) {
					$values = $value['values'];
					unset($value['values']);
				}

				$values = array_merge($values, $value);
				$obj->$key = Blueprint::make($class, $blueprint, $values, true);
			} else {
				$obj->$key = $value;
			}
		}

		if($commit) $obj->commit();

		return $obj;
	}

	private function __construct($class_name, $path) {
		$this->class_name = $class_name;

		$contents = file_get_contents($path);

		$contents = preg_replace('/^(\s+)(\w+):/m', '$1"$2":', $contents);

		$this->data = json_decode($contents, true);

		if($this->data === NULL) {
			// Define the errors.
			$constants = get_defined_constants(true);
			$json_errors = array();
			foreach ($constants["json"] as $name => $value) {
				if (!strncmp($name, "JSON_ERROR_", 11)) {
					$json_errors[$value] = $name;
				}
			}

			throw new BlueprintException("JSON parse error: {$json_errors[json_last_error()]}.\nParsed source: \n" . var_export($contents, true));
		}
	}
}

class BlueprintException extends Exception {}
