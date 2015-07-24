<?php

/**
 * SqlBuilder 負責依資料庫取得的資訊產生修正 SQL
 * @author		cwlin <cwlin0416@gmail.com>
 * @copyright	2015 Chien Wei Lin
 * @link		https://github.com/cwlin0416/phpMyDBCompare
 * @version	1.0.0
 */
class SqlBuilder {

	public static $ignoreAutoIncrement = true;
	public static $constraintSuggestion = true;

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
		if ($column["Extra"] == "auto_increment") {
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

	public static function getConstraintSuggestion($constraint) {
		$tableA = $constraint['TABLE_NAME'];
		$tableAForeignKey = $constraint['COLUMN_NAME'];
		$tableB = $constraint['REFERENCED_TABLE_NAME'];
		$tableBPrimaryKey = $constraint['REFERENCED_COLUMN_NAME'];
		$suggestSql = "";
		switch ($constraint['DELETE_RULE']) {
			case 'CASCADE':
				// 若限制採用連動刪除，則刪除資料修正關聯
				$suggestSql .= "-- Constraint suggestion: Please aware rows in $tableA.$tableAForeignKey which reference $tableB.$tableBPrimaryKey not existed will be deleted.\n";
				$suggestSql .= "DELETE FROM `$tableA` WHERE $tableA.$tableAForeignKey NOT IN(SELECT $tableBPrimaryKey FROM `$tableB`);\n";
				break;
			case 'RESTRICT':
				// 若限制採用限制刪除，則須補齊資料修正關聯 (須人工作業)
				$suggestSql .= "-- Constraint suggestion: Please manually fix data at $tableB.$tableBPrimaryKey which referenced by $tableA.$tableAForeignKey.\n";
				break;
			case 'SET NULL':
				// 若限制允許為空時，則以空值修正關聯
				$suggestSql .= "-- Constraint suggestion: Please aware rows in $tableA.$tableAForeignKey which reference $tableB.$tableBPrimaryKey not existed will set to NULL.\n";
				$suggestSql .= "UPDATE $tableA LEFT JOIN $tableB ON $tableA.$tableAForeignKey = $tableB.$tableBPrimaryKey SET $tableA.$tableAForeignKey = NULL";
				$suggestSql .= " WHERE $tableA.$tableAForeignKey IS NOT NULL AND $tableB.$tableBPrimaryKey IS NULL;\n";
				break;
			case 'NO ACTION':
				// 若限制允許不做任何處理，則不建議任何動作
				break;
		}
		return $suggestSql;
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
			$sql .= " " . self::getColumnDefinition($data) . ",\n";
		}

		// Indexes
		foreach ($indexes as $index) {
			$sql .= " " . self::getIndexDefinition($index) . ",\n";
		}

		// Constraints
		foreach ($constraints as $constraint) {
			$sql .= " " . self::getConstraintDefinition($constraint) . ",\n";
		}
		
		// Remove tailing comma
		if( substr($sql, -2) == ",\n" ) {
			$sql = substr($sql, 0, -2). "\n";
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

	public static function renameTable($tableA, $tableB) {
		$sql = "RENAME TABLE `" . $tableA['Name'] . "` TO `" . $tableB['Name'] . "`;\n";
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

	public static function changeTableColumn($table, $columnA, $columnB) {
		$columnSql = "ALTER TABLE `" . $table['Name'] . "` CHANGE `" . $columnA['Field'] . "` ";
		$columnSql .= self::getColumnDefinition($columnB);
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
		$sql = "";
		if (self::$constraintSuggestion) {
			$sql .= self::getConstraintSuggestion($constraint);
		}
		$sql .= "ALTER TABLE `" . $table['Name'] . "` ADD ";
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
