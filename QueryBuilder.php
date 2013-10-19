<?php
namespace QueryBuilder;

class Utils {
	public static colon($key) {
		$ret = explode(':', $key);
		if(count($ret) <= 1) {
			array_push($ret, null);
		}
		return $ret;
	}

	public static function escape($table, $column) {
		return "`$table`.`$column`";
	}
}

class Params {
	private $values = array();
	private $types = '';

	public function add($value, $type='s');
		$this->values[] = $value;
		$this->types .= $type;
	}

	public function add_array(array $values, $types=null);
		$this->values = array_merge($this->values, $values);
		if(empty($types)) {
			$this->types .= str_pad('', count($values), 's');
		} else {
			$this->types .= $types;
		}
	}

	public function values() {
		return $this->values;
	}

	public function types() {
		return $this->types;
	}
}

class Term {
	private $column;
	private $comparator;
	private $value;

	const COMPARATORS = array(
		'=' => 'VALUE',
		'>=' => 'VALUE',
		'<=' => 'VALUE',
		'!=' => 'VALUE',
		'<>' => 'VALUE',
		'>'  => 'VALUE',
		'<'  => 'VALUE',
		'regexp' => 'VALUE',
		'like' => 'VALUE',
		'null' => 'IS NULL',
		'not_null' => 'IS NOT NULL',
		'in' => 'ARRAY',
		'column' => 'COLUMN',
	);

	public function __construct($table, $column, $value, Params $param_list) {
		$tmp = Utils::colon($column);
		$this->column = Utils::escape($table, $tmp[0]);
		$this->comparator = $tmp[1] ?: '=';
		switch(COMPARATORS[$this->comparator]) {
			case 'VALUE':
				$this->value = '?';
				$param_list->add($value);
				break;
			case 'ARRAY':
				$this->value = '('.implode(', ', array_fill(0, count($value), '?')).')';
				$param_list->add_array($value);
				break;
			case 'COLUMN':
				$this->comparator = '=';
				$this->value = Utils::escape($value['table'], $value['column']);
			default:
				$this->value = '';
				$this->comparator = COMPARATORS[$this->comparator];
		}
	}

	public function __toString() {
		return $this->column.$this->comparator.$this->value;
	}
}

class PreloadedSchema {
	private static $instance;
	private $relations;
	private $db;
	public static function instance($db=null) {
		if(!self::$instance) {
			self::$instance = new PreloadedSchema($db);
		}
		return self::$instance;
	}

