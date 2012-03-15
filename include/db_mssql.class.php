<?php

/**
 * A simple class providing MS SQL database access to PHP applications in an OOP
 * style.
 *
 * @author Catalin Ciocov
 * @license http://www.opensource.org/licenses/mit-license.php
 */

class db_mssql {

	/**
	 * The MS SQL link identifier.
	 * @var resource
	 */
	public $link_id;

	/**
	 * Flag that indicates if the server connection and database selection
	 * are OK.
	 * @var boolean
	 */
	public $connected = false;

	/**
	 * Internal cache for table structure.
	 * @var array { table name => table structure, ... }
	 */
	private $_cache = array();


	/**
	 * Constructor. Connect to the database server using the supplied info.
	 * @param string $dbname
	 * @param string $dbuser
	 * @param string $dbpass
	 * @param string $dbhost
	 */
	public function __construct($dbname, $dbuser, $dbpass, $dbhost) {
		$this->connected = $this->_connect($dbname, $dbuser, $dbpass, $dbhost);
	}

	/**
	 * Destructor. Close the connection.
	 */
	public function __destruct() {
		$this->close();
	}

	/**
	 * Connect to the server and select the appropriate database.
	 * @param string $dbname
	 * @param string $dbuser
	 * @param string $dbpass
	 * @param string $dbhost
	 * @return boolean
	 */
	private function _connect($dbname, $dbuser, $dbpass, $dbhost) {
		$this->link_id = @mssql_connect($dbhost, $dbuser, $dbpass);
		if ($this->link_id) {
			return @mssql_select_db($dbname, $this->link_id);
		}
		return false;
	}

	/**
	 * Close server connection.
	 */
	public function close() {
		$this->connected = false;
		return @mssql_close($this->link_id);
	}

	/**
	 * Get the number of rows/records affected by the last DELETE, INSERT
	 * or UPDATE operation.
	 * @return int
	 */
	public function rows_affected() {
		return @mssql_rows_affected($this->link_id);
	}

	/**
	 * Get the last ID generated for an auto-increment field.
	 * @return int
	 */
	//public function last_id() {
	//	return @mysql_insert_id($this->link_id);
	//}

	/**
	 * Describe a table (its structure). This is used by add_record() and
	 * update_record() and is optimized for multiple use by an internal
	 * cache.
	 * @param string $tbname The name of the table.
	 * @return array
	 */
	//public function describe($tbname) {
	//	if (!isset($this->_cache[$tbname])) {
	//		$this->_cache[$tbname] = array();
	//		$q = $this->query("SHOW COLUMNS FROM $tbname");
	//		while (is_array($res = $q->getrow())) {
	//			$this->_cache[$tbname][] = $res;
	//		}
	//	}
	//	return $this->_cache[$tbname];
	//}

	/**
	 * Get the last error produced.
	 */
	public function error() {
		return @mssql_get_last_message();
	}

	/**
	 * Execute a query.
	 * @param string $qstr The query string.
	 * @return object A db_mysql_query object.
	 */
	public function query($qstr) {
		return new db_mssql_query($this, $qstr);
	}

	/**
	 * Get the value of 1 field from 1 record.
	 * @param string $field The name of the field.
	 * @param string $tbname The name of the table.
	 * @param string $where_str A WHERE string to select the desired record.
	 * @return The value of the field or false if no record found.
	 */
	public function get_field($field, $tbname, $where_str = '') {
		$qstr = "SELECT TOP 1 $field FROM $tbname" . (!empty($where_str) ? " WHERE $where_str" : '');
		$q = $this->query($qstr);
		if (is_array($res = $q->getrow())) {
			return array_shift($res);
		}
		return false;
	}

	/**
	 * Get the value of multiple fields from 1 record.
	 * @param string $fields The name of the fields separated by commas.
	 * @param string $tbname The name of the table.
	 * @param string $where_str A WHERE string to select the desired record.
	 * @return An array { field => value, ... } or false if no record found.
	 */
	public function get_fields($fields, $tbname, $where_str = '') {
		$qstr = "SELECT TOP 1 $fields FROM $tbname" . (!empty($where_str) ? " WHERE $where_str" : '');
		$q = $this->query($qstr);
		if (is_array($res = $q->getrow())) {
			return $res;
		}
		return false;
	}

