<?
require_once( "commandLine.inc" );

$txt = "
dont
alot
thats
ect
jewelery
doesnt
htis
puclish
mroeim
goto
Homeschooli
theres
ve
doesn
thru
th
untill
su
accesories
cha
ka
thier
fave
gf
isnt
pk
hes
bs
ness
clich
eachother
somthing
excercise
der
clubpenguin
youre
buttercream
bc
remeber
ne
aren
ain't
til
mis
ogg
embarassing
throughly
arent
lil
reccomend
dosen't
esque
somethings
youself
didnt
tun
becuase
carefull
barbeque
wether
deoderant
doneness
ot
iis
sweetcorn
rubberband
placemat
CCleaner
recomended
highschool
noticable
esp
waisted
reccomended
placemats
hairbands
recomend
lightbulb
cornflour
togethers
spacebar
elses
preferrably
completly
burette
soo
hime
runtime
tre
foward
furni
get's
nee
hippy
smilies
hotdog
headbanging
freinds
definately
icecream
wouldnt
childrens
peices
immediatly
pizazz
begining
saftey
your're
earings
your's
sooo
";

$misspelled = explode("\n", trim($txt));

$wgUser = User::newFromName("MiscBot");
$dbr = wfGetDB(DB_SLAVE);

$sql = "SELECT sa_page_id FROM spellcheck_articles WHERE ";

foreach ($misspelled as $word) {
	$word = str_replace(array("'","\r"),array("\'",""),$word);
	$clause[] = "(sa_misspellings like '%,$word,%' OR sa_misspellings like '$word,%' or sa_misspellings like '%,$word' or sa_misspellings = '$word') ";
}

// set some limits
// get the count first
$res = $dbr->query("select count(*) as C from spellcheck_articles WHERE " .implode(" OR ", $clause));
$row = $dbr->fetchObject($res);
$count = $row->C;
$sql .= implode(" OR ", $clause);
$sql .= " LIMIT " . ceil(date("w") * $count / 7) . "," . ceil($count / 7);

$res = $dbr->query($sql);

	while ( $row = $dbr->fetchObject($res) ) {
		$title = Title::newFromID($row->sa_page_id);
		if (!$title) {
			echo "can't make title out of {$row->sa_page_id}\n";
			continue;
		}
		$revision = Revision::newFromTitle($title);
		if (!$revision) {
			echo "can't make revision out of {$row->sa_page_id}\n";
			continue;
		}

		$text = $revision->getText();

		if (strpos($text, "{{copyedit") === false) {
			$text = '{{copyeditbot}}'.$text;

			$a = new Article(&$title);
			$a->doEdit($text,'Adding internal copyedit template');
			echo "updating {$title->getFullURL()}\n";
		} else {
			echo "NOT UPDATING {$title->getFullURL()}\n";
		}
	}
	$dbr->freeResult($res);
?>