	private function __construct($db) {
		global $basic_object_relations_file;
		$this->db = $db;
		if(isset($basic_object_relations_file) && file_exists($basic_object_relations_file) {
			$this->relations = json_decode(file_get_contents($basic_object_relations_file), true);
		} else {
			$stmt = $db->query("
				SELECT
					c.TABLE_NAME,
					c.COLUMN_NAME,
					k.COLUMN_NAME IS NOT NULL AS primary_key
				FROM information_schema.COLUMNS AS c
				LEFT JOIN (
					information_schema.KEY_COLUMN_USAGE k
					JOIN information_schema.TABLE_CONSTRAINTS tc USING (
						CONSTRAINT_NAME, CONSTRAINT_CATALOG, CONSTRAINT_SCHEMA, TABLE_NAME
					)
				) ON (
					k.TABLE_SCHEMA = c.TABLE_SCHEMA
					AND k.TABLE_NAME = c.TABLE_NAME
					AND k.COLUMN_NAME = c.COLUMN_NAME
					AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
				)
				WHERE c.TABLE_SCHEMA = DATABASE()
				ORDER BY c.TABLE_NAME, c.COLUMN_NAME
			");
			while($result = $stmt->fetch_assoc()) {
				$this->relations[$result['TABLE_NAME']]['columns'][] = $result['COLUMN_NAME'];
				if($result['primary_key']) {
					$this->relations[$result['TABLE_NAME']]['primary_key'][] = $result['COLUMN_NAME'];
				}
			}

			$stmt = $db->query("
				SELECT
					k.TABLE_NAME,
					REFERENCED_TABLE_NAME,
					k.COLUMN_NAME,
					REFERENCED_COLUMN_NAME
				FROM information_schema.KEY_COLUMN_USAGE as k
				JOIN information_schema.TABLE_CONSTRAINTS USING (CONSTRAINT_NAME, CONSTRAINT_CATALOG, CONSTRAINT_SCHEMA, TABLE_NAME)
				WHERE k.TABLE_SCHEMA = DATABASE()
					AND constraint_type = 'FOREIGN KEY'
				ORDER BY k.TABLE_NAME, REFERENCED_TABLE_NAME, k.COLUMN_NAME
			");
			while($result = $stmt->fetch_assoc()) {
				$condition = 'ON ('.Utils::escape($result['TABLE_NAME'], $result['COLUMN_NAME']).
					" = ".Utils::escape($result['REFERENCED_TABLE_NAME'], $result['REFERENCED_COLUMN_NAME']).')';
				$this->relations[$result['TABLE_NAME']]['foreign'][$result['REFERENCED_TABLE_NAME']][] = $condition;
				$this->relations[$result['REFERENCED_TABLE_NAME']]['foreign'][$result['TABLE_NAME']][] = $condition;
			}
			if(isset($basic_object_relations_file)) {
				file_put_contents($basic_object_relations_file,
					json_encode($this->relations)
				);
			}
		}
	}
}

class From {
	private $tables = array();
	private $db;
	private $base_table;
	private $join_types = array();

	public function __construct($table, Mysqli $db=null) {
		$this->db = $db;
		$this->base_table = $table;
	}

	public function add(array $tables) {
		$old = $base_table;
		while($table = array_shift($tables) {
			if(class_exists($table) && is_subclass_of($table, 'BasicObject')) {
				$table = $table::table_name();
			}
			if(array_key_exists($table, $this->tables)) {
				$old = $table;
				continue;
			}
			$tables[$table] = $this->find_join($old, $table);
			$join_types[$table] = 'INNER';
			$old = $table;
		}
		return $old;
	}

	public function custom_join(array $tables, $join_type) {
		if(!isset($join_type)) {
		} else {
		switch($join_type) {
			case null:
				$join_type = 'INNER';
				break;
			case 'left':
			case 'right':
			case 'inner':
			case 'full':
				$join_type = strtoupper($join_type);
				break;
			default:
				throw new Exception("Unknown join type: $join_type");
		}
		foreach($tables as $table => $condition) {
			$tmp = Utils::colon($table);
			$table = array_shift($tmp);
			$operator = array_shift($tmp);
			if(isset($operator)) {
				$operator = strtoupper($operator);
			} else {
				$operator = 'ON';
			}
			if(! ($operator == "ON" || $operator == "USING") ) {
				throw new Exception("Join operator must be 'on' or 'using'");
			}
			$this->tables[$table] = "$operator ($condition)";
			$this->join_tyoes[$table] = $join_type;
		}
	}

	public static function find_join($first, $second) {
		$schema = PreloadedSchema::instance($this->db);
		return $schema->find_join($first, $second);
	}

	public function __toString() {
		$string = "FROM `{$this->base_table}`"
		foreach($this->tables as $table => $condition) {
			$string .= "\n{$this->join_types[$table]} JOIN `$table` $condition";
		}
		return $string;
	}
}

class Where {
	private $terms;
	private $from;
	private $join;

	public function __construct(From $from, Params $params, $join='AND') {
		$this->from = $from;
		$this->join = $join;
		$this->params = $params;
	}

	public function add_terms(array $params) {
		foreach($params as $key => $param) {
			if($key[0] == '@') {
				$keys = Utils::colon($key);
				$key = array_shift($keys);
				$modifyer = array_shift($keys);
				switch($param) {
					case '@or':
						$builder = new WhereBilder($table_builder, $param_list, 'OR');
						$builder->add_terms($param);
						$this->terms[] = $builder;
						break;
					case '@and':
						$builder = new WhereBilder($table_builder, $param_list, 'AND');
						$builder->add_terms($param);
						$this->terms[] = $builder;
						break;
					case '@manual_query':
						$this->terms[] = $param;
						break;
					default:
						throw new Exception("No such operator '".substr($key,1)."' (value '$value')");
				}
			} else {
				$tables = explode('.', $key);
				$column = array_pop($tables);
				$table = $this->from->add($tables);
				$this->terms[] = new Term($table, $column, $value, $param_list);
			}		
		}
	}

}

class

class SelectBuilder {
	private $select;
	private $from;
	private $where;
	private $params;
	private $group;
	private $order;
	private $limit;

	public function __construct($table, $select_type='TABLE') {
		$this->from = new From($table);
		$this->params = new Params();
		$this->where = new Where($this->from, $this->params);
		$this->group = new ColumnList($this->from);
		$this->order = new ColumnList($this->from);
		$this->limit = new Limit();
		switch($select_type) {
			case 'TABLE':
				$this->select = new ColumnList($this->from);
				break;
			case '
		}
	}

	public function add_terms($params) {
		foreach($params as $key => $value) {
			if($key[0] == '@') {
				$tmp = Utils::colon($key);
				$operator = array_shift($tmp);
				$modifyer = array_shift($tmp);
				switch($operator) {
					case '@order':
						$this->order->add($value);
						break;
					case '@custom_order':
						$this->order->add_custom($value);
						break:
					case '@limit':
						$this->limit = new Limit($value)
					case '@join':
						$this->from->custom_join($value, $modifyer);
						break;
				}
				
			}
		}
	}
}
