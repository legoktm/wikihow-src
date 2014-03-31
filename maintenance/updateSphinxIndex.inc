<?php
/**
 * @addtogroup Maintenance
 */

$bots = array();

/**
 */
function updateSphinxIndex( $start, $end, $maxLockTime, $quiet ) {
	global $wgQuiet;
	global $wgDisableSearchUpdate;
	global $bots;

	$fname = "updateSphinxIndex";

	$wgQuiet = $quiet;
	$wgDisableSearchUpdate = false;

	$bots = User::getBotIDs();

	$dbw = wfGetDB( DB_MASTER );

	output( "Updating searchindex between $start and $end\n" );

	# Select entries from recentchanges which are on top and between the specified times
	$startOfTime = $start === 0;
	$start = $dbw->strencode( $start );
	$end = $dbw->strencode( $end );

	$ns_main = NS_MAIN;
	$page = $dbw->tableName( 'page' );
	if ($startOfTime) {
		$sql = "
			SELECT page_id,page_title,page_counter,page_touched,
			  page_is_featured
			FROM $page
			WHERE page_is_redirect=0 AND page_namespace=$ns_main AND
			  page_touched BETWEEN '$start' AND '$end'
			";
	} else {
		$sql = "TODO query recentchanges here;";
	}
	$res = $dbw->query( $sql, $fname );

	# Lock searchindex
	if ( $maxLockTime ) {
		output( "   --- Waiting for lock ---" );
		lockSearchindex( $dbw );
		$lockTime = time();
		output( "\n" );
	}

	# Loop through the results and do a search update
	$i = 0;
	while ( $row = $dbw->fetchRow( $res ) ) {

		# Allow reads to be processed
		if ( $maxLockTime && time() > $lockTime + $maxLockTime ) {
			output( "    --- Relocking ---" );
			relockSearchindex( $dbw );
			$lockTime = time();
			output( "\n" );
		}

		$success = addSphinxArticle( $dbw, $row );
		$title = $row['page_title'];
		if ($success) {
			output( "$title\n" );
		} else {
			output( "$title (not found)\n" );
		}
	}

	# Unlock searchindex
	if ( $maxLockTime ) {
		output( "    --- Unlocking --" );
		unlockSearchindex( $dbw );
		output( "\n" );
	}
	output( "Done\n" );
}

/*
 *schema:
 *
CREATE TABLE search_results (
	sr_id int unsigned not null,
	sr_url_title varchar(255) not null,
	sr_timestamp int unsigned not null,
	sr_is_featured tinyint(1) unsigned not null,
	sr_has_video tinyint(1) unsigned not null,
	sr_steps tinyint(1) unsigned not null,
	sr_popularity int unsigned not null,
	sr_img varchar(255) not null,
	sr_img_thumb_100 varchar(255) not null,
	primary key(sr_id)
);
 *
 */

function addSphinxArticle(&$dbw, &$row) {
	global $bots;

	$title = $row['page_title'];
	$titleObj = Title::newFromDBkey($title);
	if ($titleObj) {
		// Get current revision
		$rev = Revision::loadFromTitle($dbw, $titleObj);
	} else {
		$rev = null;
	}
	if ($rev) {
		$revTitleObj = $rev->getTitle();
		$revTitle = $revTitleObj->getText();
		$urlTitle = $revTitleObj->getPartialURL();
		$text = $rev->getText();
		$textEnc = $dbw->strencode($text);
		$titleEnc = $dbw->strencode($revTitle);
		$urlTitleEnc = $dbw->strencode($urlTitle);
		$timestamp = wfTimestamp(TS_UNIX, $row['page_touched']);

		$sections = preg_split('@==\s*(\w+)\s*==@', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		$intro = count($sections) > 0 ? $sections[0] : '';
		$stepsMsg = wfMsg('steps');
		while ($curr = next($sections)) {
			if ($curr == $stepsMsg) break;
		}
		$steps = next($sections);
		if (!$steps) $steps = '';

		if (preg_match('@^.*(\[\[Image:([^\|\]]*)(\|[^\]]*)\]\]).*$@Us', $intro, $m)) {
			$img = trim($m[2]);
		}
		$imgEnc = !empty($img) ? $dbw->strencode($img) : '';

		$thumbUrl100 = '';
		if ($imgEnc) {
			$imgTitle = Title::newFromText($img, NS_IMAGE);
			if ($imgTitle) {
				$file = wfFindFile($imgTitle);
				if ($file) {
					$thumb = $file->getThumbnail(100, 100, true);
					if ($thumb) {
						$thumbUrl100 = $thumb->getUrl();
					}
				}
			}
		}

		$stepsCount = preg_match_all('@^(\s*#\s*[^#*])@m', $steps, $m);
		$hasVideo = preg_match('@{{video@i', $text);

		$conds = array();
		$conds[] = "rev_page = '{$row['page_id']}'";
		if (!empty($bots)) {
			$conds[] = "rev_user_text NOT IN (" . $dbw->makeList($bots) . ")";
		}
		$opts = array('ORDER BY' => 'rev_id');
		$firstUser = $dbw->selectField('revision', 'rev_user_text', $conds, __METHOD__, $opts);

		$opts = array('ORDER BY' => 'rev_id DESC');
		$lastUser = $dbw->selectField('revision', 'rev_user_text', $conds, __METHOD__, $opts);

		$sql = "REPLACE INTO search_results SET sr_id='{$row['page_id']}',
			" /*sr_title='{$titleEnc}', sr_url_title='{$urlTitleEnc}',*/ . "
			sr_timestamp='{$timestamp}', 
			sr_is_featured='{$row['page_is_featured']}',
			sr_has_video='{$hasVideo}',
			sr_steps='{$stepsCount}',
			sr_popularity='{$row['page_counter']}', 
			sr_img='{$imgEnc}',
			sr_img_thumb_100='{$thumbUrl100}'
			";
		$dbw->query($sql);

		return true;
	} else {
		return false;
	}
}

function lockSearchindex( &$db ) {
	$write = array( 'search_results' );
	$read = array( 'page', 'revision', 'text', 'interwiki', 'image' );
	$items = array();
	
	foreach( $write as $table ) {
		$items[] = $db->tableName( $table ) . ' LOW_PRIORITY WRITE';
	}
	foreach( $read as $table ) {
		$items[] = $db->tableName( $table ) . ' READ';
	}
	$sql = "LOCK TABLES " . implode( ',', $items );
	$db->query( $sql, 'updateSphinxIndex.inc ' . __METHOD__ );
}

function unlockSearchindex( &$db ) {
	$db->query( "UNLOCK TABLES", 'updateSphinxIndex.inc ' . __METHOD__ );
}

# Unlock and lock again
# Since the lock is low-priority, queued reads will be able to complete
function relockSearchindex( &$db ) {
	unlockSearchindex( $db );
	lockSearchindex( $db );
}

function output( $text ) {
	global $wgQuiet;
	if ( !$wgQuiet ) {
		print $text;
	}
}

?>
