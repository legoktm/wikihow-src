<?

class GallerySlide extends UnlistedSpecialPage {

	var $bInline = false;
	var $bNewLayout_02 = false;

	function __construct() {
		parent::__construct( 'GallerySlide' );
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgRequest;
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		
		
		//on document ready loading of the slideshow
		if ($wgRequest->getVal( 'show-slideshow' ) && $wgRequest->getVal('aid')) {
			
			if ($wgRequest->getVal( 'big-show' )) {
				$this->bInline = true;
			}
			
			if ($wgRequest->getVal( 'article_layout' ) == '2') {
				$this->bNewLayout_02 = true;
			}
		
			$t = Title::newFromID($wgRequest->getInt('aid'));
			$wgOut->setArticleBodyOnly(true);
			echo json_encode(self::getImageSlider($t));
			return;
		}
		
		//grabbing the big slide
		if ($target) {
			$vars = explode(',,',$target);
		}
		$wgOut->setArticleBodyOnly(true);
		$this->printResponse($vars);
	}

	function printResponse($vars) {
		global $wgOut;
		
		$image = $vars[0];		
		$articleID = intval($vars[1]);
		
		if ($vars[3] == 'inline') {
			$this->bInline = true;
		}
		else if (substr($vars[3],0,10) == 'redesign02') {
			$this->bNewLayout_02 = true;
		}
		
		$t = Title::newFromID($articleID);
		if (!$t) return;
		
		$r = Revision::newFromTitle($t);
		if (!$r) return;
		
		$text = self::getStepText($r,$image);
		
		if ($image !== 'end') {
			$image = Title::newFromText($image);
			$file = wfFindFile($image);
		
			if ($this->bNewLayout_02) {
				$lg_thumb = self::getLargeThumbnailObj3($file);
			}
			else if ($this->bInline) {
				$lg_thumb = self::getLargeThumbnailObj2($file);
			}
			else {
				$lg_thumb = self::getLargeThumbnailObj($file);
			}			
			
			$thumb_width = $lg_thumb->getWidth();
			$thumb_height = $lg_thumb->getHeight();
			
			if (!$this->bInline) {
				//make sure the image isn't too small
				if ($thumb_width < 150) {
					$paddingWidth = $thumb_width + 350;
				}
				else {
					$paddingWidth = $thumb_width + 250;
				}
				if ($thumb_height < 150) {
					$paddingHeight = $thumb_height + 200;
				}
				else {
					$paddingHeight = $thumb_height + 100;
				}
			}
			$width = $paddingWidth;
			$height = $paddingHeight;

			$image = $lg_thumb->getUrl();
		}
		
		$tmpl = new EasyTemplate( dirname(__FILE__) );

		$params = array(
			'articlename' => $t->getText(),
			'words' => $text
		);
		
		if ($image == 'end') {
			$related_articles = self::getRelated($r,3);
			$params['related_articles'] = $related_articles;
			
			$tmpl->set_vars($params);
			$html .= $tmpl->execute('galleryfinalslide.tmpl.php');
			
			$width = 850;
			$height = 450;
		}
		else {
			$params['img'] = htmlspecialchars($image);
			
			$tmpl->set_vars($params);
			
			if ($this->bNewLayout_02) {
				$html .= $tmpl->execute('galleryslide3.tmpl.php');
			}
			else if ($this->bInline) {
				$html .= $tmpl->execute('galleryslide2.tmpl.php');
			}
			else {
				$html .= $tmpl->execute('galleryslide.tmpl.php');
			}
		}
		
		//return JSON
		//$callback = $_GET['callback'];
		$callback = '$.prettyPhoto.showitbig';
		if ($callback) {
			$data = array('content' => $html,'width' => $width,'height' => $height);
			$result = $callback . '(' . json_encode($data) . ');';
		}
		
		$wgOut->addHtml($result);
	}
	

