<?php
/**
 * Description of SQLBuilder
 *
 * @author cwlin
 */
class SqlBuilder {

	public static $ignoreAutoIncrement = true;

	/**
	 * 產生欄位子句
	 * @param type $column
	 * @return string
	 */
	public static function getColumnDefinition($column) {
		$columnSql = "`" . $column["Field"] . "`";
		$columnSql .= " " . $column["Type"] . (isset($column["Collation"]) ? " COLLATE " . $column["Collation"] : "");
		$columnSql .= " " . ($column["Null"] == "NO" ? "NOT NULL" : "NULL");
		if (isset($column["Default"])) {
			$columnSql .= " DEFAULT '" . $column["Default"] . "'";
		}
		if (!self::$ignoreAutoIncrement && $column["Extra"] == "auto_increment") {
			$columnSql .= " AUTO_INCREMENT";
		}
		if (!empty($column["Comment"])) {
			$columnSql .= " COMMENT '" . $column["Comment"] . "'";
		}
		return $columnSql;
	}

	/**
	 * 產生索引子句
	 * @param type $index
	 * @return string
	 */
	public static function getIndexDefinition($index) {
		$indexClause = '';
		if ($index['Index_type'] == "FULLTEXT") {
			$indexClause = 'FULLTEXT KEY `' . $index['Key_name'] . '`';
		}
		if ($index['Index_type'] == "BTREE") {
			if ($index['Key_name'] == "PRIMARY") {
				$indexClause = 'PRIMARY KEY';
			} else if ($index['Non_unique'] == 0) {
				$indexClause = 'UNIQUE KEY `' . $index['Key_name'] . '`';
			} else {
				$indexClause = 'KEY `' . $index['Key_name'] . '`';
			}
		}
		$indexSql = $indexClause . ' (`' . implode('`,`', $index['Composite_Keys']) . '`)';
		return $indexSql;
	}

	/**
	 * 產生限制子句
	 * @param type $constraint
	 * @return string
	 */
	public static function getConstraintDefinition($constraint) {
		$constraintSql = "CONSTRAINT `" . $constraint['CONSTRAINT_NAME'] . "` FOREIGN KEY (`" . $constraint['COLUMN_NAME'] . "`)";
		$constraintSql .= " REFERENCES `" . $constraint['REFERENCED_TABLE_NAME'] . "` (`" . $constraint['REFERENCED_COLUMN_NAME'] . "`)";
		$constraintSql .= " ON DELETE " . $constraint['DELETE_RULE'];
		$constraintSql .= " ON UPDATE " . $constraint['UPDATE_RULE'];
		return $constraintSql;
	}

	/**
	 * 建立資料表
	 * @param type $table
	 * @param type $columns
	 * @param type $indexes
	 * @param type $constraints
	 * @return string
	 */
	public static function createTable($table, $columns, $indexes, $constraints) {
		$sql = "CREATE TABLE `" . $table['Name'] . "` (\n";

		// Columns
		foreach ($columns as $name => $data) {
			$sql .= " ". self::getColumnDefinition($data) . ",\n";
		}

		// Indexes
		foreach ($indexes as $index) {
			$sql .= " ". self::getIndexDefinition($index) . ",\n";
		}

		// Constraints
		foreach ($constraints as $constraint) {
			$sql .= " ". self::getConstraintDefinition($constraint) . ",\n";
		}

		$sql .= ") ENGINE=" . $table['Engine'];
		if (!self::$ignoreAutoIncrement && !empty($table['Auto_increment'])) {
			$sql .= " AUTO_INCREMENT=" . $table['Auto_increment'] . "";
		}
		$sql .= " DEFAULT CHARSET=" . $table['Character_set_name'] . " COLLATE=" . $table['Collation'];
		if (!empty($table['Comment'])) {
			$sql .= " COMMENT='" . $table['Comment'] . "'";
		}
		$sql .= ";\n";
		return $sql;
	}

	/**
	 * 修改資料表
	 * @param type $table
	 * @param type $changes
	 * @return string
	 */
	public static function alterTable($table, $changes) {
		$sql = "";
		// Engine
		// Auto_increment
		// Collation
		// Comment
		// Character_set_name
		foreach ($changes as $key => $value) {
			$alter = null;
			switch ($key) {
				case "Engine":
					$alter = "ALTER TABLE `" . $table['Name'] . "` ENGINE=" . $value['to'];
					break;
				case "Auto_increment":
					if (!self::$ignoreAutoIncrement) {
						$alter = "ALTER TABLE `" . $table['Name'] . "` AUTO_INCREMENT=" . $value['to'];
					}
					break;
				case "Collation":
					$alter = "ALTER TABLE `" . $table['Name'] . "` CONVERT TO CHARACTER SET " . $table['Character_set_name'] . " COLLATE " . $value['to'];
					break;
				case "Character_set_name":
					$alter = "ALTER TABLE `" . $table['Name'] . "` CONVERT TO CHARACTER SET " . $value['to'] . " COLLATE " . $table['Collation'];
					break;
				case "Comment":
					$alter = "ALTER TABLE `" . $table['Name'] . "` COMMENT='" . $value['to'] . "'";
					break;
			}
			if (!empty($alter)) {
				$sql .= $alter . ";\n";
			}
		}
		return $sql;
	}

