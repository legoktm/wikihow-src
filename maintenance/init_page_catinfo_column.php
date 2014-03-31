<?

require_once('commandLine.inc');

$dbr = wfGetDB(DB_SLAVE);

// GET THE LIST OF TITLES
$opts = array(
	"ORDER BY" => "page_id",
	"LIMIT" => 10000,
	"OFFSET" => ($batch * 10 *1000));
if ($batch == "-") {
	$opts = array(); 
}

$res = $dbr->select('page',
	array('page_namespace', 'page_title'), 
	array(
		'page_namespace' => NS_MAIN,
		'page_is_redirect' => 0,
		'page_catinfo' => 0),
	"init_toplevelcategories.php",
	$opts);

$count = 0;
$updates = array();
$titles = array();
while ($row = $dbr->fetchObject($res)) {
	$t = Title::makeTitle($row->page_namespace, $row->page_title);
	if (!$t) continue;
	$titles[] = $t;
}

// FIGURE OUT WHAT THE CATINFO COLUMN IS SUPPOSED TO BE
foreach ($titles as $t) {
	$val = Categoryhelper::getTitleCategoryMask($t);
	$count++;
	#if ($count % 1000 == 0)  {
	#	print "Done $count\n";
	#}
	$updates[] = "UPDATE page set page_catinfo={$val} where page_id={$t->getArticleID()};";
}

// DO THE UPDATES
print "doing " . sizeof($updates) . " updates\n";
$count = 0;
$dbw = wfGetDB(DB_MASTER);
foreach ($updates as $u) {
	$dbw = wfGetDB(DB_MASTER);
	$dbw->query($u);
	$count++;
	#if ($count % 1000 == 0)  {
	#	print "Done $count\n";
	#}
}