	function getStepText($r,$image) {
		global $wgParser;
		
		$stepsMsg = wfMsg('steps');
		
		//grab only the filename
		$image_name = preg_split('@/@',$image);
		$image_name = $image_name[(count($image_name)-1)];
		//removing -crop-... stuff
		$image_name = preg_replace('@-crop-600--600px-@','',$image_name);
		//remove spaces
		$image_name = preg_replace('@ @','-',$image_name); 
		
		$the_text = $r->getText();
		
		for ($i = 1; $i < 10; $i++) {
			$section = $wgParser->getSection($the_text, $i);
			if (empty($section)) break;
			if (preg_match('@==\s*'.$stepsMsg.'\s*==@',$section)) {
				$steps = preg_replace('@== '.$stepsMsg.' ==@','',$section);
				break;
			}
		}
		
		$stepnums = preg_split('/^#[^*#]/m',$steps); //array of only the actual numbered steps
		$steps = Wikitext::splitSteps($steps); //array includes steps w/in steps
		
		foreach ($steps as $s) {
			$s_comp = preg_replace('@ @','-',$s); //strip dashes for the compare
			
			if (stripos($s_comp,$image_name)) {
				
				//get step number
				for ($i=1; $i < count($stepnums); $i++) {
					$the_step = preg_replace('@ @','-',$stepnums[$i]); //strip dashes for the compare
					if (stripos($the_step,$image_name)) {
						$stepnum = $i;
						break;
					}
				}
				
				$text = WikihowArticleEditor::textify($s);
				
				$text = preg_replace("@\'\'\'@","",$text); //remove bold
				$text = preg_replace("/http?:\/\/[^ ]+ /", " ", $text); //remove urls
				
				if ($this->bNewLayout_02) {
					$text = '<div id="gs_text">From Step '.$stepnum.'</div>';
				}
				else {
					$text = '<span>From Step '.$stepnum.'</span><br /><br />'.$text;
				}
				break;
			}
		}
	
		if (!$text && !$this->bNewLayout_02) {
			//oh, is this the intro image?
			$intro = Wikitext::getIntro($the_text);
			$image_name = preg_replace('@-@',' ',$image_name); 
			if (stripos($intro,$image_name)) {
				//$text = Wikitext::flatten($intro);
			}
		}
		
		if (strlen($text) > 250) $text = substr($text,0,250).'...';
	
		return $text;
	}

	
	
	function getRelated($r,$num) {
		$related = self::getRelatedWikihowsFromSource($r,$num);
	
		if (empty($related)) {
			$related = self::getRelatedWikihowsFromCat($r,$num);		
		}
	
		return $related;
	}
	
	function getRelatedWikihowsFromSource($r,$num) {
		$text = $r->getText();
		$whow = WikihowArticleEditor::newFromText($text);
		$related = preg_replace("@^==.*@m", "", $whow->getSection('related wikihows'));
		
		$preg = "/\\|[^\\]]*/";
		$related = preg_replace($preg, "", $related);
		$rarray = split("\n", $related);
		$result = "";
		$count = 0;
		
		foreach($rarray as $related) {
			preg_match("/\[\[(.*)\]\]/", $related, $rmatch);

			$t = Title::newFromText($rmatch[1]);
			if ($t) {
				$a = new Article($t);

				if (!$a->isRedirect()) {
					$result .= self::formatRelated($t);
					if (++$count == $num) break;
				}
			}
		}
		return $result;
	}

	function getRelatedWikihowsFromCat($r,$num) {
		global $wgUser;
		
		$cats = ($r->getTitle()->getParentCategories());
		$cat1 = '';
		$result = '';
		if (is_array($cats) && sizeof($cats) > 0) {
			$keys = array_keys($cats);
			$cat1 = '';
			$found = false;
			$templates = wfMsgForContent('categories_to_ignore');
			$templates = split("\n", $templates);
			$templates = str_replace("http://www.wikihow.com/Category:", "", $templates);
			$templates = array_flip($templates); // make the array associative.
			for ($i = 0; $i < sizeof($keys) && !$found; $i++) {
				$t = Title::newFromText($keys[$i]);
				if (isset($templates[urldecode($t->getPartialURL())]) ) {
					continue;
				}
				$cat1 = $t->getDBKey();
				$found = true;
				break;
			}
		}
		if ($cat1 != '') {
			$sk = $wgUser->getSkin();
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select('categorylinks', 'cl_from', array ('cl_to' => $cat1),
				__METHOD__, array ('ORDER BY' => 'rand()', 'LIMIT' => $num*2));
			$count = 0;
			while (($row = $dbr->fetchObject($res)) && $count < $num) {
				if ($row->cl_from == $r->getTitle()->getArticleID()) {
					continue;
				}
				$t = Title::newFromID($row->cl_from);
				if (!$t) {
					continue;
				}
				if ($t->getNamespace() != NS_MAIN) {
					continue;
				}
				$result .= self::formatRelated($t);
				$count++;
			}
		}
		return $result;
	}
	
