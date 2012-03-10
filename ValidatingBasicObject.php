<?php
/**
 * This is a variant of basic object that has validations.
 *
 * Validations are added to the model by implementing 
 * protected function validation_hooks() (void)
 * 
 * In this function you can either use predefined validations by calling
 * $this->predefined_validation_name('column_name') (see below) 
 * or by creating your own validations.
 *
 * To indicate an error call $this->add_error($variable_name,$error_msg)
 * Use $variable_name = 'base' to add generic errors.
 * This adds an error to $this->errors[$variable_name].
 *
 * Validations are run automaticly on commit unless specified (argument to commit)
 *
 * ====Predefined validations====
 * The variable $var in the call to all validations are the _name_ of the variable
 * All validations support an option assoc array as a optional last argument.
 * All validations support the option "message" that override the default message.
 * Validation specific options are defined below
 * --------------------------
 *
 * validate_presence_of($var)
 *
 * Validates that $var is set
 * -------------------------
 * validate_numericality_of($var)
 * Validates that $var is a number
 * options:
 *		only_integers: true|false, default: false
 *		allow_null: true|false, default: false
 * -------------------------
 *
 * validate_lenght_of($var)
 *
 * Validates the lenght of $var 
 * If no options are set no check is made
 * If more than one options are set, multiple checks will be made
 * options:
 *		is: Lenght must be exactly this value	
 *		minimum: Lenght must be at least this value
 *		maximum: Lenght must be at most this value
 * ------------------------
 *
 * validate_in_range($var)
 * Validates that the variable is in the range given
 * If no options are set no check is made
 * If more than one options are set, multiple checks will be made
 * options:
 *		minimum: Smallest allowed value
 *		maximum: Largest allowed value
 *-------------------------
 *
 * validate_format_of($var,$format)
 *
 * Validates the format of $var
 * The second option is a regular expression to match
 * options:
 *		allow_null: Consider null to be a valid (default: false)
 * ------------------------
 *
 * validate_date($var)
 *
 * Validates that $var is a date
 * options:
 *		allow_null: Consider null to be a valid date (default: false)
 * ------------------------
 *
 * validate_equal_to($var,$val)
 *
 * Validates that the value of $var matches $val
 * options:
 * 	not_equal: Set to true to instead validate not equal
 * ==================================
 */
class ValidatingBasicObject extends BasicObject {
	public $errors=array();


	/**
	 * Runs validation hooks. Returns true if this instance validates
	 * All errors are filled into $errors
	 */
	public function validate() {
		$this->errors = array();
		$this->validation_hooks();
		return !$this->has_errors();
	}

	/**
	 * Does not perform any check, but returns true if
	 * a previous validation found errors
	 */
	public function has_errors() {
		return (count($this->errors) >  0);;
	}

	/**
	 * Override this function and call validations to run validations
	 */
	protected function validation_hooks() {}

	public function add_error($var, $msg) {
		//if(!isset($this->errors[$var])) 
			//$this->errors[$var] = array();
		$this->errors[$var][] = $msg;
	}

	public function commit($validate=true) {
		if($validate && !$this->validate()) {
			throw new ValidationException($this);
		}

		parent::commit();
	}

	public function __clone() {
		parent::__clone();
		$this->errors = array(); //Reset errors
	}

	/***********
	 *	Validators
	 **********/

	/**
	 * Validates that $var is set
	 */
	protected function validate_presence_of($var,$options=array()) {
		if($this->$var == null || $this->$var == "") {
			$message = "måste fyllas i";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		}
	}

	/**
	 * Validates that $var is a number
	 * options:
	 *		only_integers: true|false, default: false
	 *		allow_null: true|false, default: false
	 */
	protected function validate_numericality_of($var,$options=array()) {
		if(isset($options['allow_null']) && $options['allow_null'] && $this->$var == null)
			return;

		if(isset($options['only_integers']) && $options['only_integers']) { 
			if(!is_numeric($this->$var) || preg_match('/\A[+-]?\d+\Z/',$this->$var)!=1) {
				$message = "måste vara ett heltal";
				$this->add_error($var,isset($options['message'])?$options['message']:$message);
			}		
		} else if(!is_numeric($this->$var)){
			$message = "måste vara ett nummer";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		}
	}

	/**
	 * Validates the lenght of $var 
	 * If no options are set no check is made
	 * If more than one options are set, multiple checks will be made
	 * options:
	 *		is: Lenght must be exactly this value	
	 *		minimum: Lenght must be at least this value
	 *		maximum: Lenght must be at most this value
	 */	
	protected function validate_lenght_of($var,$options=array()) {
		if(isset($options['is']) && $options['is'] != strlen($this->$var)) {
			$message = "måste vara exakt {$options['is']} tecken lång";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		} 
		if(isset($options['minimum']) && $options['minimum'] > strlen($this->$var)) {
			$message = "måste vara minst {$options['minimum']} tecken lång";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		} 
		if(isset($options['maximum']) && $options['maximum'] > strlen($this->$var)) {
			$message = "får inte vara längre än {$options['maximum']} tecken";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		} 
	}

	/**
	 * Validates that the variable is in the range given
	 * If no options are set no check is made
	 * If more than one options are set, multiple checks will be made
	 * options:
	 *		minimum: Smallest allowed value
	 *		maximum: Largest allowed value
	 */	
	protected function validate_in_range($var,$options=array()) {
		if(isset($options['minimum']) && $options['minimum'] > $this->$var) {
         $message = "måste vara minst {$options['minimum']}";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		} 
		if(isset($options['maximum']) && $options['maximum'] < $this->$var) {
			$message = "får inte vara större än {$options['maximum']}";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		} 
	}


	/**
	 * Validates the format of $var
	 * The second option is a regular expression to match
	 * options:
	 *		message: The error message to show. Default: "ogiltligt format"	
	 *		allow_null: Consider null to be a valid (default: false)
	 */
	 protected function validate_format_of($var,$format,$options=array()) {
		if(isset($options['allow_null']) && $options['allow_null'] && $this->$var == null)
			return;

		if(preg_match($format,$this->$var) != 1) {
			$this->add_error($var,isset($options['message'])?$options['message']:"ogiltligt format");
		}
	 }

	 /**
	  * Validates that $var is a date
	  * options:
	  *		allow_null: Consider null to be a valid date (default: false)
	  */
	 protected function validate_date($var,$options=array()) {
		if(isset($options['allow_null']) && $options['allow_null'] && $this->$var == null)
			return;

		if(strtotime($this->$var)==false) {
			$this->add_error($var,isset($options['message'])?$options['message']:"måste vara ett datum");
		}
	 }

	/**
	 * Validates that the value of $var matches $val
	 * options:
	 * 	not_equal: Set to true to instead validate not equal
	 */
	protected function validate_equal_to($var,$val,$options=array()) {
		if($this->$var != $val && (
			!isset($options['not_equal']) || !$options['not_equal']
			)) {
			$message = "måste vara $val";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		} else if(isset($options['not_equal']) && $options['not_equal'] &&
			$this->var == $var
			) {
			$message = "får inte vara $val";
			$this->add_error($var,isset($options['message'])?$options['message']:$message);
		}
	}
}

/** 
 * Exception thrown when validations fail
 */
class ValidationException extends Exception {
	public $errors;
	public $object;

	/**
	 * @param BasicObject $object The object that validations failed in
	 */
	public function __construct($object) {
		$this->object = $object;
		$this->errors = $object->errors;
		$this->message = "Validations failed in $object.\n Errors: ".print_r($this->errors,true);
	}

}
