<?

class Copyimages extends SpecialPage {

	function __construct() {
        parent::__construct( 'Copyimages' );
    }	


	function execute($par) {
	
		global $wgOut, $wgRequest;
		global $wgCopyimagesBaseURL, $wgServer;

		wfLoadExtensionMessages('Copyimages');	
		$this->setHeaders();
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$t = Title::newFromText($target);
		if (!$t) {
			$wgOut->addHTML(wfMsg('copyimages_noarticle'));
			return;
		}
		$id = $t->getArticleID();
	
		if ($wgRequest->wasPosted()) {
	    	$dbr = wfGetDB(DB_MASTER);
	    	$res = $dbr->query("select il_to from imagelinks left join page on il_to = page_title where il_from=$id and page_id is NULL;");
	    	$images = array();
	    	while ($row = $dbr->fetchObject($res)) {
	       	 	$images[] = $row->il_to;
	    	}
			foreach ($images as $image) {
				$up = new UploadForm($wgRequest);
				$up->mSourceType = 'web';
			
			}
			return;
		}
		$dbr = wfGetDB(DB_MASTER);
		$res = $dbr->query("select il_to from imagelinks left join page on il_to = page_title where il_from=$id and page_id is NULL;");
		$images = array();
		while ($row = $dbr->fetchObject($res)) {
			$images[] = $row->il_to;
		}
		if (sizeof($images) == 0) {
			$wgOut->addHTML(wfMsg('copyimages_nobrokenimages'));
			return;
		}
		$wgOut->addHTML(wfMsg('copyimages_brokenimages'));
		$wgOut->addHTML("<p><form id='copyimages' method='POST'><ul>");
		foreach ($images as $image) {
				$i = Image::newFromName($image);
				$target_url  =$wgCopyimagesBaseURL . Image::imageUrl( $i->name, $i->fromSharedDirectory );
				$desc = file_get_contents($wgCopyimagesBaseURL . "/index.php?title=Image:$image&action=raw");
				$desc = str_replace("\n", " ", $desc);
				$up = Title::makeTitle(NS_SPECIAL, "Upload");
	
				$url = $up->getFullURL() . "?desturl=" . urlencode($target_url). "&desc=" . urlencode($desc) . "&destname=" . urlencode($image);
				$wgOut->addHTML("<li><a onclick=\"javascript:window.open('$url', 'upload', 'scrollbars=1,status=0,toolbar=0,location=0,menubar=0, height=500,width=800');\">$image</a></li>\n");
			}	
		
		$wgOut->addHTML("</ul><br/><br/></p>");
		$dbr->freeResult($res);
	}
	function CopyimagesHook($article, $user, $text, $summary, $isminor, $iswatch, $section ) {
		global $wgOut;
		$t = $article->getTitle();
		if ($t->getNamespace() != NS_MAIN) 
			return true;
	
		// are there any missing images?
		$id = $article->getID();
		$dbr = wfGetDB(DB_MASTER);
		$res = $dbr->query("select count(*) as C from imagelinks left join page on il_to = page_title where il_from=$id and page_id is NULL;");
		$images = array();
		if  ($row = $dbr->fetchObject($res)) {
			$count = $row->C;
		}
		$dbr->freeResult($res);
	
		if ($count > 0) {
			$cpimages = SpecialPage::getTitleFor( 'Copyimages', $t->getText() );
			$wgOut->redirect($cpimages->getFullURL());
		}
		return true;	
	
	}
	
} // CLass
