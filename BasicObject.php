<?php

/**
 * {@example BasicObjectExample.php}
 */
abstract class BasicObject {

	protected $_data;
	protected $_old_key = array();
	protected $_exists;

	/*
	 *	[
	 *		'table:field' => [
	 *			value => object
	 *		]
	 *	]
	 */
	protected static $_from_field_cache = array();

	protected static $_enable_cache = false;

	/*
	 * [ 'query' => result ]
	 */
	protected static $_selection_cache = array();
	protected static $_count_cache = array();
	protected static $_sum_cache = array();

	/*
	 * Memcache for caching database structure between requests
	 */
	private static $memcache = null;

	private static $column_ids = array();
	private static $connection_table = array();
	private static $tables = null;
	private static $columns = array();

	public static $output_htmlspecialchars;

	/*
	 * Methods for toggling query caching on and off
	 * Default: Off
	 */
	public static function disable_cache() {
		BasicObject::$_enable_cache = false;
	}

	public static function enable_cache() {
		BasicObject::$_enable_cache = true;
	}

	public static function invalidate_cache() {
		BasicObject::$_from_field_cache = array();
		BasicObject::$_selection_cache = array();
		BasicObject::$_sum_cache = array();
		BasicObject::$_count_cache = array();
	}

	/**
	 * Runs the callback with a output_htmlspecialchars temporary value set
	 * and returns the value that the callback returned
	 */
	public static function with_tmp_htmlspecialchars($tmp_value, $callback) {
		$current_value = BasicObject::$output_htmlspecialchars;
		BasicObject::$output_htmlspecialchars = $tmp_value;
		$ret = $callback();
		BasicObject::$output_htmlspecialchars = $current_value;
		return $ret;
	}

	/**
	 * Returns the table name associated with this class.
	 * @return The name of the table this class is associated with.
	 */
//	abstract protected static function table_name();

	/**
	 * Returns the table name associated with this class.
	 * @return The name of the table this class is associated with.
	 */
	private static function id_name($class_name = null){
		$pk = static::primary_key($class_name);
		if(count($pk) < 1) {
			return null;
		}
		if(count($pk) > 1) {
			return $pk;
		}
		return $pk[0];
	}

	private static function primary_key($class_name = null) {
		global $db;
		if(class_exists($class_name) && is_subclass_of($class_name, 'BasicObject')){
			$table_name = $class_name::table_name();
		} elseif($class_name == null) {
			$table_name = static::table_name();
		} else {
			$table_name = $class_name;
		}
		if(!array_key_exists($table_name, BasicObject::$column_ids)){
			$stmt = $db->prepare("
				SELECT
					`COLUMN_NAME`
				FROM
					`information_schema`.`key_column_usage` join
					`information_schema`.`table_constraints` USING (`CONSTRAINT_NAME`, `CONSTRAINT_SCHEMA`, `TABLE_NAME`)
				WHERE
					`table_constraints`.`CONSTRAINT_TYPE` = 'PRIMARY KEY' AND
					`table_constraints`.`CONSTRAINT_SCHEMA` = ? AND
					`table_constraints`.`TABLE_NAME` = ?"
			);
			$db_name = self::get_database_name();
			$stmt->bind_param('ss', $db_name, $table_name);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($index);

			BasicObject::$column_ids[$table_name] = array();
			while($stmt->fetch()) {
				BasicObject::$column_ids[$table_name][] = $index;
			}
			static::store_column_ids();
			$stmt->close();
		}

		return BasicObject::$column_ids[$table_name];
	}

	/**
	 * Enables structure cache using the provided Memcache object
	 * The memcache instance must be connected
	 */
	public static function enable_structure_cache($memcache) {
		BasicObject::$memcache = $memcache;

		$stored = BasicObject::$memcache->get("column_ids");
		if($stored) BasicObject::$column_ids = unserialize($stored);

		$stored = BasicObject::$memcache->get("connection_table");
		if($stored) BasicObject::$connection_table = unserialize($stored);

		$stored = BasicObject::$memcache->get("tables");
		if($stored) BasicObject::$tables = unserialize($stored);

		$stored = BasicObject::$memcache->get("columns");
		if($stored) BasicObject::$columns = unserialize($stored);
	}

	public static function clear_structure_cache($memcache) {
		$memcache->flush();
	}

	private static function store_column_ids() {
		if(BasicObject::$memcache) {
			BasicObject::$memcache->set("column_ids", serialize(BasicObject::$column_ids), 0, 0); /* no expire */
		}
	}

	private static function store_connection_table() {
		if(BasicObject::$memcache) {
			BasicObject::$memcache->set("connection_table", serialize(BasicObject::$connection_table), 0, 0); /* No expire */
		}
	}

	private static function store_tables() {
		if(BasicObject::$memcache) {
			BasicObject::$memcache->set("tables", serialize(BasicObject::$tables), 0, 0); /* No expire */
		}
	}

	private static function store_columns() {
		if(BasicObject::$memcache) {
			BasicObject::$memcache->set("columns", serialize(BasicObject::$columns), 0, 0); /* No expire */
		}
	}

