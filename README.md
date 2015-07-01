# phpMyDBCompare

phpMyDBCompare can compare MySQL database schema and generate diff sql to synchronize database schema.
It's support:
* Table 
* Table columns 
* Table indexes
* Table constraints
* Character set and collation

Synchronize table constraints may occur error caused by data not consistent (foreign key dosen't exists).
You can use following SQL to fix:

```sql
DELETE FROM `TableA` WHERE TableA.fk NOT IN(SELECT pk FROM `TableB`);
```

If Foreign Key allowed to use NULL, use following SQL to fix:

```sql
UPDATE `TableA` LEFT JOIN $tableB ON TableA.fk = TableB.pk SET TableA.fk = NULL
```


Example Code:

```php
<?php
require_once 'DatabaseCompare.php';

$source = new DatabaseConnection('localhost', 'testa', 'root', '');
$dest = new DatabaseConnection('localhost', 'testb', 'root', '');
$dc = new DatabaseCompare($source, $dest);
SqlBuilder::$constraintSuggestion = true;
SqlBuilder::$ignoreAutoIncrement = true;
//$dc->reverse();
$dc->compareTables();
?>
```

Command Line:

	./compare_cli.php <dbhost> <user> <password> <source dbname> <dest dbname>
