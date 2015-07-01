# phpMyDBCompare

phpMyDBCompare can compare MySQL database schema and generate diff sql to synchronize database schema.

Example Code:

	<?php
	require_once 'DatabaseCompare.php';

	$source = new DatabaseConnection('localhost', 'testa', 'root', '');
	$dest = new DatabaseConnection('localhost', 'testb', 'root', '');
	$dc = new DatabaseCompare($source, $dest);
	//$dc->reverse();
	$dc->compareTables();
	?>

Command Line:
	./compare_cli.php <dbhost> <user> <password> <source dbname> <dest dbname>
