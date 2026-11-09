<?php

// security - hide paths
if (!defined('ADODB_DIR')) die();

class perf_sqlite3 extends adodb_perf{

	public $tablesSQL = "SELECT * FROM sqlite_master WHERE type='table'";

	public $createTableSQL = "CREATE TABLE adodb_logsql (
		created datetime NOT NULL,
		sql0 varchar(250) NOT NULL,
		sql1 text NOT NULL,
		params text NOT NULL,
		tracer text NOT NULL,
		timer decimal(16,6) NOT NULL
		)";

	public $settings = array();

	public function __construct(&$conn)
	{
		$this->conn = $conn;
	}

	public function tables($orderby='1')
	{
		if (!$this->tablesSQL){
			return false;
		}

		$rs = $this->conn->execute($this->tablesSQL);
		if (!$rs) {
			return false;
		}

		$html = rs2html($rs, false, false, false, false);
		return $html;
	}
}
