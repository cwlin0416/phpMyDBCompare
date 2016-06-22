<?php
require_once 'DatabaseCompare.php';

class PageController {

	function __construct() {
		$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_STRING);
		switch ($page) {
			case 'selectDatabase':
				$this->showSelectDatabase();
				break;
			case 'compareResult':
				$this->showCompareResult();
				break;
			default:
				$this->showDatabaseSetting();
		}
	}

	function showDatabaseSetting() {
		?>
		<h1>phpMyDBCompare</h1>
		<h2>Database Setting</h2>
		<form method="POST" action="index.php?page=selectDatabase">
			<fieldset>
				<legend>Source Database</legend>
				<label for="source_host">Host</label>
				<input type="text" id="source_host" name="source_host" value="localhost" /><br />
				<label for="source_user">User</label>
				<input type="text" id="source_user" name="source_user" value="root" /><br />
				<label for="source_passwd">Password</label>
				<input type="password" id="source_passwd" name="source_passwd" value="" /><br />
			</fieldset>
			<br />
			<fieldset>
				<legend>Destination Database</legend>
				<label for="dest_host">Host</label>
				<input type="text" id="dest_host" name="dest_host" value="localhost" /><br />
				<label for="dest_user">User</label>
				<input type="text" id="dest_user" name="dest_user" value="root" /><br />
				<label for="dest_passwd">Password</label>
				<input type="password" id="dest_passwd" name="dest_passwd" value="" /><br />
			</fieldset>
			<p style="text-align: center">
				<input type="submit" />
				<input type="reset" />
			</p>
		</form>
		<?php
	}

	function showSelectDatabase() {
		session_start();

		$source_host = filter_input(INPUT_POST, 'source_host', FILTER_SANITIZE_STRING);
		$source_user = filter_input(INPUT_POST, 'source_user', FILTER_SANITIZE_STRING);
		$source_passwd = filter_input(INPUT_POST, 'source_passwd', FILTER_SANITIZE_STRING);
		$dest_host = filter_input(INPUT_POST, 'dest_host', FILTER_SANITIZE_STRING);
		$dest_user = filter_input(INPUT_POST, 'dest_user', FILTER_SANITIZE_STRING);
		$dest_passwd = filter_input(INPUT_POST, 'dest_passwd', FILTER_SANITIZE_STRING);

		$_SESSION['source_host'] = $source_host;
		$_SESSION['source_user'] = $source_user;
		$_SESSION['source_passwd'] = $source_passwd;
		$_SESSION['dest_host'] = $dest_host;
		$_SESSION['dest_user'] = $dest_user;
		$_SESSION['dest_passwd'] = $dest_passwd;

		try {
			$sourceTables = $this->getDatabases($source_host, $source_user, $source_passwd);
			$destTables = $this->getDatabases($dest_host, $dest_user, $dest_passwd);
			?>
			<h1>phpMyDBCompare</h1>
			<h2>Select Database</h2>

			<form method="POST" action="index.php?page=compareResult">
				<fieldset>
					<legend>Select Database</legend>
					<label for="source_dbname">Source Database</label>
					<?php echo $this->getHtmlSelect('source_dbname', $sourceTables); ?><br />
					<label for="dest_dbname">Destination Database</label>
					<?php echo $this->getHtmlSelect('dest_dbname', $destTables); ?><br />
				</fieldset>
				<p style="text-align: center">
					<input type="submit" />
					<input type="reset" />
				</p>
			</form>

			<?php
		} catch (Exception $exc) {
			echo $exc->getMessage();
		}
	}

	function showCompareResult() {
		session_start();

		$source_dbname = filter_input(INPUT_POST, 'source_dbname', FILTER_SANITIZE_STRING);
		$dest_dbname = filter_input(INPUT_POST, 'dest_dbname', FILTER_SANITIZE_STRING);

		try {
			$source = new DatabaseConnection($_SESSION['source_host'], $source_dbname, $_SESSION['source_user'], $_SESSION['source_passwd']);
			$dest = new DatabaseConnection($_SESSION['dest_host'], $dest_dbname, $_SESSION['dest_user'], $_SESSION['dest_passwd']);

			$dc = new DatabaseCompare($source, $dest);
			SqlBuilder::$constraintSuggestion = true;
			SqlBuilder::$ignoreAutoIncrement = true;
			//$dc->reverse();

			set_time_limit(0);
			?>
			<h1>phpMyDBCompare</h1>
			<h2>Compare Results</h2>
			<fieldset>
				<legend>Results</legend>
				<pre><?php
					flush();
					ob_flush();
					$dc->compareTables();
					?></pre>
			</fieldset>
			<?php
		} catch (Exception $exc) {
			echo $exc->getMessage();
		}
	}

	function getHtmlSelect($name, $options) {
		$html = '<select id="' . $name . '" name="' . $name . '">';
		foreach ($options as $option) {
			$html .= '<option>' . $option . '</option>';
		}
		$html .= '</select>';
		return $html;
	}

	function getDatabases($host, $user, $passwd) {
		$db = new PDO("mysql:host=$host", $user, $passwd);
		$result = $db->query("show databases");
		$tableNames = array();
		while ($row = $result->fetch()) {
			$tableNames[] = $row[0];
		}
		return $tableNames;
	}

}

new PageController();
?>
