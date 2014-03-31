<?
class UploadFormPopup extends UploadForm {

	var $mCaption, $mAddToSection, $mStepNum;

	function UploadFormPopup (&$request) {
		$this->mCaption = 	$request->getText('wpCaption');
		$this->mAddToSection = $request->getText('wpAddToSection');
		$this->mStepNum = $request->getText('wpStepNum');
		UploadForm::UploadForm(&$request);
	}

	function execute() {
		global $wgOut, $wgStylePath;
		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHTML(" 
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en' dir='ltr'>
<head>  
    
       <title> " . wfMsg('upload') . "</title>
    <style type='text/css' media='screen,projection'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/skins/WikiHow/main.css') . "'; /*]]>*/</style>
    <script type='text/javascript' src='" . wfGetPad('/extensions/min/f/skins/common/wikibits.js,extensions/wikihow/prototype1.8.2/prototype.js,extensions/wikihow/prototype1.8.2/effects.js,extensions/wikihow/prototype1.8.2/controls.js,skins/WikiHow/gaWHTracker.js&rev=') . WH_SITEREV . "'></script>

    <style type='text/css'>
    BODY { background-color: #DEF; }

    DIV#UploadForm {
        background-color: #DEF;
        margin: 0;
    }

    DIV#UploadForm P { margin: 10px; }

    DIV#UploadForm DIV {
        margin: 1px 2px;
        padding: 1px 2px;
    }

    DIV#UploadForm DIV.formL {
        width: 150px;
        float: left;
        font-weight: bold;
    }

    DIV#UploadForm INPUT, DIV#UploadForm SELECT {
        background-color: #FFF;
        border: 1px solid #666;
    }
    DIV#UploadForm SELECT { font-size: .9em; }

    DIV#UploadForm INPUT.cbox {
        border: none;
        background-color: #DEF;
    }
    DIV#UploadForm INPUT#wpIgnoreWarning { margin-left: 15px; }

    DIV#UploadForm INPUT#wpUpload {
        margin-top: 5px;
        cursor: pointer;
        cursor: hand;
    }

    DIV#UploadForm P#ULimages {
        float: left;
        text-align: center;
    }

    DIV#UploadForm P#UploadButtons {
        text-align: center;
        margin-top: 13px;
    }
    DIV#UploadForm P#UploadButtons INPUT { margin: 0 25px; }

    #uploadcamera {
        float: right;
        margin: 10px;
    }
    </style>
</head>
            <body><div id='UploadForm'>
