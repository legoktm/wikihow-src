<?php
require_once('commandLine.inc');
global $IP;
require_once("$IP/extensions/wikihow/dedup/Recommendations.class.php");
require_once("$IP/extensions/wikihow/RecommendationPresenter/RecommendationPresenter.class.php");

# This tool calculate the recommendations to a file, and then it saves
# them under a different mode.
class RecommendationController {
	public static function calcRecommendations($filename, $limit = false) {
		$f = fopen($filename, "w");
		$stubs = Recommendations::findStubs($limit);
		$r = new Recommendations();
		$r->excludeWorstRelated(250);
		$userScore = array();
		foreach($stubs as $stub) {
			if($stub) {
				$userScore = $r->getSuggestedUsers($stub);
				arsort($userScore);
				foreach($userScore as $username => $score) {
					if(Recommendations::isAvailableUser($username)) {
						print wfTimestampNow() . " Adding recommendation to edit " . $stub->getText() . " for user " . $username . "\n";
						$u = User::newFromId($username);
						if($u && $u->getId()) {
							fwrite($f,$u->getId() . "\t" . $stub->getArticleId() . "\t" . $score );
							$reasons = $r->getSuggestionReason($username, $stub->getArticleId());
							foreach($reasons as $reason) {
								fwrite($f, "\t" . $reason);	
							}
							fwrite($f, "\n");
						}
					}
				}
			}
		}
	}
	public static function loadRecommendations($filename) {
		$f = fopen($filename, "r");
		while(!feof($f)) {
			$l = fgets($f);
			$l = chop($l);
			$s = preg_split("@\t@",$l);
			if(sizeof($s) >= 3) {
				$u = User::newFromName($s[0]);
				$t = Title::newFromId($s[1]);
				$score = intVal($s[2]);
				if($u && $t && $score) {
					RecommendationPresenter::addRecommendation($u, $t, $score);
					if(sizeof($s) > 3) {
						$n = 3;
						while($n < sizeof($s)) {
							RecommendationPresenter::addRecommendationReason($u,$t,intVal($s[$n]));	
							$n++;
						}
					}
				}
			}
		}

	}
}
if(sizeof($argv) < 2) {
	printf("processRecommendations.php \ncalc [filename] (optional number of dups to check) -- calculate and save to filename\nload [filename] --- load recommendations from filename\n");
	exit;
}
else {
	if($argv[0] == "calc") {
		if(isset($argv[2])) {
			RecommendationController::calcRecommendations($argv[1], $argv[2]);
		}
		else {
			RecommendationController::calcRecommendations($argv[1]);
		}
	}
	elseif($argv[0] == "load") {
		RecommendationController::loadRecommendations($argv[1]);
	}
}
