<?
require_once( "commandLine.inc" );

/***********
 *
 * Script that can be used to test removing alternate methods added
 * through the alt method adder. 
 *
 ***********/

$delete = false;

switch($argv[0]) {
	case "test":
		if($argv[1] == null || $argv[1] > 10) {
			echo "You must enter a number <= 10\n";
			exit;
		}
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(MethodGuardian::TABLE_NAME, array("*"), array("ama_patrolled" => 0), __FILE__, array("ORDER BY" => "RAND()", "LIMIT" => $argv[1]));
		echo $dbr->lastQuery() . "\n\n";
		foreach($res as $row) {
			$title = Title::newFromID($row->ama_page);
			if($title) {
				echo "ARTICLE: " . $title->getText() . "\n";
				echo "METHOD NAME: " . $row->ama_method . "\nSTEPS: " . $row->ama_steps . "\n";
				$result = MethodGuardian::checkContent($title, $row->ama_method, $row->ama_steps, true);
				if($result) {
					echo "GOOD\n";
				}
				else {
					echo "BAD\n";
				}
			}
			else {
				echo "BAD: title no longer exists\n";
			}
		}
		break;
	case "static":
		$methodTitle = "A method title";
		//$methodSteps = "#12. 12 12 12 12 12 12 12 12 12\n#34$";
		$methodSteps = "#ass. IS. A. GOOD. THING.\n#AND ANOTHER. THING. IS. REALLY. GOOD. TO. SAY. IN. A CASE OF SOMETHING\n";
		$title = Title::newFromText("Kiss");
		$result = MethodGuardian::checkContent($title, $methodTitle, $methodSteps, true);
		if($result) {
			echo "GOOD\n\n";
		}
		else {
			echo "BAD\n\n";
		}
		break;
	case "delete":
		$delete = true;
		$dbw = wfGetDB(DB_MASTER);
	case "all":
		$res = DatabaseHelper::batchSelect(MethodGuardian::TABLE_NAME, array("*"), array("ama_patrolled" => 0));
		$rows = array();
		foreach($res as $row) {
			$rows[] = $row;
		}

		$total = 0;
		$bad = 0;
		foreach($rows as $row) {
			$title = Title::newFromID($row->ama_page);
			$result = MethodGuardian::checkContent($title, $row->ama_method, $row->ama_steps, true);
			if(!$result) {
				if($title)
					echo "ARTICLE: " . $title->getText() . "\n";
				echo "METHOD NAME: " . $row->ama_method . "\nSTEPS: " . $row->ama_steps . "\n\n\n";
				if($delete) {
					$dbw->delete(MethodGuardian::TABLE_NAME, array("ama_id" => $row->ama_id), __FILE__);
					// sleep for 0.5s
					usleep(500000);
				}
				$bad++;
			}
			$total++;
		}
		echo "$bad methods removed out of $total\n";
		break;
	case "id":
		if($argv[1] == null) {
			echo "You must enter an article id\n";
			exit;
		}
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(MethodGuardian::TABLE_NAME, array("*"), array("ama_patrolled" => 0, "ama_page" => $argv[1]), __FILE__);
		echo $dbr->lastQuery() . "\n\n";
		foreach($res as $row) {
			$title = Title::newFromID($row->ama_page);
			if($title)
				echo "ARTICLE: " . $title->getText() . "\n";

			echo "METHOD NAME: " . $row->ama_method . "\nSTEPS: " . $row->ama_steps . "\n";
			$result = MethodGuardian::checkContent($title, $row->ama_method, $row->ama_steps, true);
			if($result) {
				echo "GOOD\n\n";
			}
			else {
				echo "BAD\n\n";
			}
		}
		break;
}