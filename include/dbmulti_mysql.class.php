<?php

/**
 * EXPERIMENTAL
 *
 * A simple class providing MySQL database access over a set of nodes/servers to
 * PHP applications in an OOP style. This is very useful when your application
 * is distributed over multiple nodes each with their own local database.
 *
 * A database operation (query) executed over multiple nodes is called a multi
 * query. The class will execute such queries on all online nodes.
 *
 * The class can also execute queries on just one node in which case it will use
 * the first online node that is found, by parsing the node list in the order in
 * which it was generated. In other words, non-multi queries are executed on the
 * online node that was added first to the list of nodes.
 *
 * @author Catalin Ciocov
 * @license http://www.opensource.org/licenses/mit-license.php
 */

require_once('db_mysql.class.php');

class dbmulti_mysql {

	/**
	 * The set of nodes/servers associated with this object.
	 * @var array [{id, dbname, dbuser, dbpass, dbhost}, ...]
	 */
	public $nodes = array();


	/**
	 * Constructor. Optionally set the nodes to be used.
	 * @param array $nodes An array containing node details.
	 */
	public function __construct($nodes = false) {
		if (is_array($nodes)) $this->add_nodes($nodes);
	}

	/**
	 * Add nodes.
	 * @param array $nodes An array of arrays containing node details.
	 */
	public function add_nodes($nodes) {
		foreach ($nodes as $node_info) {
			$idx = count($this->nodes);
			if (!isset($node_info['id'])) $node_info['id'] = $idx;
			$this->nodes[] = array(
				'idx'	=> $idx,
				'id'	=> $node_info['id'],
				'info'	=> $node_info,
				'db'	=> null
			);
		}
	}

	/**
	 * Get all nodes associated with this object.
	 * @return array An array of arrays with node details.
	 */
	public function get_nodes() {
		return $this->nodes;
	}

	/**
	 * Get node details by node ID.
	 * @param string $id The node ID.
	 * @return array
	 */
	public function get_node_by_id($id) {
		foreach ($this->nodes as $node) {
			if ($node['id'] == $id) return $node;
		}
		return false;
	}

	/**
	 * Get node details by node index.
	 * @param int $i The node index in the list of nodes.
	 * @return array
	 */
	public function get_node_by_index($i) {
		if (isset($this->nodes[$i])) return $this->nodes[$i];
		return false;
	}

	/**
	 * Connect to a node specified by its index.
	 * @param int $i The node index in the list of nodes.
	 * @return boolean
	 */
	public function connect_node_by_index($i) {
		if (isset($this->nodes[$i])) {
			if (!$this->nodes[$i]['db']) {
				$info = $this->nodes[$i]['info'];
				$this->nodes[$i]['db'] = new db_mysql($info['dbname'], $info['dbuser'], $info['dbpass'], $info['dbhost']);
			}
			return $this->nodes[$i]['db']->connected;
		}
		return false;
	}

	/**
	 * Execute a multi query.
	 * @param string $qstr The query string.
	 * @return A dbmulti_mysql_query object or false if no node is available.
	 */
	public function multi_query($qstr) {
		$q = new dbmulti_mysql_query($this, $qstr);
		if ($q->executed) return $q;
		return false;
	}

	/**
	 * Execute a multi get_field operation.
	 * @return array An array containing the operation result for each node.
	 */
	public function multi_get_field($field, $tbname, $where_str = '') {
		$result = array();
		for ($i = 0; $i < count($this->nodes); $i++) {
			$res = array(
				'id'		=> $this->nodes[$i]['id'],
				'connected'	=> false
			);
			if ($this->connect_node_by_index($i)) {
				$res['connected'] = true;
				$res['result'] = $this->nodes[$i]['db']->get_field($field, $tbname, $where_str);
			}
			$result[] = $res;
		}
		return $result;
	}

	/**
	 * Execute a multi get_fields operation.
	 * @return array An array containing the operation result for each node.
	 */
	public function multi_get_fields($fields, $tbname, $where_str = '') {
		$result = array();
		for ($i = 0; $i < count($this->nodes); $i++) {
			$res = array(
				'id'		=> $this->nodes[$i]['id'],
				'connected'	=> false
			);
			if ($this->connect_node_by_index($i)) {
				$res['connected'] = true;
				$res['result'] = $this->nodes[$i]['db']->get_fields($fields, $tbname, $where_str);
			}
			$result[] = $res;
		}
		return $result;
	}