	private static function unique_identifier($class_name = null) {
		if(class_exists($class_name) && is_subclass_of($class_name, 'BasicObject')){
			$table_name = $class_name::table_name();
		} elseif($class_name == null) {
			$table_name = static::table_name();
		} else {
			$table_name = $class_name;
		}
		$pk = static::primary_key($class_name);
		if(count($pk)==1) {
			return "`$table_name`.`{$pk[0]}`";
		} elseif(empty($pk)) {
			throw new Exception("A table should have a primary key to use BasicObject");
		} else {
			return 'concat(`'.$table_name.'`.`'.implode("`, '¤', `$table_name`.`", $pk).'`)';
		}
	}

	/**
	 * @param $array Assoc array of values to set in this instance
	 * @param $exists Set to true to mark that this is an existing object, and that commits should use update
	 */
	public function __construct($array = null, $exists=false) {
		if($exists && empty($array)) {
			throw new Exception("Can't create new instance marked as existing with an empty data array");
		}
		$columns = self::columns(static::table_name());

		if(is_array($array)) {
			foreach($array as $key => $value) {
				if($key != "id" && !in_array($key, $columns)) {
					unset($array[$key]);
				}
			}
		}

		$this->_exists = $exists;
		$this->_data = $array;
	}

	/**
	 * Creates a duplicate with all the attributes from this instance, but with id set to null, and exist set to false
	 */
	public function duplicate() {
		$dup = clone $this;
		$dup->_exists = false;
		$dup->_data[$this->id_name()]=null;
		return $dup;
	}

	/**
	 * Called after a clone is completed.
	 * Don't do anything, but with this one undefined __call get called instead
	 */
	public function __clone() {

	}

	/**
	 * Returns values in this table or Objects of neighboring tables if there is a foreign key.
	 * @param array Only alowed when accessing other tables. Extra paramaters for selection
	 * see selection() for details.
	 * @returns mixed If the function name is the exact name of a neighboring class, an object or
	 * a list of objects is returned depending on the direction of the foreign key.
	 * Oterwise if there exists a value ($object->value) that has the same name as the name called,
	 * then that value is returned.
	 */
	public function __call($name, $arguments){
		if(class_exists($name) && is_subclass_of($name, 'BasicObject')){
			$other_table = $name::table_name();
			$con = $this->connection($this->table_name(), $other_table);
			if($con) {
				if(isset($arguments[0]) && is_array($arguments[0])){
					$params = $arguments[0];
				} else {
					$params = array();
				}
				if($con['TABLE_NAME'] == $this->table_name()){
					// We know them (single value)
					$ref_name = $con['COLUMN_NAME'];
					return $name::from_id($this->$ref_name);
				} else {
					// They know us (multiple values)
					$params[$con['COLUMN_NAME']] = $this->id;
					return $name::selection($params);
				}
			}
		}
		if(count($arguments) == 0 && $name != 'table_name'){
			try{
				return $this->__get($name);
			} catch(UndefinedMemberException $e) {
			}
		}
		throw new UndefinedFunctionException("Undefined call to function '".__CLASS__."::$name'");
	}

	/**
	 * Returns values in this table or Objects of neighboring tables if there is a foreign key.
	 * Overload this method to define specific behaviours such as denying access and custom
	 * formating.
	 * @returns mixed If there exists a column in the table with the same name the value of the
	 * field is returned.
	 * Otherwise if the property name is the exact name of a neighboring class, an object or
	 * a list of objects is returned depending on the direction of the foreign key.
	 */
	public function __get($name){
		if(!is_bool(BasicObject::$output_htmlspecialchars)) {
			if(defined('HTML_ACCESS') && is_bool(HTML_ACCESS)) {
				BasicObject::$output_htmlspecialchars = HTML_ACCESS;
			} else {
				throw new Exception("Neither BasicObject::\$output_htmlspecialchars nor HTML_ACCESS is a boolean");
			}
		}
		if($this->in_table($name, $this->table_name())){
			if(isset($this->_data) && array_key_exists($name, $this->_data)) {
				$ret = $this->_data[$name];
				if(BasicObject::$output_htmlspecialchars && is_string($ret)) {
					$ret = htmlspecialchars($ret, ENT_QUOTES, 'utf-8');
				}
				return $ret;
			} else {
				return null;
			}
		}
		if(class_exists($name) && is_subclass_of($name, 'BasicObject')){
			return $this->$name(array());
		}
		if($name == 'id'){
			$name = $this->id_name();
			return $this->$name;
		}
		throw new UndefinedMemberException("unknown property '$name'");
	}

	protected function is_protected($name) {
		return false;
	}

	/**
	 * Returns wether a variable in this object is set.
	 * @param string property name
	 * @returns bool Returns True if the value exists an is not null, false otherwise.
	 */
	public function __isset($name) {
		if($name == 'id') return true;

		if(isset($this->_data[$name])) {
			return true;
		}
		try{
			$data = $this->__get($name);
			return isset($data);
		} catch(Exception $e) {
			return false;
		}
	}

