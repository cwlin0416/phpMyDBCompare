<?php
require_once 'DatabaseCompare.php';

$source = new DatabaseConnection('localhost', 'testa', 'root', '');
$dest = new DatabaseConnection('localhost', 'testb', 'root', '');
$dc = new DatabaseCompare($source, $dest);
//$dc->reverse();
$dc->compareTables();
?>