	/**
	 * Execute a multi get_records operation.
	 * @return array An array containing the operation result for each node.
	 */
	public function multi_get_records($fields, $tbname, $where_str = '', $key_field = '', $callback = false) {
		$result = array();
		for ($i = 0; $i < count($this->nodes); $i++) {
			$res = array(
				'id'		=> $this->nodes[$i]['id'],
				'connected'	=> false
			);
			if ($this->connect_node_by_index($i)) {
				$res['connected'] = true;
				$res['result'] = $this->nodes[$i]['db']->get_records($fields, $tbname, $where_str, $key_field, $callback);
			}
			$result[] = $res;
		}
		return $result;
	}

	/**
	 * Execute a multi add_record operation.
	 * @return array An array containing the operation result for each node.
	 */
	public function multi_add_record($hash, $tbname) {
		$result = array();
		for ($i = 0; $i < count($this->nodes); $i++) {
			$res = array(
				'id'		=> $this->nodes[$i]['id'],
				'connected'	=> false
			);
			if ($this->connect_node_by_index($i)) {
				$res['connected'] = true;
				$res['result'] = $this->nodes[$i]['db']->add_record($hash, $tbname);
			}
			$result[] = $res;
		}
		return $result;
	}

	/**
	 * Execute a multi update_record operation.
	 * @return array An array containing the operation result for each node.
	 */
	public function multi_update_record($hash, $tbname, $where_str) {
		$result = array();
		for ($i = 0; $i < count($this->nodes); $i++) {
			$res = array(
				'id'		=> $this->nodes[$i]['id'],
				'connected'	=> false
			);
			if ($this->connect_node_by_index($i)) {
				$res['connected'] = true;
				$res['result'] = $this->nodes[$i]['db']->update_record($hash, $tbname, $where_str);
			}
			$result[] = $res;
		}
		return $result;
	}

	/**
	 * Execute a query on the first online node.
	 * @param string $qstr The query string.
	 * @param string $node_id If specified, will contain the ID of the node
	 * selected to execute the query.
	 * @return A db_mysql_query object of false if no node is available.
	 */
	public function query($qstr, &$node_id = false) {
		for ($i = 0; $i < count($this->nodes); $i++) {
			if ($this->connect_node_by_index($i)) {
				if ($node_id !== false) $node_id = $this->nodes[$i]['id'];
				return $this->nodes[$i]['db']->query($qstr);
			}
		}
		if ($node_id !== false) $node_id = false;
		return false;
	}

	/**
	 * Execute a get_field operation on the first online node.
	 * @return The operation result or false if no node is available.
	 */
	public function get_field($field, $tbname, $where_str = '', &$node_id = false) {
		for ($i = 0; $i < count($this->nodes); $i++) {
			if ($this->connect_node_by_index($i)) {
				if ($node_id !== false) $node_id = $this->nodes[$i]['id'];
				return $this->nodes[$i]['db']->get_field($field, $tbname, $where_str);
			}
		}
		if ($node_id !== false) $node_id = false;
		return false;
	}

	/**
	 * Execute a get_fields operation on the first online node.
	 * @return The operation result or false if no node is available.
	 */
	public function get_fields($fields, $tbname, $where_str = '', &$node_id = false) {
		for ($i = 0; $i < count($this->nodes); $i++) {
			if ($this->connect_node_by_index($i)) {
				if ($node_id !== false) $node_id = $this->nodes[$i]['id'];
				return $this->nodes[$i]['db']->get_fields($fields, $tbname, $where_str);
			}
		}
		if ($node_id !== false) $node_id = false;
		return false;
	}

	/**
	 * Execute a get_records operation on the first online node.
	 * @return The operation result or false if no node is available.
	 */
	public function get_records($fields, $tbname, $where_str = '', $callback = false, &$node_id = false) {
		for ($i = 0; $i < count($this->nodes); $i++) {
			if ($this->connect_node_by_index($i)) {
				if ($node_id !== false) $node_id = $this->nodes[$i]['id'];
				return $this->nodes[$i]['db']->get_records($fields, $tbname, $where_str, $callback);
			}
		}
		if ($node_id !== false) $node_id = false;
		return false;
	}