	/**
	 * Set this function to return the name of a column to sort by that if '@order' is not specified
	 */
	protected static function default_order() {
		return null;
	}

	/**
	 * Set the value of a field. Use commit() to write to database.
	 */
	public function __set($name, $value) {
		if($this->is_protected($name)){
			$trace = debug_backtrace();
			if(!isset($trace[1]) || $trace[1]['object'] != $trace[0]['object']) {
				throw new Exception("Trying to set protected member '$name' from public scope.");
			}
		}
		if($name == 'id'){
			$name = $this->id_name();
			$this->$name = $value;
		}
		if($this->in_table($name, $this->table_name())) {
			$pk = $this->id_name();
			if($this->_exists && ((is_array($pk) && in_array($name, $pk)) || $pk == $name)) {
				$this->_old_key[$name] = $this->$name;
			}
			$this->_data[$name] = $value;
		} elseif($this->is_table($name)) {
			$connection = self::connection($name, $this->table_name());
			if($connection && $connection['TABLE_NAME'] == $this->table_name()) {
				$name = $connection['COLUMN_NAME'];
			} else {
				$other_id = self::id_name($name);
				if($other_id != 'id' && in_array($other_id, self::columns($this->table_name()))) {
					$name = $other_id;
				} else {
					throw new Exception("No connection from '{$this->table_name()}' to table '$name'");
				}
			}
			$this->$name = $value->id;
		} else {
			throw new Exception("unknown property '$name'");
		}
	}

	private function get_fresh_instance() {
		$id_name = $this->id_name();
		if(!is_array($id_name)) {
			if(!isset($this->id)) {
				throw new Exception("Primary key is not auto increment and is not set.");
			}
			return $this->from_id($this->id);
		}
		$params = array();
		foreach($id_name as $col) {
			$params[$col] = $this->$col;
		}
		$ret = $this->selection($params);
		return array_shift($ret);
	}

	private static function cache_clone(&$obj) {
		if(is_array($obj)) {
			$ret = array();
			foreach($obj as $k=>$v) {
				$ret[$k] = clone $v;
			}
			return $ret;
		} else if($obj !== null) {
			return clone $obj;
		} else {
			return null;
		}
	}

	private static function in_cache(&$cache, $key) {
		return isset($cache) && array_key_exists($key, $cache);
	}

	private static function changed($old, $cur){
		if ( $old != $cur ) return true;
		if ( $old === null && $cur !== null ) return true;
		if ( $old !== null && $cur === null ) return true;
		return false;
	}

	/**
	 * Commits all fields to database. If this object was created with "new Object()" a new row
	 * will be created in the table and this object will atempt to update itself with automagic values.
	 * If the inhereting class wants to do special things on creation, it is best to overload this method
	 * and do them again.
	 */
	public function commit() {
		global $db;
		$id_name = $this->id_name();
		if(isset($this->_exists) && $this->_exists){
			$query = "UPDATE `".$this->table_name()."` SET\n";
			$old_object = $this->get_fresh_instance();
		} else {
			$query = "INSERT INTO `".$this->table_name()."` SET\n";
		}
		$types = '';
		$params = array(&$types);
		$change = false;

		foreach($this->_data as $column => $value){
			if(!isset($old_object) || static::changed($old_object->_data[$column], $value) ) {
				$change = true;

				/* handle null values */
				if ( $value === null ){
					$query .= "	`$column` = NULL,\n";
					continue;
				}

				$params[] = &$this->_data[$column];
				$query .= "	`$column` = ?,\n";
				$types .= 's';
			}
		}

		if(!$change) {
			/**
			 * No change to data means no on change hooks in mysql.
			 */
			return;
		}
		$query = substr($query, 0, -2);

		if(isset($this->_exists) && $this->_exists){
			if(is_array($id_name)) {
				$query .= "\nWHERE ";
				$subquery = '';
				foreach($id_name as $field) {
					$dummy[$field] = $this->$field;
					if(array_key_exists($field, $this->_old_key)) {
						$dummy[$field] = $this->_old_key[$field];
					}
					$subquery .= "`$field` = ? AND ";
					$params[] = &$dummy[$field];
					$types .= 's';
				}
				$query .= substr($subquery, 0, -5);
			} else {
				$query .= "\nWHERE `$id_name` = ?";
				$id = $this->id;
				$types .= 's';
				$params[] = &$id;
			}
		}
		$stmt = $db->prepare($query);
		call_user_func_array(array($stmt, 'bind_param'), $params);
		if(!$stmt->execute()) {
			throw new Exception("Internal error, failed to execute query:\n<pre>$query\n".$stmt->error.'</pre>', $stmt->errno);
		}
		$stmt->close();
		if(!isset($this->_exists) || !$this->_exists){
			$this->_exists = true;
			if($db->insert_id) {
				$object = $this->from_id($db->insert_id);
			} else {
				$object = self::get_fresh_instance();
			}
			$this->_data = $object->_data;
		}

		BasicObject::invalidate_cache();
	}

