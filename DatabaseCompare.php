<?php

require_once "SqlFormatter.php";
require_once 'SqlBuilder.php';
require_once 'DatabaseConnection.php';

/**
 * DatabaseCompare 可以比對 MySQL 資料庫的綱要，並且產生修正 SQL
 * @author		cwlin <cwlin0416@gmail.com>
 * @copyright	2015 Chien Wei Lin
 * @link		https://github.com/cwlin0416/phpMyDBCompare
 * @version	1.0.0
 */
class DatabaseCompare {

	public $diffSql = "";
	public $syntaxHighlight = true;

	/**
	 *
	 * @var DatabaseConnection 
	 */
	private $source;

	/**
	 *
	 * @var DatabaseConnection
	 */
	private $dest;

	function __construct(DatabaseConnection $source, DatabaseConnection $dest) {
		$this->source = $source;
		$this->dest = $dest;
	}

	function reverse() {
		$originalSource = $this->source;
		$this->source = $this->dest;
		$this->dest = $originalSource;
	}

	function printSql() {
		if ($this->syntaxHighlight) {
			echo SqlFormatter::highlight($this->diffSql);
		} else {
			echo $this->diffSql;
		}
	}

	/**
	 * 比對陣列值
	 * @param type $sourceArr
	 * @param type $destArr
	 * @return type
	 */
	function _compareArrayValue($sourceArr, $destArr) {
		$results = array();
		foreach ($sourceArr as $key => $value) {
			if ($sourceArr[$key] != $destArr[$key]) {
				$results[$key] = array(
					'from' => $sourceArr[$key],
					'to' => $destArr[$key]
				);
			}
		}
		return $results;
	}

	/**
	 * 比對陣列鍵
	 * @param type $sourceArr
	 * @param type $destArr
	 * @return type
	 */
	function _compareArrayKeys($sourceArr, $destArr) {
		$sourceKeys = array_keys($sourceArr);
		$destKeys = array_keys($destArr);

		$deleteKeys = array_diff($sourceKeys, $destKeys);
		$addKeys = array_diff($destKeys, $sourceKeys);
		$sameKeys = array_intersect($sourceKeys, $destKeys);

		return array($sameKeys, $addKeys, $deleteKeys);
	}

	/**
	 * 比對所有資料表
	 */
	function compareTables() {
		if (!$this->source->isConnected() || !$this->dest->isConnected()) {
			echo "DatabaseCompare: compareTables: Source or Destination Database connection not connected.\n";
			return;
		}
		$sourceTables = $this->source->getTables();
		$destTables = $this->dest->getTables();

		list($syncTableKeys, $addTableKeys, $deleteKeys) = $this->_compareArrayKeys($sourceTables, $destTables);
		//echo "# Same Tables: " . implode(", \n#  ", $syncTableKeys) . "\n";
		//echo "# Add Tables: " . implode(", \n#  ", $addTableKeys) . "\n";
		//echo "# Delete Tables: " . implode(", \n#  ", $deleteKeys) . "\n";

		$this->diffSql = "";

		$this->diffSql .= "\n--\n";
		$this->diffSql .= "-- phpMyDBCompare\n";
		$this->diffSql .= "--\n";
		$this->diffSql .= "-- https://github.com/cwlin0416/phpMyDBCompare\n";
		$this->diffSql .= "-- Copyright (C) 2015 Chien Wei Lin\n";
		$this->diffSql .= "-- Generated time: ". date("Y-m-d H:i:s")."\n";
		$this->diffSql .= "-- Source database: " . $this->source->host . ", " . $this->source->dbname . "\n";
		$this->diffSql .= "-- Destination database: " . $this->dest->host . ", " . $this->dest->dbname . "\n";
		$this->diffSql .= "--\n";

		$this->diffSql .= "\n--\n-- Alter Tables\n--\n";
		foreach ($syncTableKeys as $tableKey) {
			$this->diffSql .= "\n-- Alter Table: $tableKey\n";
			$compareResult = $this->compareTable($sourceTables[$tableKey], $destTables[$tableKey]);
			$this->diffSql .= SqlBuilder::alterTable($sourceTables[$tableKey], $compareResult);

			$this->compareTableColumns($tableKey, $sourceTables[$tableKey]);
			$this->compareTableIndexes($tableKey, $sourceTables[$tableKey]);
			$this->compareTableConstraints($tableKey, $sourceTables[$tableKey]);
		}

		$this->diffSql .= "\n--\n-- Create Tables\n--\n";
		foreach ($addTableKeys as $tableKey) {
			
			$this->diffSql .= SqlBuilder::createTable(
							$destTables[$tableKey], $this->dest->getTableColumns($tableKey), $this->dest->getTableIndexes($tableKey), $this->dest->getTableConstraints($tableKey));
		}

		$this->diffSql .= "\n--\n-- Drop Tables\n--\n";
		foreach ($deleteKeys as $tableKey) {
			
			$this->diffSql .= SqlBuilder::dropTable($sourceTables[$tableKey]);
		}
		$this->printSql();
	}

	/**
	 * 比對各別資料表
	 * @param type $sourceTable
	 * @param type $destTable
	 * @return type
	 */
	function compareTable($sourceTable, $destTable) {
		$results = $this->_compareArrayValue($sourceTable, $destTable);
		return $results;
	}

