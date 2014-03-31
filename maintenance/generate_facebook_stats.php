<?

	require_once('commandLine.inc');
	
	$date1 = wfTimestamp(TS_UNIX, "20091109000000");
	$now = time();
	$dbr = wfGetDB(DB_SLAVE);
	echo "
		<style type='text/css'>
			table.fbconnect_stats {
				width: 900px;
				margin-left: auto; margin-right: auto;
			}
			table.fbconnect_stats td {
				font-family: Arial;	
			}				
		</style>
		<table class='fbconnect_stats'>";
	echo "<tr><td>Date</td><td>Total registrations</td><td>Facebook Connect reg's</td><td>With Email</td></tr>\n";
	while ($date1 < $now) {
		$ts1 = substr(wfTimestamp(TS_MW, $date1), 0, 8) . "000000";
		$ts2 = substr(wfTimestamp(TS_MW, $date1+3600*24), 0, 8) . "000000";
		$date1 += 3600*24;
		$fb = $dbr->selectField( array('user', 'facebook_connect'), 
				array('count(*)'),
				array('user_id=wh_user', "user_registration>='{$ts1}'", "user_registration<'{$ts2}'")
	
			);
        $email = $dbr->selectField( array('user', 'facebook_connect'),
                array('count(*)'),
                array('user_id=wh_user', "user_registration>='{$ts1}'", "user_registration<'{$ts2}'", "user_email != ''")

            );

		$total = $dbr->selectField( array('user'),
                array('count(*)'),
                array("user_registration>='{$ts1}'", "user_registration<'{$ts2}'")

            );


        $fb = $dbr->selectField( array('user', 'facebook_connect'),
                array('count(*)'),
                array('user_id=wh_user', "user_registration>='{$ts1}'", "user_registration<'{$ts2}'")

            );


		$d = date("Y-m-d", $date1);
		echo "<tr><td>$d</td><td>$total</td><td>$fb</td><td>{$email}</td><td>{$created}</td><td>{$edits}</td></tr>\n";
	}
	echo "</table>";
		
	$edits =  $dbr->selectField( array('revision', 'facebook_connect', 'page', 'user'),
                array('count(*)'),
                array('user_id=rev_user', 'page_id=rev_page', 'page_namespace=0', 'user_id=wh_user',
				//"rev_timestamp>='{$ts1}'", "rev_timestamp<'{$ts2}'"
				)
            );
    $created = $dbr->selectField( array('firstedit', 'facebook_connect'),
                array('count(*)'),
                array('fe_user=wh_user', 'fe_user > 0'
				)
            );

	echo "Total articles created: {$created}<br/>";
	echo "Total Main NS Edits: {$edits}<br/>";