	/**
	 * Deletes this object from the database and calls unset on this object.
	 */
	public function delete() {
		global $db;
		if(isset($this->_exists) && $this->_exists){
			$types='';
			$params = array(&$types);
			$query = "DELETE FROM ".$this->table_name();
			if(is_array($this->id_name())) {
				$query .= "\nWHERE ";
				$subquery = '';
				foreach($this->id_name() as $field) {
					$subquery .= "`$field` = ? AND ";
					$dummy[$field] = $this->$field;
					$params[] = &$dummy[$field];
					$types .= 's';
				}
				$query .= substr($subquery, 0, -5);
			} else {
				$query .= "\nWHERE `".$this->id_name()."` = ?";
				$id = $this->id;
				$types .= 'i';
				$params[] = &$id;
			}
			$stmt = $db->prepare($query);
			call_user_func_array(array($stmt, 'bind_param'), $params);
			$stmt->execute();
			if($stmt->affected_rows <=  0) {
				$msg = "Failed to delete object: \n";
				if(strlen($stmt->error)>0) {
					$msg.=$stmt->error."\n";
				}
				throw new Exception($msg, $stmt->errno);
			}
			$stmt->close();
		}
		unset($this);
	}

	/**
	 * Returns the Object with object_id = $id.
	 * @param $id Integer The ID of the Object requested.
	 * @return Object The Object specified by $id.
	 */
	public static function from_id($id){
		$id_name = static::id_name();
		return static::from_field($id_name, $id);
	}

	protected static function from_field($field, $value, $type='s'){
		global $db;

		$field_name = $field;
		$table_name = static::table_name();
		$cache_key = "$table_name:$field_name";

		/* test if a cached result exists */
		if(BasicObject::$_enable_cache && self::in_cache(BasicObject::$_from_field_cache[$cache_key], $value)){
			return self::cache_clone(BasicObject::$_from_field_cache[$cache_key][$value]);
		}

		if(!self::in_table($field, $table_name)){
			throw new Exception("No such column '$field' in table '$table_name'");
		}
		$stmt = $db->prepare(
			"SELECT *\n".
			"FROM `".$table_name."`\n".
			"WHERE `".$field."` = ?\n".
			"LIMIT 1"
		);
		$stmt->bind_param($type, $value);
		$stmt->execute();
		$stmt->store_result();
		$fields = $stmt->result_metadata();
		while($field = $fields->fetch_field()){
			$bind_results[$field->name] = &$row[$field->name];
		}
		call_user_func_array(array($stmt, 'bind_result'), $bind_results);
		$object = null;
		if($stmt->fetch()) {
			$object = new static($bind_results, true);
		}
		$stmt->close();

		/* store result in cache */
		if(BasicObject::$_enable_cache){
			if(!isset(BasicObject::$_from_field_cache[$cache_key])) BasicObject::$_from_field_cache[$cache_key] = array();
			BasicObject::$_from_field_cache[$cache_key][$value] = self::cache_clone($object);
		}

		return $object;
	}

	public static function sum($field, $params = array()) {
		global $db;
		$data = static::build_query($params, '*');

		$cache_string = null;
		if(BasicObject::$_enable_cache) {
			$cache_string = implode(";", $data);
			if(self::in_cache(BasicObject::$_sum_cache,$cache_string)) {
				return BasicObject::$_sum_cache[$cache_string];
			}
		}

		$query = array_shift($data);
		$allowed_symbols=array('*', '+', '/', '-', );
		if(is_array($field)) {
			$f = array_shift($field);
			if(!self::in_table($f, static::table_name())){
				throw new Exception("No such column '$field' in table '".static::table_name()."'");
			}
			$exp = "`$f`";
			while($f = array_shift($field)) {
				if(!in_array($f, $allowed_symbols)) {
					throw new Exception("Non allowed symbol '$f' in expression");
				}
				$exp .= " $f ";
				if(!($f = array_shift($field))) {
					throw new Exception("Mismatched expression");
				}
				if(!self::in_table($f, static::table_name())){
					throw new Exception("No such column '$f' in table '".static::table_name()."'");
				}
				$exp .= "`$f`";
			}
			$query = "SELECT SUM($exp) FROM ($query) q";
		} else {
			if(!self::in_table($field, static::table_name())){
				throw new Exception("No such column '$field' in table '".static::table_name()."'");
			}
			$query = "SELECT SUM(`$field`) FROM ($query) q";
		}
		$stmt = $db->prepare($query);
		foreach($data as $key => $value) {
			$data[$key] = &$data[$key];
		}
		if(count($params)!=0) {
			call_user_func_array(array($stmt, 'bind_param'), $data);
		}
		$stmt->execute();
		$stmt->store_result();
		$stmt->bind_result($result);
		$stmt->fetch();
		$stmt->close();

		if(BasicObject::$_enable_cache) {
			BasicObject::$_sum_cache[$cache_string] = $result;
		}

		return $result;
	}

