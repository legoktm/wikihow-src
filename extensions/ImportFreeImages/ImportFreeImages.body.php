<?
class ImportFreeImages extends SpecialPage {

    function __construct() {
		global $wgIFI_ValidDomains, $wgIFI_UseAjax, $wgIFI_AjaxDomain;
        parent::__construct( 'ImportFreeImages' );
		if ($wgIFI_UseAjax) {
			$wgIFI_ValidDomains[$wgIFI_AjaxDomain] = 1;
    	}
		$this->setListed(false);
	}

	function uploadWarning($u) {
        global $wgOut;
        global $wgUseCopyrightUpload;

        $u->mSessionKey = $u->stashSession();
        if( !$u->mSessionKey ) {
            # Couldn't save file; an error has been displayed so let's go.
            return;
        }

        $wgOut->addHTML( "<h2>" . wfMsgHtml( 'uploadwarning' ) . "</h2>\n" );
        $wgOut->addHTML( "<ul class='warning'>{$warning}</ul><br />\n" );

        $save = wfMsgHtml( 'savefile' );
        $reupload = wfMsgHtml( 'reupload' );
        $iw = wfMsgWikiHtml( 'ignorewarning' );
        $reup = wfMsgWikiHtml( 'reuploaddesc' );
        $titleObj = Title::makeTitle( NS_SPECIAL, 'Upload' );
        $action = $titleObj->escapeLocalURL( 'action=submit' );
        if ( $wgUseCopyrightUpload )
        {
            $copyright =  "
    <input type='hidden' name='wpUploadCopyStatus' value=\"" . htmlspecialchars( $u->mUploadCopyStatus ) . "\" />
    <input type='hidden' name='wpUploadSource' value=\"" . htmlspecialchars( $u->mUploadSource ) . "\" />
    ";
        } else {
            $copyright = "";
        }

        $wgOut->addHTML( "
    <form id='uploadwarning' method='post' enctype='multipart/form-data' action='$action'>
        <input type='hidden' name='wpIgnoreWarning' value='1' />
        <input type='hidden' name='wpSessionKey' value=\"" . htmlspecialchars( $u->mSessionKey ) . "\" />
        <input type='hidden' name='wpUploadDescription' value=\"" . htmlspecialchars( $u->mUploadDescription ) . "\" />
        <input type='hidden' name='wpLicense' value=\"" . htmlspecialchars( $u->mLicense ) . "\" />
        <input type='hidden' name='wpDestFile' value=\"" . htmlspecialchars( $u->mDestFile ) . "\" />
        <input type='hidden' name='wpWatchu' value=\"" . htmlspecialchars( intval( $u->mWatchu ) ) . "\" />
    {$copyright}
    <table border='0'>
        <tr>
            <tr>
                <td align='right'>
                    <input tabindex='2' type='submit' name='wpUpload' value=\"$save\" />
                </td>
                <td align='left'>$iw</td>
            </tr>
        </tr>
    </table></form>\n" . wfMsg('importfreeimages_returntoform',  $_SERVER["HTTP_REFERER"]) );
//  $_SERVER["HTTP_REFERER"]; -- javascript.back wasn't working for some reason... hmph.

}

	function execute($par) {
		global $wgUser, $wgOut, $wgScriptPath, $wgRequest, $wgLang, $wgIFI_FlickrAPIKey, $wgTmpDirectory;
		global $wgIFI_ResultsPerPage, $wgIFI_FlickrSort, $wgIFI_FlickrLicense, $wgIFI_ResultsPerRow, $wgIFI_CreditsTemplate;
		global $wgIFI_GetOriginal, $wgIFI_PromptForFilename, $wgIFI_AppendRandomNumber, $wgIFI_FlickrSearchBy, $wgIFI_ThumbType;
		global $wgIFI_CheckForExistingFile, $wgIFI_ValidDomains, $wgIFI_ValidLicenses;
		global $wgIFI_UseAjax, $wgIFI_AjaxKey, $wgIFI_AjaxDomain, $wgIFI_AjaxTemplate;
	
		//old and breaks with the upgrade
		//we have better tools and word is nobody uses this...
		$wgOut->addHTML('<center><tt><b>
Hello!<br>
<br>
Let me clear the cobwebs off this tool...<br />
Hold on. It looks like there are too many cobwebs.<br /><br />
You might be interested in <a href="/Put-a-Photo-in-a-wikiHow-Article">other ways to upload images</a>.<br />
<br />
		</b></tt></center>');
		return;	
	
	
		require_once("phpFlickr-2.0.0/phpFlickr.php");
		wfLoadExtensionMessages('ImportFreeImages');
		$this->setHeaders();
		
		$fname = "wfSpecialImportFreeImages";
		$importPage = Title::makeTitle(NS_SPECIAL, "ImportFreeImages");
	
	    if( $wgUser->isAnon() ) {
	        $wgOut->showErrorPage( 'uploadnologin', 'uploadnologintext' );
	        return;
	     } 
	
		if (empty($wgIFI_FlickrAPIKey)) {
			// error - need to set $wgIFI_FlickrAPIKey to use this extension
			$wgOut->showErrorPage('error', 'importfreeimages_noapikey');
			return;
		}	
		$q = '';	
		if (isset($_GET['q']) && !$wgRequest->wasPosted() ) {
			$q = $_GET['q'];
		}
	
	
		$import = '';
		if ($wgRequest->wasPosted() && isset($_POST['url'])) {
			$import = $_POST['url'];
			$parts = parse_url($import);
			preg_match ("/[^.]+\.[^.]+$/", $parts['host'], $domain_only); 
			$domain = $domain_only[0];
	        if (!isset($wgIFI_ValidDomains[$domain])) {
				$wgOut->addHTML(wfMsg('importfreeimages_invalidurl', $import));
			 	return;
	        }
	
			if ($wgIFI_CheckForExistingFile && $wgRequest->getVal('override', null) == null) {
				$title = urldecode($wgRequest->getVal('ititle'));
				$id = $wgRequest->getVal('id');
				$x = Title::newFromText($title);	
				if ($x) {
					$dbr = wfGetDB(DB_SLAVE);
					$res = $dbr->select("image",
	               	 array("img_name", "img_description"),
	               	 array("img_name like '" .  $dbr->strencode($x->getDBKey()) . "%'")
						);
					$found = false;
					$wt = "";
					while ($row = $dbr->fetchObject($res)) {
						if (strpos($row->img_description, $id) !== false) {
							$img = wfFindFile($row->img_name);
							$t = Title::makeTitle(NS_IMAGE, $row->img_name);
							$wt .= "<tr><td>[[Image:{$img->getName()}|thumb|center|{$t->getText()}]]</td>
							<td valign='top'>" .  wfMsg('image_instructions', $t->getFullText()) . "</td></tr>";
							$found = true;
						}
				
					}
					$dbr->freeResult($res);
				}
				if ($found) {
					$wgOut->addHTML(wfMsg('importfreeimages_similarphotosfound') . "<table>");
					$wgOut->addWikiText($wt);
					$wgOut->addHTML("</table><br clear='all'/><form method='POST'>");
					$vals = $wgRequest->getValues();
					foreach ($vals as $key=>$value) {
						$wgOut->addHTML("<input type='hidden' name='$key' value='" . htmlspecialchars($value) ."'/>");
					}	
					$wgOut->addHTML("<input type='hidden' name='override' value='true'/>");
					$wgOut->addHTML("<input type='button' onclick='window.location=\"{$importPage->getFullURL()}\";' class='guided-button' value='" . wfMsg('importfreeimages_dontimportduplicate') ."'/>&nbsp;&nbsp;&nbsp; <input class='guided-button' type='submit' value='" . wfMsg('importfreeimages_importduplicate') . "'/></form>");
	
					return;
				}
			}	
			
			if ($wgIFI_GetOriginal && $domain = "flickr.com") {
				// get URL of original :1
				$sizes = $f->photos_getSizes($_POST['id']);
				$original = '';
				foreach ($sizes as $size) {
					if ($size['label'] == 'Original') {
						$original = $size['source'];
						$import = $size['source'];
					} else if ($size['label'] == 'Large') {
						$large = $size['source'];
					}
				}
				//somtimes Large is returned but no Original!
				if ($original == '' && $large != '') 
					$import = $large; 
			}
	
			// store the contents of the file
			$pageContents = file_get_contents($import); 	
			$name =$wgTmpDirectory . "/flickr-" . rand(0,999999);
			$r = fopen($name, "w");
			$size = fwrite ( $r, $pageContents);	
			fclose($r);
			chmod( $name, 0777 );
		
			if ($domain == $wgIFI_AjaxDomain) {
				$caption = "{{{$wgIFI_AjaxTemplate}|{$import}}}";
				$id = $wgRequest->getVal('id');
				if ($domain == "wikimedia.org") {
					//maybe we can grab the licnese
					$yy = str_replace("http://upload.wikimedia.org/", "", $import);
					$parts = split("/", $yy);
					$img_title = "";
					if (sizeof($parts) == 7) 
						$img_title = $parts[5];
					else if(sizeof($parts) == 5)  
						$img_title = $parts[4];
					if ($img_title != "") {
						$url = "http://commons.wikimedia.org/wiki/Image:{$img_title}";
						$license = "unknown";
						$contents = file_get_contents("http://commons.wikimedia.org/w/index.php?title=Image:{$img_title}&action=raw");
						foreach ($wgIFI_ValidLicenses as $lic) {
							if (strpos($contents, "{{$lic}") !== false ||
								strpos($contents, "{{self|{$lic}") !== false ||
								strpos($contents, "{{self2|{$lic}") !== false) {
								$license = $lic; 
								break;
							}
						}	
						$caption = "{{{$wgIFI_AjaxTemplate}|{$import}|{$url}|{$license}}}";
					}
				}
			} else  if (!empty($wgIFI_CreditsTemplate)) {
	       		$f = new phpFlickr($wgIFI_FlickrAPIKey);
				$info = $f->photos_getInfo($_POST['id']);
				$caption = "{{" . $wgIFI_CreditsTemplate . $info['license'] . "|{$_POST['id']}|" . urldecode($_POST['owner']) . "|" . urldecode($_POST['name']). "}}";
			} else {
				$caption = wfMsg('importfreeimages_filefromflickr', $_POST['t'], "http://www.flickr.com/people/" . urlencode($_POST['owner']) . " " . $_POST['name']) . " <nowiki>$import</nowiki>. {{CC by 2.0}} ";
			}
			$caption = trim($caption);
			$t = $_POST['ititle'];
	
			// handle duplicate filenames
			$i = strrpos($import, "/");
			if ($i !== false) {
				$import = substr($import, $i + 1);
			}
	
			// pretty dumb way to make sure we're not overwriting previously uploaded images
			$c = 0;
			$nt =& Title::makeTitle( NS_IMAGE, $import);
			$fname = $import;
			while( $nt->getArticleID() && $c < 20) {
				$fname = $c . "_" . $import;
				$nt =& Title::makeTitle( NS_IMAGE, $fname);
				$c++;
			}
			$import = $fname;
	
	/*
			$arr = array ( "size" => $size, "tempname" => $name, 
					"caption" => $caption,
					"url" => $import, "title" => $_POST['t'] );

	*/

			$filename = trim(urldecode($wgRequest->getVal('ititle', null)));
			if ($filename == "undefined")
				$filename = wfTimestampNow();	
			$filename .= "_";
				
			if ($wgIFI_AppendRandomNumber)
				$filename .=  rand(0, 100000);

			$parts = parse_url($wgRequest->getVal('url'));
			$ux = $wgRequest->getVal('url');
			$ext = strtolower(substr($ux, strrpos($ux, ".")));
			switch($ext) {
				case ".png":
				case ".jpeg":
				case ".jpg":
				case ".gif":
					$filename .= $ext;
					break;
				default:
					$filename .= ".jpg";
			}
			$filename = str_replace("?", "", $filename);
			$filename = str_replace(":", "", $filename);
			$filename = preg_replace('/ [ ]*/', ' ', $filename);
	
			if (!class_exists("UploadForm")) 
				require_once('includes/SpecialUpload.php');
			$u = new UploadForm($wgRequest);
	
			//MW 1.12+
			$u->mTempPath = $name;
			$u->mFileSize = $size;
			$u->mComment = $caption;
			$u->mSrcName = $filename;
	
	        $u->mUploadTempName = $name;
	        $u->mUploadSize     = $size; 
			$u->mUploadDescription = $caption;
			$u->mRemoveTempFile = true;
			$u->mIgnoreWarning =  true;
	        $u->mOname = $filename;
			$t = Title::newFromText($filename, NS_IMAGE);
			if (!$t) {
				$wgOut->addHTML("Error - could not create title from filename \"$filename\"");
				return;
			}
			if ($t->getArticleID() > 0) {
				$sk = $wgUser->getSkin();
	           	$dlink = $sk->makeKnownLinkObj( $t );
	            $warning .= '<li>'.wfMsgHtml( 'fileexists', $dlink ).'</li>';
				
				// use our own upload warning as we dont have a 'reupload' feature
				$this->uploadWarning	($u);
				return;
			} else {
				$u->execute();
			}
	
		}
	
	
		$wgOut->addHTML(wfMsg ('importfreeimages_description') . "<br/><br/>
			<form method=GET action='" . $importPage->getFullURL() . "'>".wfMsg('search').
			": <input type=text name=q value='" . htmlspecialchars($q) . "'><input type=submit value=".wfMsg('search').">
			</form>");
	
		if ($q != '') { 
			$page = $_GET['p'];
			if ($page == '') $page = 1;
	        	$f = new phpFlickr($wgIFI_FlickrAPIKey);
	        	$q = $_GET['q'];
			// TODO: get the right licenses
	        	$photos = $f->photos_search(array(
					"$wgIFI_FlickrSearchBy"=>"$q", "tag_mode"=>"any", 
					"page" => $page, 
					"per_page" => $wgIFI_ResultsPerPage, "license" => $wgIFI_FlickrLicense, 
					"sort" => $wgIFI_FlickrSort  ));
	
			$i = 0;
			if ($photos == null || !is_array($photos) || sizeof($photos) == 0 || !isset($photos['photo']) ) {
				$wgOut->addHTML(wfMsg("importfreeimages_nophotosfound",$q));
				return;
			}
			$sk = $wgUser->getSkin();
	
			$wgOut->addHTML("
				<style type='text/css' media='all'>/*<![CDATA[*/ @import '/extensions/ImportFreeImages/ifi.css'; /*]]>*/</style>
				<div id='photo_results'> " . wfMsg('importfreeimages_results', 'Flickr') . "
				<center>
				<table cellpadding='4' class='ifi_table'>
				<form method='POST' name='uploadphotoform' action='" . $importPage->getFullURL() . "'>
					<input type='hidden' name='url' value=''/>
					<input type='hidden' name='id' value=''/>
					<input type='hidden' name='action' value='submit'/>
					<input type='hidden' name='owner' value=''/>
					<input type='hidden' name='name' value=''/>
					<input type='hidden' name='ititle' value=''/>
				</form>	
		<script type=\"text/javascript\">
	
			function s2 (url, id, owner, name, ititle) {
				results = document.getElementById('photo_results');
				document.uploadphotoform.url.value = url;
				document.uploadphotoform.id.value = id;
				document.uploadphotoform.owner.value = owner;
				document.uploadphotoform.name.value = name;
				document.uploadphotoform.ititle.value = ititle;
				if (" . ($wgIFI_PromptForFilename ? "true" : "false") . ") {
					ititle = ititle.replace(/\+/g, ' ');
					document.uploadphotoform.ititle.value = prompt('" . wfMsg('importfreeimages_promptuserforfilename') . "', unescape(ititle));
					if (document.uploadphotoform.ititle.value == '') {
						document.uploadphotoform.ititle.value = ititle;
					}
				}
				document.uploadphotoform.submit();
				results.innerHTML = '" . wfMsg('importfreeimages_uploadingphoto') . "';
			}
	
		</script>
				");
				$count = 0;
	        	foreach ($photos['photo'] as $photo) {
					$count++;
				if ($i % $wgIFI_ResultsPerRow == 0) $wgOut->addHTML("<tr>");
	                	$owner = $f->people_getInfo($photo['owner']);
	                	$wgOut->addHTML( "<td><a href='http://www.flickr.com/photos/" . $photo['owner'] . "/" . $photo['id'] . "/'>" );
	                	$wgOut->addHTML( $photo['title'] );
	                	$wgOut->addHTML( "</a><br/>".wfMsg('importfreeimages_owner').": " );
	                	$wgOut->addHTML( "<a href='http://www.flickr.com/people/" . $photo['owner'] . "/'>") ;
	                	$wgOut->addHTML( $owner['username'] );
	                	$wgOut->addHTML( "</a><br/>" );
	                	//$wgOut->addHTML( "<img  src=http://static.flickr.com/" . $photo['server'] . "/" . $photo['id'] . "_" . $photo['secret'] . "." . "jpg>" );
						$url="http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}.jpg";
	                    $wgOut->addHTML( "<img src=\"http://farm{$photo['farm']}.static.flickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_{$wgIFI_ThumbType}.jpg\">" );     
	
				$wgOut->addHTML( "<br/>(<a href='#' onclick=\"s2('$url', '{$photo['id']}','{$photo['owner']}', '" 
							. urlencode($owner['username']  ) . "', '" . urlencode($photo['title']) . "');\">" . 
									wfMsg('importfreeimages_importthis') . "</a>)\n" );
				$wgOut->addHTML("</td>");
				if ($i % $wgIFI_ResultsPerRow == ($wgIFI_ResultsPerRow - 1) ) $wgOut->addHTML("</tr>");
				$i++;
			}
			if ($count == 0) {
				$wgOut->addHTML(wfMsg('importfreeimages_noresults'));
			}
			$wgOut->addHTML("</table></center>");
	
			if ($wgIFI_UseAjax) {	
				$s = htmlspecialchars($wgRequest->getVal('q'));	
				$gPage = ($page - 1) * 2;
				$importjs = HtmlSnips::makeUrlTags('js', array('importfreeimages.js'), '/extensions/ImportFreeImages/', false);
				$wgOut->addHTML("<br/><br/>" . wfMsg('importfreeimages_results', $wgIFI_AjaxDomain) . "
					<script type='text/javascript'>			
						var gAjaxDomain = '{$wgIFI_AjaxDomain}';
						var gInitialSearch = '{$s}';
						var gPage = {$gPage};
						var gImportMsg = '" .  wfMsg('importfreeimages_importthis') . "';
						var gImportMsgManual = '" .  wfMsg('importfreeimages_importmanual') . "';
						var gManualURL= '" . Title::makeTitle(NS_PROJECT, 'Manual Import')->getFullURL() . "';
						var gMoreInfo='" . wfMsg('importfreeimages_moreinfo') . "';
					</script>
				   <script src='http://www.google.com/jsapi?key={$wgIFI_AjaxKey}' type='text/javascript'></script>
					$importjs
				 	<div id='searchform' style='width:200px; display:none;'>Loading...</div>
	    			<div id='ajax_results'></div>
				");
			}
			$page = $page + 1;
	
			$wgOut->addHTML("</form>");
			$wgOut->addHTML("<br/>" .  $sk->makeLinkObj($importPage, wfMsg('importfreeimages_next', $wgIFI_ResultsPerPage), "p=$page&q=" . urlencode($q ) ) );
			$wgOut->addHTML("</div>");
	
	
	
		}
	}
}