	function formatRelated($t) {
		global $wgUser, $wgParser;
		$result = '';
		
		if ($t && $t->exists()) {		
			$r = Revision::newFromTitle($t);
			$intro = $wgParser->getSection($r->getText(), 0);
			$intro = Wikitext::flatten($intro);
		
			if (strlen($intro) > 250) $intro = substr($intro,0,250).'...';
		
			$sk = $wgUser->getSkin();
			$img = SkinWikihowskin::getGalleryImage($t, 238, 139);
		
			$result .= "<div class='slide_related'>
						<a href='{$t->getFullURL()}'><img src='{$img}' alt='' width='238' height='139' class='gs_img' /></a>
						<h3><a href='{$t->getFullURL()}'>{$t->getText()}</a></h3>
						<p>{$intro}</p>
						</div>";
		}
		return $result;
	}
	
	/**
	 *
	 * Returns an image strip of images in the given title
	 *
	 */
	function getImageSlider($title){
		global $wgOut, $wgUser, $wgMemc, $wgLanguageCode, $wgTitle;
		
		wfLoadExtensionMessages('ImageHelper');
		$exceptions = wfMsg('ih_exceptions');
		$imageExceptions = split("\n", $exceptions);

		$revid = $title->getLatestRevID();
		
		$imageName = $title->getDBkey();
//		$result = $wgMemc->get(wfMemcKey("gs_pp_" . $imageName . "_" . $revid));
//		if ($result) {
//			return $result;
//		}

		if(in_array($imageName, $imageExceptions)){
			//$wgMemc->set(wfMemcKey("gs_pp_" . $imageName . "_" . $revid), "");
			return "";
		}
		
		$html = "";

		$imageUrl = array();
		$thumbUrl = array();
		$imageTitle = array();
		$imageWidth = array();
		$imageHeight = array();
		
		$results = self::getImagesFromSteps($title->getArticleID(), $revid);
		if (empty($results)) {
			return;
		}
		$intro_image = self::getImageFromIntro($title->getArticleID(), $revid);

		if (!empty($intro_image)) {
			//add it to the beginning
			array_unshift($results, $intro_image);
		}
		
		$sk = $wgUser->getSkin();
		$titleLink = $sk->makeKnownLinkObj($title, "");
		$count = 0;
		
		foreach($results as $img){

			$img = preg_replace('@\[\[@','',$img);				
			$image = Title::newFromText($img);
			
			if ($image && $image->getArticleID() > 0) {
				$file = wfFindFile($image);
				if ($file && isset($file)) {
				
//					if (!self::thumbnail_exists($file)) {
						//log this so we can act on it later
						/*
						CREATE TABLE slideshow_todo (
							ss2d_page_id INT(8) PRIMARY KEY,
							ss2d_done TINYINT(1) NOT NULL DEFAULT 0,
							ss2d_error TINYINT(1) NOT NULL DEFAULT 0,
							ss2d_created TIMESTAMP DEFAULT NOW()
						)
						*/
/*						$dbw = wfGetDB(DB_MASTER);
						$dbw->ignoreErrors(true);
						$dbw->insert('slideshow_todo', array('ss2d_page_id' => $title->getArticleID()));
						return "";
					}*/

					//$thumb = $file->getThumbnail(60, -1, true, true);
					$thumb = $file->getThumbnail(60, -1, true, true);
					
					$fileUrl[] = $image->getLocalURL();
					$imageUrl[] = $image->getFullURL();
					$thumbUrl[] = $thumb->getUrl();
					$imageTitle[] = $row['il_to'];
					$count++;
				}
			}
		}

		if($count > 3){
			$a = new Article($title);
		
			$tmpl = new EasyTemplate( dirname(__FILE__) );
			$tmpl->set_vars(array(
				'fileUrl' => $fileUrl,
				'imageUrl' => $imageUrl,
				'thumbUrl' => $thumbUrl,
				'imageTitle' => $imageTitle,
				'title' => $titleLink,
				'numImages' => count($imageUrl),
				'imgStrip' => true,
				'articleID' => $title->getArticleID(),
				'revid' => $revid
			));

			if ($this->bNewLayout_02) {
				$html .= $tmpl->execute('prettyPhoto3.tmpl.php');
			}
			else if ($this->bInline) {
				$html .= $tmpl->execute('prettyPhoto2.tmpl.php');
			}
			else {
				$html .= $tmpl->execute('prettyPhoto.tmpl.php');
			}
			
		}
		$result = array('content' => $html, 'num_images' => count($imageUrl));
		
		//$wgMemc->set(wfMemcKey("gs_pp_" . $imageName . "_" . $revid), $result);
		
		return $result;
	}
	
	static function getLargeThumbnailObj(&$file) {
		if ($file->width > 600) {
			$thumb = $file->getThumbNail(600, -1, false, true);
		}
		return $thumb ? $thumb : $file;
	}
	
