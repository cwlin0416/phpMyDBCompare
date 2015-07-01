<?php

/**
 * DatabaseConnection 可連線至資料庫取得資料庫相關資訊
 * @author		cwlin <cwlin0416@gmail.com>
 * @copyright	2015 Chien Wei Lin
 * @link		https://github.com/cwlin0416/phpMyDBCompare
 * @version	1.0.0
 */
class DatabaseConnection {

	public $host;
	public $user;
	public $dbname;
	private $conn = null;
	private $password;

	function __construct($host, $dbname, $user, $password) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->dbname = $dbname;
		$this->open();
	}

	function open() {
		try {
			$this->conn = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->user, $this->password);
			$this->conn->query("SET NAMES UTF8");
		} catch (Exception $exc) {
			echo "DatabaseConnection($this->host, $this->dbname, $this->user): Connect error: \n";
			echo $exc->getMessage(). "\n";
		}
	}

	function isConnected() {
		return !empty($this->conn);
	}

	function getTables() {
		$tables = array();
		$sql = "SHOW TABLE STATUS";
		foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
			$row['Character_set_name'] = $this->getTableDefaultCharset($row['Name']);
			$tables[$row['Name']] = $row;
		}
		return $tables;
	}

	function getTableDefaultCharset($tablename) {
		$sql = "SELECT CCSA.character_set_name FROM information_schema.`TABLES` T,";
		$sql .= " information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA";
		$sql .= " WHERE CCSA.collation_name = T.table_collation";
		$sql .= " AND T.table_schema = '$this->dbname'";
		$sql .= " AND T.table_name = '$tablename';";

		$stmt = $this->conn->query($sql, PDO::FETCH_ASSOC);
		$row = $stmt->fetch();
		return $row['character_set_name'];
	}

	function getTableColumns($tablename) {
		$columns = array();
		$sql = "SHOW FULL COLUMNS FROM $tablename";
		foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
			$columns[$row['Field']] = $row;
		}
		return $columns;
	}

	function getTableIndexes($tablename) {
		$indexes = array();
		$sql = "SHOW INDEX FROM $tablename";
		foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
			if (isset($indexes[$row['Key_name']])) {
				// 若有重複的鍵名，代表為組合鍵，以 Composite_Keys 欄位註記以便比對
				$indexes[$row['Key_name']]['Composite_Keys'][] = $row['Column_name'];
			} else {
				$indexes[$row['Key_name']] = $row;
				$indexes[$row['Key_name']]['Composite_Keys'] = array($row['Column_name']);
			}
		}
		return $indexes;
	}

	function getTableConstraints($tablename) {
		$constraints = array();
		$sql = "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME ";
		$sql .= "FROM information_schema.`KEY_COLUMN_USAGE` ";
		$sql .= "WHERE REFERENCED_TABLE_SCHEMA IS NOT NULL AND TABLE_SCHEMA = '$this->dbname' AND TABLE_NAME = '$tablename'";

		foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
			$referential = $this->getTableConstraintReferential($tablename, $row['CONSTRAINT_NAME']);
			$row['UPDATE_RULE'] = $referential['UPDATE_RULE'];
			$row['DELETE_RULE'] = $referential['DELETE_RULE'];
			$constraints[$row['CONSTRAINT_NAME']] = $row;
		}
		return $constraints;
	}

	function getTableConstraintReferential($tablename, $constraintname) {
		$sql = "SELECT UPDATE_RULE, DELETE_RULE FROM information_schema.`REFERENTIAL_CONSTRAINTS` ";
		$sql .= "WHERE CONSTRAINT_SCHEMA = '$this->dbname' AND TABLE_NAME='$tablename' AND CONSTRAINT_NAME='$constraintname'";
		$stmt = $this->conn->query($sql);
		$row = $stmt->fetch();
		return $row;
	}

}