	/**
	 * 比對所有欄位
	 * @param type $tableKey
	 * @param type $sourceTable
	 */
	function compareTableColumns($tableKey, $sourceTable) {
		$sourceTableColumns = $this->source->getTableColumns($tableKey);
		$destTableColumns = $this->dest->getTableColumns($tableKey);

		list($syncColumnKeys, $addColumnKeys, $deleteColumnKeys) = $this->_compareArrayKeys($sourceTableColumns, $destTableColumns);
		//echo "# Same Columns: " . implode(", ", $syncColumnKeys) . "\n";
		//echo "# Add Columns: " . implode(", ", $addColumnKeys) . "\n";
		//echo "# Delete Columns: " . implode(", ", $deleteColumnKeys) . "\n";

		foreach ($syncColumnKeys as $columnKey) {
			$compareResult = $this->compareTableColumn($sourceTableColumns[$columnKey], $destTableColumns[$columnKey]);
			$this->diffSql .= SqlBuilder::modifyTableColumn($sourceTable, $sourceTableColumns[$columnKey], $compareResult);
		}

		foreach ($addColumnKeys as $columnKey) {
			$this->diffSql .= SqlBuilder::addTableColumn($sourceTable, $destTableColumns[$columnKey]);
		}

		foreach ($deleteColumnKeys as $columnKey) {
			$this->diffSql .= SqlBuilder::dropTableColumn($sourceTable, $sourceTableColumns[$columnKey]);
		}
	}

	/**
	 * 比對各別欄位
	 * @param type $sourceTableColumn
	 * @param type $destTableColumn
	 * @return type
	 */
	function compareTableColumn($sourceTableColumn, $destTableColumn) {
		$results = $this->_compareArrayValue($sourceTableColumn, $destTableColumn);
		return $results;
	}

	/**
	 * 比對資料表的所有索引
	 * @param type $tableKey
	 * @param type $sourceTable
	 */
	function compareTableIndexes($tableKey, $sourceTable) {
		$sourceTableIndexes = $this->source->getTableIndexes($tableKey);
		$destTableIndexes = $this->dest->getTableIndexes($tableKey);

		list($syncIndexKeys, $addIndexKeys, $deleteIndexKeys) = $this->_compareArrayKeys($sourceTableIndexes, $destTableIndexes);
		//echo "# Same Indexes: " . implode(", ", $syncIndexKeys) . "\n";
		//echo "# Add Indexes: " . implode(", ", $addIndexKeys) . "\n";
		//echo "# Delete Indexes: " . implode(", ", $deleteIndexKeys) . "\n";

		foreach ($syncIndexKeys as $indexKey) {
			$compareResult = $this->compareTableIndex($sourceTableIndexes[$indexKey], $destTableIndexes[$indexKey]);
			$this->diffSql .= SqlBuilder::updateTableIndex($sourceTable, $sourceTableIndexes[$indexKey], $compareResult);
		}

		foreach ($addIndexKeys as $indexKey) {
			$this->diffSql .= SqlBuilder::addTableIndex($sourceTable, $destTableIndexes[$indexKey]);
		}

		foreach ($deleteIndexKeys as $indexKey) {
			$this->diffSql .= SqlBuilder::dropTableIndex($sourceTable, $sourceTableIndexes[$indexKey]);
		}
	}

	/**
	 * 比對各別索引
	 * @param type $sourceTableIndex
	 * @param type $destTableIndex
	 * @return type
	 */
	function compareTableIndex($sourceTableIndex, $destTableIndex) {
		$results = $this->_compareArrayValue($sourceTableIndex, $destTableIndex);
		return $results;
	}

	/**
	 * 比對資料表的所有限制
	 * @param type $tableKey
	 * @param type $sourceTable
	 */
	function compareTableConstraints($tableKey, $sourceTable) {
		$sourceTableConstraints = $this->source->getTableConstraints($tableKey);
		$destTableConstraints = $this->dest->getTableConstraints($tableKey);

		list($syncConstraintKeys, $addConstraintKeys, $deleteConstraintKeys) = $this->_compareArrayKeys($sourceTableConstraints, $destTableConstraints);
		//echo "# Same Constraints: " . implode(", ", $syncConstraintKeys) . "\n";
		//echo "# Add Constraints: " . implode(", ", $addConstraintKeys) . "\n";
		//echo "# Delete Constraints: " . implode(", ", $deleteConstraintKeys) . "\n";

		foreach ($syncConstraintKeys as $constraintKey) {
			$compareResult = $this->compareTableConstraint($sourceTableConstraints[$constraintKey], $destTableConstraints[$constraintKey]);
			$this->diffSql .= SqlBuilder::updateTableConstraint($sourceTable, $sourceTableConstraints[$constraintKey], $compareResult);
		}

		foreach ($addConstraintKeys as $constraintKey) {
			$this->diffSql .= SqlBuilder::addTableConstraint($sourceTable, $destTableConstraints[$constraintKey]);
		}

		foreach ($deleteConstraintKeys as $constraintKey) {
			$this->diffSql .= SqlBuilder::dropTableConstraint($sourceTable, $sourceTableConstraints[$constraintKey]);
		}
	}

	/**
	 * 比對各別限制
	 * @param type $sourceTableConstraint
	 * @param type $destTableConstraint
	 * @return type
	 */
	function compareTableConstraint($sourceTableConstraint, $destTableConstraint) {
		$results = $this->_compareArrayValue($sourceTableConstraint, $destTableConstraint);
		return $results;
	}

}