	static function getLargeThumbnailObj2(&$file) {
		if ($file->width > 400) {
			$thumb = $file->getThumbNail(400, -1, false, true);
		}
		return $thumb ? $thumb : $file;
	}
	
	static function getLargeThumbnailObj3(&$file) {
		if ($file->width > 200) {
			$thumb = $file->getThumbNail(200, -1, false, true);
		}
		return $thumb ? $thumb : $file;
	}
	
	function getImagesFromSteps($articleId,$revid=''){
		global $wgMemc;
		
		$key = wfMemcKey("GallerySlide_getImagesFromSteps", $articleId, $revid);
		$result = $wgMemc->get($key);
		if ($result) {
			return $result;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$rev = Revision::loadFromPageId($dbr, $articleId);
		
		if ($rev) {
			$steps = self::getStepsSection($rev->getText());
			if ($steps) {
				preg_match_all("@\[\[Image:[^\]|\|]*@", $steps, $results);
				$results = $results[0];
				if ($revid != '') {
					$wgMemc->set($key, $results);
				}
			}
		}
		
		return $results;
	}
	
	function getImageFromIntro($articleId,$revid=''){
		global $wgMemc;
		
		$key = wfMemcKey("GallerySlide_getImageFromIntro" , $articleId, $revid);
		$result = $wgMemc->get($key);
		if ($result) {
			return $result;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$rev = Revision::loadFromPageId($dbr, $articleId);
		
		if ($rev) {
			$intro = Wikitext::getIntro($rev->getText());
			
			if ($intro) {
				preg_match("@\[\[Image:[^\]|\|]*@", $intro, $results);
				$results = $results[0];
				if ($revid != '') {
					$wgMemc->set($key, $results);
				}
			}
		}
		
		return $results;
	}
	
	/**
	 * Extract the Steps section from some wikitext.
	 */
	public static function getStepsSection($articleText) {
		static $stepsMsg = '';
		if (empty($stepsMsg)) $stepsMsg = wfMsg('steps');

		$out = array();

		$sections = preg_split('@==\s*([\w ]+)\s*==@', $articleText,-1,PREG_SPLIT_DELIM_CAPTURE);

		$sections = array_map(function ($elem) {
			return trim($elem);
		}, $sections);
		$sections = array_filter($sections, function ($elem) {
			return !empty($elem);
		});
		$sections = array_values($sections);

		while ($i < count($sections)) {
			$name = trim($sections[$i]);
			if ($name == $stepsMsg && $i + 1 < count($sections)) {
				$body = trim($sections[$i + 1]);
				return $body;
			}
			$i++;
		}
	}	

	/* returns boolean */
	public function thumbnail_exists( $file ) {
		$params = array( 'width' => 60, 'crop' => 1 );
		
		$normalisedParams = $params;
		$file->handler->normaliseParams( $file, $normalisedParams );
		$thumbName = $file->thumbName( $params, $normalisedParams );
		$thumbPath = $file->getThumbPath( $thumbName );

		if (file_exists($thumbPath)) {	
			return true;
		}
		else {
			return false;
		}
	}
	
	
	/*
		batchGenerateThumbs()
		---------------------------
		Thumbnails are too expensive to generate on the fly
		so here's the function to batch generate a bunch of new thumbs
	*/
	public function batchGenerateThumbs() {
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select('slideshow_todo', 'ss2d_page_id', array ('ss2d_done' => 0, 'ss2d_error' => 0), __METHOD__);

		$pages = array();
		while ($row = $dbw->fetchObject($res)) {
			$pages[] = $row->ss2d_page_id;
		}
				
		foreach ($pages as $aid) {
			$error = false;
			
			$results = self::getImagesFromSteps($aid);
			if (empty($results)) {
				//mark as errored
				$dbw->update('slideshow_todo',array('ss2d_error' => 1),array('ss2d_page_id' => $aid));
				continue;
			}
			
			foreach($results as $img){
				$img = preg_replace('@\[\[@','',$img);				
				$image = Title::newFromText($img);
				
				if ($image && $image->getArticleID() > 0) {
					$file = wfFindFile($image);
					if ($file && isset($file)) {
						$thumb = $file->getThumbnail(60, -1, true, true);
						$lg_thumb = self::getLargeThumbnailObj($file);
						
						if (!$thumb || !$lg_thumb) {
							$error = true;
						}
					}
				}
			}
			
			if ($error) {
				//mark as errored
				$dbw->update('slideshow_todo',array('ss2d_error' => 1),array('ss2d_page_id' => $aid));
			}
			else {
				//mark as done
				$dbw->update('slideshow_todo',array('ss2d_done' => 1),array('ss2d_page_id' => $aid));
			}
		}
	}
}