	/**
	 * Returns the number of items matching the conditions.
	 * @param $params Array See selection for structure of $params.
	 * @returns Int the number of items matching the conditions.
	 */
	public static function count($params = array(), $debug = false){
		global $db;
		$data = static::build_query($params, 'count');

		$cache_string = null;
		if(BasicObject::$_enable_cache) {
			$cache_string = implode(";", $data);
			if(self::in_cache(BasicObject::$_count_cache,$cache_string)) {
				return BasicObject::$_count_cache[$cache_string];
			}
		}

		$query = array_shift($data);
		if($debug) {
			echo "<pre>$query</pre>\n";
			var_dump($data);
		}
		$stmt = $db->prepare($query);
		foreach($data as $key => $value) {
			$data[$key] = &$data[$key];
		}
		if(count($data)>1) {
			call_user_func_array(array($stmt, 'bind_param'), $data);
		}
		$stmt->execute();
		$stmt->store_result();
		$stmt->bind_result($result);
		$stmt->fetch();
		$stmt->close();

		if(BasicObject::$_enable_cache) {
			BasicObject::$_count_cache[$cache_string] = $result;
		}

		return $result;
	}

	/**
	 * Returns a list of Objects of this class where the conditions
	 * specified in $params are true on all objects.
	 * @param $params Array An array of conditions.
	 * If $params is empty, all objects will be returned.
	 * $params is structured as:
	 *   array(
	 *     '<<column>>:<<operator>>' => <<value>>,
	 *     array(
	 *       'column' => <<column>>,
	 *       'value' => <<value>>
	 *     ),
	 *     '@manual_query' => <<valid where clause>>,
	 *     [...,]
	 *     // special clauses
	 *     '@or' => array([<params>]),
	 *     '@and' => array([<params>]),
	 *     '@order' => array(<<order-column>> [, <<order-column>> ...]) | <<order-column>>,
	 *     '@limit' => array(<<limit>> [, <<limit>>]),
	 *     '@join[:<<join-type>>]' => array(
	 *                '<<table>>[:<<operator>>]' => <<condition>> ,
	 *								...
	 *			)
	 *   )
	 *
	 * Joins: <<join-type>>: The join type (eg. LEFT,RIGHT OUTER etc)
	 * This produces the join " <<join-type>> JOIN <<table>> <<operator>> <<condition>>
	 * Operator can be 'on' or 'using'
	 *
	 * @returns Array An array of Objects.
	 */
	public static function selection($params = array(), $debug=false){
		global $db;
		$data = self::build_query($params, '*');

		$cache_string = null;
		if(BasicObject::$_enable_cache) {
			$cache_string = implode(";", $data);
			if(self::in_cache(BasicObject::$_selection_cache,$cache_string)) {
				return self::cache_clone( BasicObject::$_selection_cache[$cache_string]);
			}
		}

		$query = array_shift($data);
		$stmt = $db->prepare($query);
		if(!$stmt) {
			throw new Exception("BasicObject: error parsing query: $query\n $db->error");
		}
		foreach($data as $key => $value) {
			$data[$key] = &$data[$key];
		}
		if(count($data)>1) {
			call_user_func_array(array($stmt, 'bind_param'), $data);
		}
		if($debug) {
			echo "<pre>$query</pre>";
			var_dump($data);
		}
		if($stmt->execute() === false) {
			throw new Exception("BasicObject: error while executing query: $db->error", $db->errno);
		}
		if($stmt->store_result() === false) {
			throw new Exception("BasicObject: error in store_result: $db->error", $db->errno);
		}
		$fields = $stmt->result_metadata();
		if($fields === false) {
			throw new Exception("BasicObject: error while fetching result metadata: $db->error", $db->errno);
		}
		while($field = $fields->fetch_field()){
			$result[$field->name] = &$row[$field->name];
		}
		call_user_func_array(array($stmt, 'bind_result'), $result);

		$ret = array();
		while($stmt->fetch()){
			// fix result so they don't all referencde the same stuff.
			$tmp = array();
			foreach($result as $key => $value){
				$tmp[$key] = $value;
			}
			$ret[] = new static($tmp, true);
		}
		$stmt->close();

		if(BasicObject::$_enable_cache) {
			BasicObject::$_selection_cache[$cache_string] = self::cache_clone($ret);
		}

		return $ret;
	}

	private static function build_query($params, $select){
		$table_name = static::table_name();
		$id_name = static::id_name();
		$joins = array();
		$wheres = '';
		$order = array();
		$user_params = array();
		$types = self::handle_params($params, $joins, $wheres, $order, $table_name, $limit, $user_params, 'AND');

		if(count($order) == 0 && strpos(strtolower($wheres),'order by') === false) {
			// Set default order
			if(static::default_order() != null)
				self::handle_order(static::default_order(), $joins, $order, $table_name, self::columns($table_name));
		}

		$query = "SELECT ";
		switch($select) {
			case '*':
				$query .= "`".$table_name."`.*\n";
				$group = "\nGROUP BY ".static::unique_identifier();
				break;
			case 'count':
				$query .= "COUNT(DISTINCT(".static::unique_identifier().")) AS `count`\n";
				$group = "";
				break;
		}
		$query .=
			"FROM\n".
			"	`".$table_name."`";
		foreach($joins as $table => $join){
			$type = isset($join['type']) ? $join['type'] : "";
			$query .= " $type JOIN\n";

			if(isset($join['using'])){
				$query .= "	`".$table."` USING (`".$join['using']."`)";
			} else {
				$query .= "	`".$table."` ON (".$join['on'].")";
			}
		}
		$query .= "\n";
		$result = array();
		$prepare_full_params = array(&$query, $types); // note the & in &$query making the changes to $query in subsequent lines matter
		if(strlen($wheres) > 0){
			$wheres = substr($wheres, 0, -5);
			$query .= "WHERE\n$wheres";
			foreach($user_params as $user_param){
				$prepare_full_params[] = $user_param;
			}
		}
		$query .= $group;
		if(count($order) > 0){
			$query .= "\nORDER BY\n	";
			$query .= implode(",\n	", $order);
		}
		if(isset($limit)){
			$query .= "\n$limit";
		}
		return $prepare_full_params;
	}