	/**
	 * 刪除資料表
	 * @param type $table
	 * @return string
	 */
	public static function dropTable($table) {
		$sql = "DROP TABLE `" . $table['Name'] . "`";
		$sql .= ";\n";
		return $sql;
	}

	/**
	 * 加入資料表欄位
	 * @param type $table
	 * @param type $column
	 * @return string
	 */
	public static function addTableColumn($table, $column) {
		$columnSql = "ALTER TABLE `" . $table['Name'] . "` ADD ";
		$columnSql .= self::getColumnDefinition($column);
		$sql = $columnSql . ";\n";
		return $sql;
	}

	/**
	 * 修改資料表欄位
	 * @param type $table
	 * @param type $column
	 * @param type $changes
	 * @return string
	 */
	public static function modifyTableColumn($table, $column, $changes) {
		$sql = "";

		// Ignore Key changes
		unset($changes['Key']);
		if (self::$ignoreAutoIncrement) {
			unset($changes['Extra']);
		}
		if (!empty($changes)) {
			foreach ($changes as $key => $value) {
				$column[$key] = $value['to'];
			}
			$columnSql = "ALTER TABLE `" . $table['Name'] . "` MODIFY ";
			$columnSql .= self::getColumnDefinition($column);
			$sql = $columnSql . ";\n";
		}
		return $sql;
	}

	/**
	 * 移除資料表欄位
	 * @param type $table
	 * @param type $column
	 * @return string
	 */
	public static function dropTableColumn($table, $column) {
		$sql = "ALTER TABLE `" . $table['Name'] . "` DROP `" . $column["Field"] . "`;\n";
		return $sql;
	}

	/**
	 * 加入資料表索引
	 * @param type $table
	 * @param type $index
	 * @return string
	 */
	public static function addTableIndex($table, $index) {
		$sql = "ALTER TABLE `" . $table['Name'] . "` ADD ";
		$sql .= self::getIndexDefinition($index) . ";\n";
		return $sql;
	}

	/**
	 * 更新資料表索引
	 * @param type $table
	 * @param type $index
	 * @param type $changes
	 * @return type
	 */
	public static function updateTableIndex($table, $index, $changes) {
		// If changes, drop first, then add.
		$sql = "";
		unset($changes['Cardinality']);
		if (!empty($changes)) {
			$sql .= self::dropTableIndex($table, $index);
			foreach ($changes as $key => $value) {
				$index[$key] = $value['to'];
			}
			$sql .= self::addTableIndex($table, $index);
		}
		return $sql;
	}

	/**
	 * 移除資料表索引
	 * @param type $table
	 * @param type $index
	 * @return string
	 */
	public static function dropTableIndex($table, $index) {
		$sql = "ALTER TABLE `" . $table['Name'] . "` DROP ";

		if ($index['Key_name'] == "PRIMARY") {
			$indexName = 'PRIMARY KEY';
		} else {
			$indexName = 'KEY `' . $index['Key_name'] . '`';
		}
		$sql .= $indexName . ";\n";
		return $sql;
	}

	/**
	 * 加入資料表限制
	 * @param type $table
	 * @param type $constraint
	 * @return string
	 */
	public static function addTableConstraint($table, $constraint) {
		$sql = "ALTER TABLE `" . $table['Name'] . "` ADD ";
		$sql .= self::getConstraintDefinition($constraint) . ";\n";
		return $sql;
	}

	/**
	 * 修改資料表限制
	 * @param type $table
	 * @param type $constraint
	 * @param type $changes
	 * @return type
	 */
	public static function updateTableConstraint($table, $constraint, $changes) {
		// If changes, drop first, then add.
		$sql = "";
		unset($changes['REFERENCED_TABLE_SCHEMA']);
		if (!empty($changes)) {
			//var_dump($changes);
			$sql .= self::dropTableConstraint($table, $constraint);
			foreach ($changes as $key => $value) {
				$constraint[$key] = $value['to'];
			}
			$sql .= self::addTableConstraint($table, $constraint);
		}
		return $sql;
	}

	/**
	 * 移除資料表限制
	 * @param type $table
	 * @param type $constraint
	 * @return string
	 */
	public static function dropTableConstraint($table, $constraint) {
		$sql = "ALTER TABLE `" . $table['Name'] . "` DROP FOREIGN KEY `" . $constraint['CONSTRAINT_NAME'] . "`;\n";
		return $sql;
	}

}