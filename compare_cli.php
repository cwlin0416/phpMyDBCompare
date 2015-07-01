#!/usr/bin/php
<?php
require_once 'DatabaseCompare.php';

if( count($argv) != 6) {
	echo "Syntax: compare_cli.php <dbhost> <user> <password> <source dbname> <dest dbname>\n";
	return;
}
$host = $argv[1];
$user = $argv[2];
$password = $argv[3];
$source_dbname = $argv[4];
$dest_dbname = $argv[5];

$source = new DatabaseConnection($host, $source_dbname, $user, $password);
$dest = new DatabaseConnection($host, $dest_dbname, $user, $password);
$dc = new DatabaseCompare($source, $dest);
$dc->syntaxHighlight = false;
//$dc->reverse();
$dc->compareTables();
?>