	private static function handle_params($params, &$joins, &$wheres, &$order, &$table_name, &$limit, &$user_params, $glue = 'AND') {
		$columns = self::columns($table_name);
		$types = '';
		foreach($params as $column => $value){
			// give a possibility to have multiple params with the same column.
			if(is_int($column) && is_array($value) && isset($value['column']) && isset($value['value'])){
				$column = $value['column'];
				$value = $value['value'];
			}
			if($column[0] == '@'){
				$column_split = explode(':', $column);
				$column = $column_split[0];
				// special parameter
				switch($column){
					case '@custom_order':
						$order[] = $value;
						break;
					case '@order':
						self::handle_order($value, $joins, $order, $table_name, $columns);
						break;
					case '@limit':
						if(is_numeric($value)){
							$value = array($value);
						}
						if(!is_array($value) || count($value) > 2){
							throw new Exception("Expected array or number for limit clause");
						}
						foreach($value as $v){
							if(!is_numeric($v) && $v>=0){
								throw new Exception("Limit must be numeric clauses only");
							}
						}
						$limit = "LIMIT ".$value[0];
						if(count($value) == 2){
							$limit .= ', '.$value[1];
						}
						break;
					case '@manual_query':
						if(is_array($value)){
							$wheres .= "	({$value['where']}) $glue\n";
							$types .= $value['types'];
							$user_params = array_merge($user_params, $value['params']);
						} else {
							$wheres .= "	($value) $glue\n";
						}
						break;
					case '@or':
						$where = '';
						$types .= self::handle_params($value, $joins, $where, $order, $table_name, $limit, $user_params, 'OR');
						$wheres .= "(\n".substr($where, 0, -4)."\n) $glue\n";
						break;
					case '@and':
						$where = '';
						$types .= self::handle_params($value, $joins, $where, $order, $table_name, $limit, $user_params, 'AND');
						$wheres .= "(\n".substr($where, 0, -5)."\n) $glue\n";
						break;
					case '@join':

						if(count($column_split) > 1) {
							$join_type = $column_split[1];
						} else {
							$join_type = null;
						}

						if(!is_array($value)) {
							throw new Exception("Join must be array");
						}
						foreach($value as $table => $condition) {
							$table = explode(':', $table);
							if(count($table) > 1) {
								$operator = strtolower($table[1]);
								if(! ($operator == "on" || $operator == "using") ) {
									throw new Exception("Join operator must be 'on' or 'using'");
								}
							} else {
								$operator = "on";
							}
							$table = $table[0];

							$join = array(
								$operator => $condition,
								'to' => static::table_name()
							);
							if($join_type != null) {
								$join['type'] = $join_type;
							}

							$joins[$table] = $join;
						}
						break;
					default:
						throw new Exception("No such operator '".substr($column,1)."' (value '$value')");
				}
			} else {
				$where=array();
				// handle operator
				$column = explode(':', $column);
				if(count($column) > 1) {
					// Has operator
					$where['operator'] = self::operator($column[1]);
				} else {
					// default operator
					$where['operator'] = '=';
				}
				$column = $column[0];

				$function=NULL;

				//Handle functions:
				if(preg_match("/(.*)\((.*)\)/",$column, $matches)) {
					$function = $matches[1];
					$column = $matches[2];
				}

				// handle column
				$path = explode('.', $column);
				if(count($path)>1){
					$where['column'] = '`'.self::fix_join($path, $joins, $columns, $table_name).'`';
				} else {
					if(!self::in_table($column, $table_name)){
						throw new Exception("No such column '$column' in table '$table_name' (value '$value')");
					}
					$where['column'] = '`'.$table_name.'`.`'.$column.'`';
				}

				if($function) {
					$where['column'] = "$function({$where['column']})";
				}

				if($where['operator'] == 'in') {
					$wheres .= "	{$where['column']} IN (";
					if(!is_array($value)){
						throw new Exception("Operator 'in' should be coupled with an array of values.");
					}
					foreach($value as $v){
						$types .= 's';
						$wheres .= '?, ';
						$user_params[] = $v;
					}
					$wheres = substr($wheres, 0, -2);
					$wheres .= ") $glue\n";
				} elseif($where['operator'] == 'null') {
					$wheres .= "	".$where["column"]." IS NULL $glue\n";
				} elseif($where['operator'] == 'not_null') {
					$wheres .= "	".$where["column"]." IS NOT NULL $glue\n";
				} else {
					$user_params[] = $value;
					$wheres .= "	".$where["column"]." ".$where['operator']." ? ".$glue."\n";
					$types.='s';
				}
			}
		}
		return $types;
	}

