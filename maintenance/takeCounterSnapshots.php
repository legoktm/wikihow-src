<?
require_once( "commandLine.inc" );

$dbw =& wfGetDB( DB_MASTER );

// copy over yesterday's values

$today = substr(wfTimestampNow(TS_MW), 0, 8);

// update today's values
$sql = "insert into snap(snap_page, snap_counter1) SELECT page_id, page_counter from page where page_namespace=0 and page_is_redirect=0 on DUPLICATE KEY UPDATE snap_counter1 = page_counter;";
#$dbw->query($sql);


$row = $dbw->selectRow('site_stats',
		array(
			'ss_total_views',
			'ss_total_edits',
			'ss_good_articles',
			'ss_links_emailed',
			'ss_total_pages',
			'ss_users',
			'ss_admins',
			'ss_images'
		),
		array()
	);

$sql = "INSERT INTO sitesnap
		VALUES
		('$today',
{$row->ss_total_views}, 
{$row->ss_total_edits}, 
{$row->ss_good_articles}, 
{$row->ss_links_emailed}, 
{$row->ss_total_pages}, 
{$row->ss_users}, 
{$row->ss_admins}, 
{$row->ss_images}
		);
	";

$dbw->query($sql);

?>