<img src='" . wfGetPad('/extensions/UploadPopup/uploadcamera.gif') . "' width='100' height='104' id='uploadcamera' />");
		UploadForm::execute();
		// finish off the HTML
        $wgOut->addHTML("</div></body>
            </html>");
	}



	function mainUploadForm( $msg = '') {

		global $wgOut, $wgUser;
		global $wgUseCopyrightUpload;

		global $wgStylePath;
	
		// dont show all of the skin	

		$ew = $wgUser->getOption( 'editwidth' );
		if ( $ew ) $ew = " style=\"width:100%\"";
		else $ew = '';

		if ( '' != $msg ) {
			$sub = wfMsgHtml( 'uploaderror' );
			$wgOut->addHTML( "<h2>{$sub}</h2>\n" .
			  "<span class='error'>{$msg}</span>\n" );
		}
		$sk = $wgUser->getSkin();


		$sourcefilename = wfMsgHtml( 'sourcefilename' );
		$destfilename = wfMsgHtml( 'destfilename' );
		$summary = wfMsg( 'imagepopup_summary' );
		$addtosection = wfMsg('imageuploadsection');
		$cp = wfMsg('imageuploadcaption');

		$licenses = new Licenses();
		$license = wfMsgHtml( 'license' );
		$nolicense = wfMsgHtml( 'nolicense' );
		$licenseshtml = $licenses->getHtml();

        $articlesummary = wfMsg('summary');
        $steps = wfMsg('steps');
        $tips = wfMsg('tips');
        $warnings = wfMsg('warnings');

		$ulb = wfMsgHtml( 'uploadbtn' );

		$titleObj = Title::makeTitle( NS_SPECIAL, 'UploadPopup' );
		$action = $titleObj->escapeLocalURL();

		$encDestFile = htmlspecialchars( $this->mDestFile );

		$watchChecked = $wgUser->getOption( 'watchdefault' )
	? 'checked="checked"'
	: '';

		$wgOut->addHTML( "
            <script type='text/javascript'>
                function checkFFBug() {
                    if ((document.uploadform.wpLicense.value == '' || document.uploadform.wpLicense.value == 'No License' ) 
                        && navigator.userAgent.toLowerCase().indexOf('firefox') >= 0) { 
                        return confirm('" . wfMsg('no_license_selected') . "'); 
                    }
                    return true;
            }
            </script>
	<form id='upload' name='uploadform' method='post' enctype='multipart/form-data' action=\"$action\" onsubmit='return checkFFBug();'>
		<table border='0'>
		<tr>
			<td align='right'><label for='wpUploadFile'>{$sourcefilename}:</label></td>
			<td align='left'>
				<input tabindex='1' type='file' name='wpUploadFile' id='wpUploadFile' " . ($this->mDestFile?"":"onchange='fillDestFilename(\"wpUploadFile\")' ") . "size='40' />
			</td>
		</tr>
		<tr>
			<td align='right'><label for='wpDestFile'>{$destfilename}:</label></td>
			<td align='left'>
				<input tabindex='2' type='text' name='wpDestFile' id='wpDestFile' size='40' value=\"$encDestFile\" />
			</td>
		</tr>
	      <tr>
            <td align='right'><label for='wpAddToSection'>{$addtosection}:</label></td>
            <td align='left'>
        		<SELECT name=wpAddToSection tabindex='3'>
       			  <OPTION VALUE=summary>{$articlesummary}</OPTION>
        			<OPTION VALUE=steps>{$steps}</OPTION>
        			<OPTION VALUE=tips>{$tips}</OPTION>
        			<OPTION VALUE=warnings>{$warnings}</OPTION>
        		</SELECT>#:<input type=text size=2 name=wpStepNum>
            </td>
        </tr>
    	<tr>
            <td align='right'><label for='wpCaption'>{$cp}:</label></td>
            <td align='left'>
        			<input tabindex='4' type='text' name=\"wpCaption\" size='40'\"/>
        	</td>
		</tr>
		<tr>
			<td align='right'><label for='wpUploadDescription'>{$summary}</label></td>
			<td align='left'>
				<input tabindex='5' type='text' name='wpUploadDescription' id='wpUploadDescription' size='40' value=\"" . htmlspecialchars( $this->mUploadDescription ) . "\" />
			</td>
		</tr>
		<tr>" );


	if ( $licenseshtml != '' ) {
			global $wgStylePath;
			$wgOut->addHTML( "
			<td align='right'><label for='wpLicense'>$license:</label></td>
			<td align='left'>
				<script type='text/javascript' src=\"" . wfGetPad('/extensions/min/f/skins/common/upload.js') . "\"></script>
				<select name='wpLicense' id='wpLicense' tabindex='6'
					onchange='licenseSelectorCheck()'>
					$licenseshtml
				</select>
			</td>
			</tr>
			<tr>
		");
		}

		$wgOut->addHtml( "
		<td></td>
		<td>
			<input tabindex='7' type='checkbox' name='wpWatchthis' id='wpWatchthis' $watchChecked value='true' />
			<label for='wpWatchthis'>" . wfMsgHtml( 'watchthis' ) . "</label>
			<input tabindex='8' type='checkbox' name='wpIgnoreWarning' id='wpIgnoreWarning' value='true' />
			<label for='wpIgnoreWarning'>" . wfMsgHtml( 'ignorewarnings' ) . "</label>
		</td>
	</tr>
	<tr>

	</tr>
	<tr>
		<td></td>
		<td align='left'><input id='gatWPUploadPopup' tabindex='9' type='submit' name='wpUpload' value=\"{$ulb}\" /></td>
	</tr>

	<tr>
		<td></td>
		<td align='left'>
		" );
		$wgOut->addWikiText( wfMsgForContent( 'edittools' ) );
		$wgOut->addHTML( "
		</td>
	</tr>

	</table>
	</form>

<script type='text/javascript'>
var gaJsHost = (('https:' == document.location.protocol) ? 'https://ssl.' : 'http://www.');
document.write(unescape('%3Cscript src=\'' + gaJsHost + 'google-analytics.com/ga.js\' type=\'text/javascript\'%3E%3C/script%3E'));    

try {       
var pageTracker = _gat._getTracker('UA-2375655-1'); 
pageTracker._setDomainName('.wikihow.com');} catch(err) {}

if (typeof jQuery == 'undefined') {
	Event.observe(window, 'load', gatStartObservers); 
} else {
	jQuery(window).load(gatStartObservers);
}
</script> " );
	}


	function processUpload() {
		global $wgMaxUploadFiles, $wgOut;

		parent::processUpload();
		
                $wgOut->redirect(''); // clear the redirect
	}

}

class UploadPopup extends UnlistedSpecialPage {

    function __construct() {
		global $wgHooks;
		$wgHooks['UploadComplete'][] = array("UploadPopup::showSuccess");
        parent::__construct( 'UploadPopup' );
    }

	function execute($par) {
    	global $wgRequest;
    	$form = new UploadFormPopup( $wgRequest );
    	$form->execute();
	}

	function showSuccess($uploadForm) {
		global $wgOut, $wgUser, $wgTitle;

		if ($wgTitle->getText() != "UploadPopup") {
			return true;
		}
		$wgOut->redirect(''); // clear redire
        $wgOut->addHTML( '<div style="padding: 10px"><b>' . wfMsg( 'successfulupload' ) . "</b><br/>\n" );
        $text = wfMsg( 'fileuploaded', $ilink, $dlink );
        $filename = '[[Image:' . $uploadForm->mDestName .  '|thumb|' . $uploadForm->mCaption . ']]';
		$titleObj = Title::makeTitle( NS_SPECIAL, 'UploadPopup' );

        $wgOut->addHTML( "
        
              <script type='text/javascript'>
                  
    
                          var filename = \"{$uploadForm->mDestName}\";
                          var caption = \"{$uploadForm->mCaption}\";
                  var section =\"{$uploadForm->mAddToSection}\";
                  var stepNum =\"{$uploadForm->mStepNum}\";
            
                          filename = '[[Image:' + filename + '|thumb|' + caption + ']]';
                  if (section == 'summary') {
                            opener.document.editform.summary.value = filename + opener.document.editform.summary.value;
                } else {
                    var marker = '* ';
                    var control = opener.document.editform.tips;
                    if (section == 'warnings') { 
                        control = opener.document.editform.warnings;
                    } else if (section == 'steps') {
                        control = opener.document.editform.steps;
                        marker = '# ';
                    }
                    var i = 0;
                    if (stepNum == '') stepNum = 1;
                    for (var x = 1; x < stepNum && i >= 0; x++) {
                        i = control.value.indexOf(marker, i+1);
                    }
                    if (i >= 0)
                        control.value = control.value.substring(0, i + 3)
                                        + filename 
                                        + control.value.substring(i + 3, control.value.length);
                    else 
                        control.value += filename;
                }   
        
              </script>

            " . wfMsg('Imagepopup_congrats') );
			//$wgOut->addWikiText($filename);	
			$wgOut->addHTML(wfMsg('Imagepopup_youcan') . 
				"<ul><li><a href='#' onclick='window.close()'>" . wfMsg('Imagepopup_resumediting') . " </a><b>" . wfMsg('or') . 
				"</b></li><li><a href='" . $titleObj->getFullURL() . "'>" . wfMsg('Imagepopup_addanother')  . "</a></li></ul>
			");
            $wgOut->addHTML("</div>
        ");
	
		global $wgLanguageCode;
		if ($wgLanguageCode == 'en') {
			$dbw = wfGetDB(DB_MASTER); 	
			$dbw->insert('upload_popup', array('up_user' => $wgUser->getID(), 'up_filename' => $filename, 'up_timestamp' => wfTimestamp(TS_MW)));
		}
		return true;
	}
}