	/**
	 * Update or create an object from an array (often postdata)
	 * By default this method performs commit() on the object before it is returned, but that
	 * can be turned of (see @param $options)
	 *
	 * @param $array An assoc array (for example from postdata) with $field_name=>$value.
	 *						If ["id"] or [id_name] is set the model is marked as existing,
	 *						otherwise it is treated as a new object.
	 *
	 *						Note: To use this method with checkboxes a hidden field with the same name and value
	 *						0 must exist, otherwise the value will not be changed. This is because the function is
	 *						build to allow partial updates of a model and loads any missing data from the database.
	 *
	 * @param $options An array with options
	 *						empty_to_null: Set to true to replace all instances of "" with null. (default true)
	 *						commit: Set to false to not perform commit() (default false)
	 */
	public static function update_attributes($array, $options=array()) {
		$defaults = array(
			'empty_to_null' => true,
			'commit' => false,
		);
		$options = array_merge($defaults, $options);
		if(isset($options["empty_to_null"]) && $options["empty_to_null"] == true) {
			foreach($array as $k => $v) {
				if($v == "")
					$array[$k] = null;
			}
		}

		$obj = new static($array);

		//Change [id] to [id_name] if [id] is set but id_name()!='id'
		if($obj->id_name() != "id"
			&& isset($obj->_data['id'])
			&& !is_null($obj->_data['id'])
			&& !empty($obj->_data['id'])
			&& !isset($obj->_data[$obj->id_name()])) {
				$obj->_data[$obj->id_name()] = $obj->_data['id'];
				unset($obj->_data['id']);
		} else if($obj->id_name() != "id") {
			//Prevent errors where the id field has another name and ['id'] is null
			unset($obj->_data['id']);
		}

		$id = $obj->id;

		if($id!=null && $id!="") {
			$old_obj = static::from_id($id);
			$obj->_data = array_merge($old_obj->_data,$obj->_data);
			$obj->_exists = true; //Mark as existing
		}

		if(!isset($options["commit"]) || $options["commit"] == true) {
			$obj->commit();
		}

		return $obj;
	}

	private static function columns($table){
		global $db;
		if(!isset(BasicObject::$columns[$table])){
			if(!self::is_table($table)){
				throw new Exception("No such table '$table'");
			}
			$column[$table] = array();
			$stmt = $db->prepare(
				"SELECT `COLUMN_NAME`\n".
				"FROM `information_schema`.`COLUMNS`\n".
				"WHERE\n".
				"	`TABLE_SCHEMA` = ? AND\n".
				"	`table_name` = ?"
			);
			$db_name = self::get_database_name();
			$stmt->bind_param('ss', $db_name, $table);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($column);
			while($stmt->fetch()){
				BasicObject::$columns[$table][] = $column;
			}
			$stmt->close();
			BasicObject::store_columns();
		}
		return BasicObject::$columns[$table];
	}

	private static function operator($expr){
		switch($expr){
			case "=":
			case "!=":
			case "<=":
			case ">=":
			case "<":
			case ">":
			case "regexp":
			case "like":
			case "in":
			case "null":
			case "not_null":
				return $expr;
			default:
				throw new Exception("No such operator '$expr'");
		}
	}

