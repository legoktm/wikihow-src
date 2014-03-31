<?
	require_once('commandLine.inc');

	$dbw = wfGetDB(DB_MASTER);
	$dbr = wfGetDB(DB_SLAVE);
#echo "getting bad words from file {$argv[0]}\n";
    $f = file_get_contents($argv[0]);
    $lines = split("\n", trim($f));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == "") continue;
        if ($bad_re != "") $bad_re .= "|";
        $bad_re .= "\b$line\b";
    }

#echo "getting acronyms\n";
    $f = file_get_contents($argv[1]);
    $lines = split("\n", trim($f));
    $ac_re = array();
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == "") continue;
        $tokens = split(",", $line);
        $ac_re[] = "/\b" . $tokens[0] . "\b/i";
        if (sizeof($tokens) > 1) {
            $ac_re2[] = trim($tokens[1]);
        } else
            $ac_re2[] = strtoupper($tokens[0]);
    }
/*
mysql> describe suggested_titles;
+-----------------+------------------+------+-----+---------+----------------+
| Field           | Type             | Null | Key | Default | Extra          |
+-----------------+------------------+------+-----+---------+----------------+
| st_id           | int(10) unsigned | NO   | PRI | NULL    | auto_increment | 
| st_title        | varchar(255)     | NO   | UNI |         |                | 
| st_key          | varchar(255)     | NO   | MUL |         |                | 
| st_used         | tinyint(4)       | YES  |     | 0       |                | 
| st_hastraffic_v | varchar(32)      | YES  |     |         |                | 
| st_sv           | tinyint(4)       | YES  |     | -1      |                | 
| st_created      | varchar(14)      | NO   |     |         |                | 
+-----------------+------------------+------+-----+---------+----------------+
*/
	$f = file_get_contents($argv[2]);
	$lines = split("\n", $f);

	$keys = array();
	foreach ($lines as $line) {
		$tokens = split("\t", $line); 
		if (sizeof($tokens) < 2) {
			echo "too few tokens for {$line}\n";
			continue;
		}
		$title = preg_replace('@^"how to @i', '', $tokens[1]);
		$title = preg_replace('@"@', '', $title);
		$title = preg_replace("@\r@", '', $title);
		$key = generateSearchKey($title);
		
		$t= Title::makeTitle(NS_MAIN,$title);
		if (!$t) {
			echo "no object {$title}\n"; exit;
			continue;
		}
		
		if (isset($keys[$key])) {
			echo "already have a key for $key\n";
			continue;
		}
		$keys[$key] = 1;


		# check for bad words
        if (preg_match("/{$bad_re}/i", $title, $matches)) {
            echo "excluding $title because of bad word {$matches[0]}\n";
			continue;
        }

		# check for duplicate keys
		$matches = $dbr->selectField('suggested_titles', array('count(*)'), array('st_key' => $key));
		if ($matches > 0) {
			echo "excluding {$title}\n";
			continue;
		}
	
		# check for duplicates in the new one
        $matches = $dbr->selectField('suggested_titles_bd', array('count(*)'), array('st_title' => $t->getDBKey()));
        if ($matches > 0) {
            echo "excluding {$title} - already have inserted it\n";
            continue;
        }

		$dbw->insert('suggested_titles_bd',
			array(	'st_title' 	=> $t->getDBKey(),
					'st_key'	=> $key,	
					'st_source'	=> 'bd1'
				)
			);
	}	
