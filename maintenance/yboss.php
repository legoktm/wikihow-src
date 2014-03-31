<?

require_once('commandLine.inc');


$url = "http://boss.yahooapis.com/ysearch/web/v1/$1%20site:wikihow.com?appid=" . WH_YAHOO_BOSS_APP_ID .  "&format=xml&view=keyterms";

$dbr = wfGetDB(DB_SLAVE);
$res = $dbr->select('page', array('page_title', 'page_namespace'), array('page_namespace'=>NS_MAIN, 'page_is_redirect = 0'),
	"yboss.php", 	
	array("LIMIT" => 1000, 'ORDER BY' => 'page_counter desc')
	);

$done = array(); 


$ignore = array("how to", "wikihow");

while ($row = $dbr->fetchObject($res)) {
	$t = Title::makeTitle($row->page_namespace, $row->page_title);
	if (isset($done[$t->getDBKey()]))
		continue;
	$dbw = wfGetDB(DB_MASTER);
	if (!$t) continue;
	$u = str_replace("$1", urlencode(strtolower($t->getText())), $url);
	$results = Importvideo::getResults($u);
	preg_match_all("@<result>.*</result>@msU", $results, $matches);
	foreach ($matches[0] as $m) {
		preg_match("@<url>.*</url>@", $m, $k);
		$xx = strip_tags(str_replace("http://www.wikihow.com/", "", urldecode($k[0])));
		$target = Title::newFromURL($xx);
		if (!$target) {
			echo "Couldn't make target from $xx\n";
			continue;
		}
		preg_match_all("@<term>.*</term>@U", $m, $terms);
		foreach ($terms[0] as $t) {
			$t = strtolower(strip_tags($t));
			if (!in_array($t, $ignore)) {
				$dbw->insert('keywords', array('kw_page'=>$target->getArticleID(), 'kw_keyword'=>$t));
			}			
		}
		$done[$target->getDBKey()] = 1;
	}
}