	/**
	 * Get the value of multiple fields from multiple records.
	 * @param string $fields The name of the fields separated by commas.
	 * @param string $tbname The name of the table.
	 * @param string $where_str A WHERE string to select the desired records.
	 * @param string $key_field The record field to use as key in the return array.
	 * @param int $n The maximum number of records to select.
	 * @param callback $callback A function to process each retrieved record before adding it to the returned result.
	 * @return An array [{ field => value, ... }, ...] or false if no records found.
	 */
	public function get_records($fields, $tbname, $where_str = '', $key_field = '', $n = 0, $callback = false) {
		$qstr = 'SELECT ' . ($n > 0 ? "TOP $n " : '') . "$fields FROM $tbname" . (!empty($where_str) ? " WHERE $where_str" : '');
		$q = $this->query($qstr);
		if ($q->numrows() > 0) {
			$results = array();
			while (is_array($res = $q->getrow())) {
				if (!empty($key_field)) {
					$results[$res[$key_field]] = ($callback === false ? $res : call_user_func($callback, $res));
				}
				else {
					$results[] = ($callback === false ? $res : call_user_func($callback, $res));
				}
			}
			return $results;
		}
		return false;
	}

	/**
	 * Add a record to a table. This function looks at the table structure
	 * to determine what fields are required and picks them up from the
	 * supplied array.
	 * @param array $hash Where to look for field values { field => value, ... }
	 * @param string $tbname The table where to add the new record.
	 * @return The result of last_id() or false if the operation fails.
	 */
	//public function add_record($hash, $tbname) {
	//	$fields = array();
	//	$values = array();
	//	foreach ($this->describe($tbname) as $column) {
	//		if (isset($hash[$column['Field']])) {
	//			$fields[] = $column['Field'];
	//			// remove double quotes '' at the start and end of each value:
	//			if ($hash[$column['Field']] != '') {
	//				$values[] = preg_replace('/^\'\'|\'\'$/', '', "'" . $hash[$column['Field']] . "'");
	//			}
	//			else {
	//				$values[] = "'" . $hash[$column['Field']] . "'";
	//			}
	//		}
	//	}
	//	$fields_str = implode(', ', $fields);
	//	$values_str = implode(', ', $values);
	//	$q = $this->query("INSERT INTO $tbname ($fields_str) VALUES ($values_str)");
	//	if ($q) {
	//		return $this->last_id();
	//	}
	//	return false;
	//}

	/**
	 * Update a record in a table. This function looks at the table
	 * structure to determine what fields can be updated and updates only
	 * those present in the supplied array.
	 * @param array $hash Where to look for field values { field => value, ... }
	 * @param string $tbname The table where to add the new record.
	 * @param string $where_str A WHERE string to select the record that needs to be updated.
	 * @return boolean
	 */
	//public function update_record($hash, $tbname, $where_str) {
	//	$values = array();
	//	foreach ($this->describe($tbname) as $column) {
	//		if (isset($hash[$column['Field']])) {
	//			if ($hash[$column['Field']] != '') {
	//				$values[] = "$column[Field] = " . preg_replace('/^\'\'|\'\'$/', '', "'" . $hash[$column['Field']] . "'");
	//			}
	//			else {
	//				$values[] = "$column[Field] = " . "'" . $hash[$column['Field']] . "'";
	//			}
	//		}
	//	}
	//	$values_str = implode(', ', $values);
	//	$q = $this->query("UPDATE $tbname SET $values_str WHERE $where_str");
	//	if ($q) {
	//		return true;
	//	}
	//	return false;
	//}
}


class db_mssql_query {

	/**
	 * Internal reference to the database object associated with this query.
	 * @var object
	 */
	private $_db;

	/**
	 * The result resource (returned by mysql_query()).
	 * @var resource
	 */
	private $_result;


	/**
	 * Constructor. Execute the specified query using the specified database
	 * object.
	 * @param object $db The database object.
	 * @param string $qstr The query string.
	 * @param boolean $strict Flag indicating if an exception should be raised in case of query errors.
	 */
	public function __construct(db_mssql &$db, $qstr, $strict = false) {
		$this->_db = $db;
		$this->_result = @mssql_query($qstr, $this->_db->link_id);
		if (!$this->_result && $strict) {
			throw new Exception("DB ERROR!<br/>\nQUERY IS: $query<br/>\nERROR IS: {$this->error()}<br/>\n");
		}
	}

	/**
	 * Get the next row from the result set.
	 */
	public function getrow() {
		return @mssql_fetch_array($this->_result, MSSQL_ASSOC);
	}

	/**
	 * Get the number of rows that are in the result set.
	 */
	public function numrows() {
		return @mssql_num_rows($this->_result);
	}

	/**
	 * Get the last error produced.
	 */
	public function error() {
		return $this->_db->error();
	}

	/**
	 * Free the resources associated with this query.
	 */
	public function free() {
		return @mssql_free_result($this->_result);
	}
}
