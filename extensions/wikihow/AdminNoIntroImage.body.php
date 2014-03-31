<?

if (!defined('MEDIAWIKI')) die();

class AdminNoIntroImage extends UnlistedSpecialPage {

	const DEFAULT_CENTER_PIXELS = 550;

	public function __construct() {
		parent::__construct('AdminNoIntroImage');
	}

	/**
	 * Parse the input field into an array of URLs and Title objects
	 */
	private static function parseURLlist($pageList) {
		$pageList = preg_split('@[\r\n]+@', $pageList);
		$urls = array();
		foreach ($pageList as $url) {
			$url = trim($url);
			if (!empty($url)) {
				$title = WikiPhoto::getArticleTitle(urldecode($url));
				$urls[] = array('url' => $url, 'title' => $title);
			}
		}
		return $urls;
	}

	/**
	 * Cycle through the url list to call the removeIntroImages function
	 */
	private static function removeIntroImagesUrls(&$urls, $px, $text) {
		$dbr = wfGetDB(DB_SLAVE);
		foreach ($urls as &$url) {
			$err = '';
			$final_step = '';
			if (!$url['title']) {
				$err = 'Unable to load article';
			} else {
				$introText = '';
				$wikitext = Wikitext::getWikitext($dbr, $url['title']);
				if ($wikitext) {
					$introText = Wikitext::getIntro($wikitext);
				}

				if (!$introText) {
					$err = 'Unable to load wikitext';
				} else {
				
					//first, let's use the intro image for the final step
					if ($px > 0) {
						$new_final_step = self::makeFinalStep($introText, $px, $text);
						
						if ($new_final_step) {
							list($stepsText, $sectionID) = Wikitext::getStepsSection($wikitext, true);
							$stepsText = $stepsText.$new_final_step;
							$wikitext = Wikitext::replaceStepsSection($wikitext, $sectionID, $stepsText, true);
							
							if (preg_match("@[\r\n]+===[^=]*===@m", $stepsText)) {
								$final_step = 'x (alt)'; //success! (yay!) but has alt methods (boo!)
							}
							else {
								$final_step = 'x'; //success!
							}
						}
					}
				
					$prevIntroText = $introText;
					$introText = self::removeIntroImages($prevIntroText, $url['title']);
					if ($introText && $introText != $prevIntroText) {
						$wikitext = Wikitext::replaceIntro($wikitext, $introText, true);
						$comment = 'Removing intro images';
						
						if ($final_step == 'x') {
							$comment .= '; Made final step out of former intro image';
						}

						$err = Wikitext::saveWikitext($url['title'], $wikitext, $comment);
						
						if (empty($err)) {
							//make sure the intro image adder doesn't grab it
							$id = $url['title']->getArticleID();
							if ($id) {
								$dbw = wfGetDB(DB_MASTER);
								$dbw->update('imageadder', array('imageadder_hasimage' => 1), array('imageadder_page'=>$id));
							}
						}
					} else {
						$err = 'Either no intro image or no intro found';
					}
				}
			}
			$url['err'] = $err;
			$url['final_step'] = $final_step;
		}
	}

	/**
	 * Remove any intro images
	 */
	private static function removeIntroImages($text) {
		//strip current images
		$text = preg_replace('@\[\[Image[^\]]*\]\]@','',$text);
		
		return $text;
	}
	
	/**
	 * Create final step from the intro image
	 */
	private static function makeFinalStep($intro,$px,$text) {
		$newStep = '';
		
		preg_match('@\[\[Image[^\]]*\]\]@',$intro,$matches);
		$introimg = $matches[0];
		
		if (!empty($introimg)) {
			$orientation = 'center';
			$introimg = Wikitext::changeImageTag($introimg, $px, $orientation);
			$newStep = "\n".'#'.$text.'<br><br>'.$introimg;
		}
		
		return $newStep;
	}

	/**
	 * Execute special page.  Only available to wikihow staff.
	 */
	public function execute() {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		$user = $wgUser->getName();
		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($wgRequest->wasPosted()) {
			$wgOut->setArticleBodyOnly(true);

			$b_final_step = $wgRequest->getVal('final-step','') == 1;
			
			if ($b_final_step) {
				$px = intval($wgRequest->getVal('final-step-img-size', '0'));
				//$fst = $wgRequest->getVal('final-step-text', '');
				$fst = 'Finished.';
				
				if ($px < 50 || $px > 591) {
					$result = array('result' => '<i>ERROR: bad pixel value</i>');
					print json_encode($result);
					return;
				}
			}

			$pageList = $wgRequest->getVal('pages-list', '');

			$urls = self::parseURLlist($pageList);
			if (empty($urls)) {
				$result = array('result' => '<i>ERROR: no URLs given</i>');
				print json_encode($result);
				return;
			}

			self::removeIntroImagesUrls($urls, $px, $fst);

			$html = '<style>.tres tr:nth-child(even) {background: #ccc;}</style>';
			$html .= '<table class="tres"><tr><th width="400px">URL</th><th>Step Added</th><th>Error</th></tr>';
			foreach ($urls as $row) {
				$html .= "<tr><td><a href='{$row['url']}'>{$row['url']}</a></td><td align='center'>{$row['final_step']}</td><td>{$row['err']}</td></tr>";
			}
			$html .= '</table>';

			$result = array('result' => $html);
			print json_encode($result);
			return;
		}

		$wgOut->setHTMLTitle('Admin - No Intro Image - wikiHow');

		$defaultCenterPixels = self::DEFAULT_CENTER_PIXELS;
$tmpl = <<<EOHTML
<form id="images-resize" method="post" action="/Special:AdminNoIntroImage">
<div style="font-size: 16px; letter-spacing: 2px; margin-bottom: 15px;">
	No Intro Images
</div>
<div style="font-size: 13px; margin-bottom: 10px; border: 1px solid #dddddd; padding: 10px;">
	<div>
		<span>
			<input type="checkbox" name="final-step" id="final-step" value="1" /> 
			<label for="final-step">Make final step out of current intro image</label>
		</span>
		<ul style="margin-left:25px;">
			<li>size: <input id="final-step-img-size" type="text" size="4" name="final-step-img-size" value="{$defaultCenterPixels}" /> pixels</li>
			<!--li>
				text: 
				<select id="final-step-text" name="final-step-text">
					<option value="Voil&#0224">Voil&#0224;</option>
					<option value="And there we go!">And there we go!</option>
					<option value="Finished.">Finished.</option>
					<option value="And...done.">And...done.</option>
				</select>
			</li-->
		</ul>
	</div>
</div>
<div style="font-size: 13px; margin: 20px 0 7px 0;">
	Enter a list of URLs such as <code style="font-weight: bold;">http://www.wikihow.com/Lose-Weight-Fast</code> to process.<br />
	One per line.
</div>
<textarea id="pages-list" name="pages-list" type="text" rows="10" cols="70"></textarea>
<button id="pages-go" disabled="disabled" style="padding: 5px;">process</button><br/>
<br/>
<div id="pages-result">
</div>
</form>

<script>
(function($) {
	$(document).ready(function() {
		$('#pages-go')
			.removeAttr('disabled')
			.click(function () {
				var form = $('#images-resize').serializeArray();
				$('#pages-result').html('loading ...');
				$.post('/Special:AdminNoIntroImage',
					form,
					function(data) {
						$('#pages-result').html(data['result']);
						$('#pages-list').focus();
					},
					'json');
				return false;
			});

		$('#pages-list')
			.focus();

	});
})(jQuery);
</script>
EOHTML;

		$wgOut->addHTML($tmpl);
	}
}