	private static function is_table($table){
		global $db;
		if(!isset(BasicObject::$tables)){
			BasicObject::$tables = array();

			$db_name = static::get_database_name();
			$stmt = $db->prepare("
				SELECT `table_name`
				FROM `information_schema`.`tables`
				WHERE `table_schema` = ?
			");
			$stmt->bind_param('s', $db_name);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($table_);
			while($stmt->fetch()){
				BasicObject::$tables[] = strtolower($table_);
			}
			$stmt->close();
			BasicObject::store_tables();
		}
		return in_array(strtolower($table), BasicObject::$tables);
	}

	private static function fix_join($path, &$joins, $parent_columns, $parent){
		$first = array_shift($path);

		if(class_exists($first) && is_subclass_of($first, 'BasicObject')){
			$first = $first::table_name();
		}

		if(!self::is_table($first)){
			throw new Exception("No such table '$first'");
		}

		$columns = self::columns($first);

		if(!isset($joins[$first])) {
			$connection = self::connection($first, $parent);
			if($connection){
				$joins[$first] = array(
					'to' => $parent,
					'on' => "`{$connection['TABLE_NAME']}`.`{$connection['COLUMN_NAME']}` = `{$connection['REFERENCED_TABLE_NAME']}`.`{$connection['REFERENCED_COLUMN_NAME']}`"
				);
			} else {
				$parent_id = self::id_name($parent);
				$first_id = self::id_name($first);
				if(in_array($first_id, $parent_columns)){
					$joins[$first] = array(
						"to" => $parent,
						"on" => "`$parent`.`$first_id` = `$first`.`$first_id`");
				} elseif(in_array($parent_id, $columns)) {
					$joins[$first] = array(
						"to" => $parent,
						"on" => "`$parent`.`$parent_id` = `$first`.`$parent_id`");
				} else {
					throw new Exception("No connection from '$parent' to table '$first'");
				}
			}
		}

		if(count($path) == 1) {
			$key = array_shift($path);
			if(!in_array($key, $columns)){
				throw new Exception("No such column '$key' in table '$first'");
			}
			return $first.'`.`'.$key;
		} else {
			return self::fix_join($path, $joins, $columns, $first);
		}
	}

	private static function in_table($column, $table){
		return in_array($column, self::columns($table));
	}

	/**
	 * Return only the first match of the given query
	 * Takes the same options as selection
	 */
	public static function first($params = array()) {
		$params['@limit']=1;
		$sel = static::selection($params);
		if(isset($sel[0])) {
			return $sel[0];
		} else {
			return null;
		}
	}

	/**
	 * Returns the only object that matches the given query
	 * If there is more than one match an exception is thrown
	 */
	public static function one($params = array()) {
		$sel = static::selection($params);
		if(count($sel) <= 1) {
			return isset($sel[0]) ? $sel[0] : null;
		} else {
			throw new Exception("Expected at most one match for query ".print_r($params, true)." but got ".count($sel));
		}
	}

	private static function connection($table1, $table2) {
		global $db;
		if(strcmp($table1, $table2) < 0){
			$tmp = $table1;
			$table1 = $table2;
			$table2 = $tmp;
		}
		if(!isset(BasicObject::$connection_table[$table1]) || !isset(BasicObject::$connection_table[$table1][$table2])){
			BasicObject::$connection_table[$table1][$table2] = array();
			$stmt = $db->prepare("
				SELECT
					`key_column_usage`.`TABLE_NAME`,
					`COLUMN_NAME`,
					`REFERENCED_TABLE_NAME`,
					`REFERENCED_COLUMN_NAME`
				FROM
					`information_schema`.`table_constraints` join
					`information_schema`.`key_column_usage` using (`CONSTRAINT_NAME`, `CONSTRAINT_SCHEMA`)
				WHERE
					`constraint_type` = 'FOREIGN KEY' and
					`table_constraints`.`table_schema` = ? AND
					(
						(
							`key_column_usage`.`TABLE_NAME` = ? AND
							`REFERENCED_TABLE_NAME` = ?
						) OR (
							`key_column_usage`.`TABLE_NAME` = ? AND
							`REFERENCED_TABLE_NAME` = ?
						)
					)
					");
			$db_name = self::get_database_name();
			$stmt->bind_param('sssss', $db_name, $table1, $table2, $table2, $table1);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result(
				BasicObject::$connection_table[$table1][$table2]['TABLE_NAME'],
				BasicObject::$connection_table[$table1][$table2]['COLUMN_NAME'],
				BasicObject::$connection_table[$table1][$table2]['REFERENCED_TABLE_NAME'],
				BasicObject::$connection_table[$table1][$table2]['REFERENCED_COLUMN_NAME']
			);
			if(!$stmt->fetch()){
				BasicObject::$connection_table[$table1][$table2] = false;
			} else if($stmt->num_rows > 1) {
				throw new Exception("Ambigious database, can't tell which relation between $table1 and $table2 to use. Remove one relation or override __get.");
			}
			$stmt->close();

			BasicObject::store_connection_table();
		}
		return BasicObject::$connection_table[$table1][$table2];
	}

	private static function get_database_name() {
		global $db;
		static $db_name = null;
		if($db_name === null) {
			$stmt = $db->prepare("SELECT DATABASE()");
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($db_name);
			$stmt->fetch();
			$stmt->close();
		}
		return $db_name;
	}

	/**
	 * Helper method for handling @order and default_order
	 */
	private static function handle_order($value,&$joins, &$order, &$table_name, $columns) {
		if(!is_array($value)){
			$value = array($value);
		}
		foreach($value as $o){
			$desc = false;
			if(substr($o,-5) == ':desc'){
				$desc = true;
				$o = substr($o, 0,-5);
			}
			$path = explode('.', $o);
			if(count($path)>1){
				$o = '`'.self::fix_join($path, $joins, $columns, $table_name).'`';
			} elseif(self::in_table($o, $table_name)){
				$o = "`$table_name`.`$o`";
			} else {
				throw new Exception("No such column '$o' in table '$table_name' (value '$value')");
			}
			if($desc){
				$o .= ' DESC';
			}
			$order[] = $o;
		}
	}

	public function __toString() {
		$content = array();
		foreach(self::columns($this->table_name()) as $c) {
			$v = $this->$c == null ? "NULL":$this->$c;
			$content[] = $c." => ".$v;
		}
		return get_class($this). "{".implode(", ",$content)."}";
	}
}

class UndefinedMemberException extends Exception{}
class UndefinedFunctionException extends Exception{}