	/**
	 * Execute an add_record operation on the first online node.
	 * @return The operation result or false if no node is available.
	 */
	public function add_record($hash, $tbname, &$node_id = false) {
		for ($i = 0; $i < count($this->nodes); $i++) {
			if ($this->connect_node_by_index($i)) {
				if ($node_id !== false) $node_id = $this->nodes[$i]['id'];
				return $this->nodes[$i]['db']->add_record($hash, $tbname);
			}
		}
		if ($node_id !== false) $node_id = false;
		return false;
	}

	/**
	 * Execute an update_record operation on the first online node.
	 * @return The operation result or false if no node is available.
	 */
	public function update_record($hash, $tbname, $where_str, &$node_id = false) {
		for ($i = 0; $i < count($this->nodes); $i++) {
			if ($this->connect_node_by_index($i)) {
				if ($node_id !== false) $node_id = $this->nodes[$i]['id'];
				return $this->nodes[$i]['db']->update_record($hash, $tbname, $where_str);
			}
		}
		if ($node_id !== false) $node_id = false;
		return false;
	}
}


class dbmulti_mysql_query {

	/**
	 * Internal reference to the dbmulti object associated with this query.
	 * @var object
	 */
	private $_dbmulti;

	/**
	 * Internal result array containing an item for each node.
	 * @var array
	 */
	private $_result = array();

	/**
	 * Flag indicating if at least 1 node was online to execute the query.
	 * @var boolean
	 */
	public $executed = false;


	/**
	 * Constructor. Execute the query on all nodes as defined by the dbmulti
	 * object.
	 * @param object $dbmulti The dbmulti object associated with this query.
	 * @param string $qstr The query string.
	 */
	public function __construct(dbmulti_mysql &$dbmulti, $qstr) {
		$this->_dbmulti = $dbmulti;
		for ($i = 0; $i < count($this->_dbmulti->nodes); $i++) {
			$result = array(
				'id'		=> $this->_dbmulti->nodes[$i]['id'],
				'connected'	=> false
			);
			if ($this->_dbmulti->connect_node_by_index($i)) {
				$result['connected'] = $this->executed = true;
				$result['q'] = $this->_dbmulti->nodes[$i]['db']->query($qstr);
			}
			$this->_result[] = $result;
		}
	}

	/**
	 * Get the next row from the result set.
	 * @param boolean $expanded Include every node in the returned result
	 * even if there are no more rows in the result set or the node is
	 * offline.
	 * @return array
	 */
	public function getrow($expanded = false) {
		$re_sw = false;
		$re = array();

		for ($i = 0; $i < count($this->_result); $i++) {
			$res_sw = false;
			$res = array(
				'id'		=> $this->_result[$i]['id'],
				'connected'	=> $this->_result[$i]['connected'],
				'result'	=> null
			);
			if ($res['connected']) {
				if (is_array($res['result'] = $this->_result[$i]['q']->getrow())) {
					$res_sw = true;
					$re_sw = true;
				}
			}
			if ($res_sw || $expanded) $re[] = $res;
		}

		if ($re_sw) return $re;
		return false;
	}

	/**
	 * Get the number of rows that are in the result set.
	 * @param array $by_node If specified will contain the number or rows
	 * returned by each node.
	 * @return int
	 */
	public function numrows(&$by_node = false) {
		$s = 0;
		for ($i = 0; $i < count($this->_result); $i++) {
			$n = 0;
			if ($this->_result[$i]['connected']) {
				$n = $this->_result[$i]['q']->numrows();
			}
			$s += $n;
			if ($by_node !== false) {
				$by_node[] = array(
					'id'		=> $this->_result[$i]['id'],
					'numrows'	=> $n
				);
			}
		}
		return $s;
	}

	/**
	 * Free the resources associated with this query.
	 */
	public function free() {
		for ($i = 0; $i < count($this->_result); $i++) {
			if ($this->_result[$i]['connected']) {
				$this->_result[$i]['q']->free();
			}
		}
	}
}
