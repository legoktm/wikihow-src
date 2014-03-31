<?

require_once('commandLine.inc');

$dbr = wfGetDB(DB_MASTER); 
$res = $dbr->select('googlebot', '*', array(), __FILE__, array("ORDER BY" => "gb_batch DESC", "LIMIT" => 4));
$rows = array();
while ($row = $dbr->fetchObject($res)) {
	$rows[] = $row;
}

$params = array(
	"Batch" => "gb_batch",
	"Total" => "gb_total",
	"404 errors" => "gb_404",
	"301 redirects" => "gb_301",
	"Main namespace" => "gb_main",
	"::userloggedout requests" => "gb_bad",
	"User namespace" => "gb_user",
	"User talk namespace" => "gb_usertalk",
	"Discussion namespace" => "gb_discuss",
	"Special namespace" => "gb_special",
	"# of unique main "  => "gb_uniquemain",
);

$html = "<style>
	table td {
		font-style: Georgia;
	}
	</style>
	<div style='font-family:Georgia;'>
	<h1>Googlebot Report</h1>
	<table width='100%'>";

$lastweek = '';

foreach ($params as $label => $column) {
	$html .= "<tr><td style='font-family: Georgia; font-weight: bold;'>$label</td>";
	foreach ($rows as $row) {
		$html .= "<td style='font-family: Georgia;'>";
		switch($column) {
			case "gb_total":
				$html .= number_format($row->gb_total, 0, ".", ",") ;
				break;
			case "gb_batch":
				$week = substr($row->gb_batch, 0, 4) . "-" . substr($row->gb_batch, 4, 2);
				$html .= $week;
				if (!$lastweek) $lastweek = $week;
				break;
			case "gb_uniquemain":
				$html .= number_format($row->gb_uniquemain, 0, ".", ",");
				break;
			default:
				$html .= number_format($row->$column, 0, ".", ",") . "(" . number_format($row->$column / $row->gb_total * 100, 2) . "%)";
				break;
		}
		$html .= "</td>";
	}
	$html .= "</tr>";
}
$html .= "</table></div>";

if ($lastweek) {
	$to = new MailAddress("reports@wikihow.com");
	$from = new MailAddress("reports@wikihow.com");
	$subject = "Googlebot report for week " . $lastweek;
	$content_type = "text/html; charset={$wgOutputEncoding}";
	UserMailer::send($to, $from, $subject, $html, null, $content_type);
}

