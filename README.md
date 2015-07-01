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


## Example Code

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

## Command Line

	./compare_cli.php <dbhost> <user> <password> <source dbname> <dest dbname>

## Result

```sql
--
-- phpMyDBCompare
--
-- https://github.com/cwlin0416/phpMyDBCompare
-- Copyright (C) 2015 Chien Wei Lin
-- Generated time: 2015-07-01 18:38:18
-- Source database: localhost, testa
-- Destination database: localhost, testb
--

--
-- Alter Tables
--

-- Alter Table: mod_fxwidth_layout_colstyle
ALTER TABLE `mod_fxwidth_layout_colstyle` DROP `is_admin`;
ALTER TABLE `mod_fxwidth_layout_colstyle` ADD KEY `mod_fxwidth_layout_colstyle_ibfk_2` (`site_id`);
ALTER TABLE `mod_fxwidth_layout_colstyle` ADD KEY `mod_fxwidth_layout_colstyle_ibfk_1` (`mod_id`);
-- Constraint suggestion: Please aware rows in mod_fxwidth_layout_colstyle.mod_id which reference proj_modules.mod_id not existed will be deleted.
DELETE FROM `mod_fxwidth_layout_colstyle` WHERE mod_fxwidth_layout_colstyle.mod_id NOT IN(SELECT mod_id FROM `proj_modules`);
ALTER TABLE `mod_fxwidth_layout_colstyle` ADD CONSTRAINT `mod_fxwidth_layout_colstyle_ibfk_1` FOREIGN KEY (`mod_id`) REFERENCES `proj_modules` (`mod_id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- Constraint suggestion: Please aware rows in mod_fxwidth_layout_colstyle.site_id which reference proj_sites.site_id not existed will be deleted.
DELETE FROM `mod_fxwidth_layout_colstyle` WHERE mod_fxwidth_layout_colstyle.site_id NOT IN(SELECT site_id FROM `proj_sites`);
ALTER TABLE `mod_fxwidth_layout_colstyle` ADD CONSTRAINT `mod_fxwidth_layout_colstyle_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `proj_sites` (`site_id`) ON DELETE CASCADE ON UPDATE CASCADE;
```