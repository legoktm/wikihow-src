<?php
/**
 * Special handling for file description pages.
 *
 */

/**
 * Class for viewing NS_IMAGEl pages
 *
 * @
 */
class WikihowImagePage extends ImagePage {

	public static function newFromTitle(&$title, &$page) {
		switch ($title->getNamespace()) {
			case NS_IMAGE:
			case NS_FILE:
				$page = new WikihowImagePage($title);
				break;
		}
		return true;
	}

	/*
	* Most of the logic for image pages exists in this method.  We're
	* overriding to put some extra bells and whistles
	*/
	function view() {
		global $wgShowEXIF, $wgRequest, $wgUser;

		$out = $this->getContext()->getOutput();
		$sk = $this->getContext()->getSkin();
		$diff = $wgRequest->getVal( 'diff' );
		$diffOnly = $wgRequest->getBool( 'diffonly', $wgUser->getOption( 'diffonly' ) );

		if ( $this->mTitle->getNamespace() != NS_IMAGE || ( isset( $diff ) && $diffOnly ) )
			return Article::view();

		if ($wgShowEXIF && $this->getDisplayedFile()->exists()) {
			// FIXME: bad interface, see note on MediaHandler::formatMetadata().
			$formattedMetadata = $this->getDisplayedFile()->formatMetadata();
			$showmeta = $formattedMetadata !== false;
		} else {
			$showmeta = false;
		}

		$this->openShowImage();
		ImageHelper::showDescription($this->mTitle);

		$lastUser = $this->getDisplayedFile()->getUser();
		$userLink = Linker::makeLinkObj(Title::makeTitle(NS_USER, $lastUser), $lastUser);

		$out->addHTML("<div style='margin-bottom:20px'></div>");

		# Show shared description, if needed
		if ( $this->mExtraDescription ) {
			$fol = wfMsgNoTrans( 'shareddescriptionfollows' );
			if( $fol != '-' && !wfEmptyMsg( 'shareddescriptionfollows', $fol ) ) {
				$out->addWikiText( $fol );
			}
			$out->addHTML( '<div id="shared-image-desc">' . $this->mExtraDescription . '</div>' );
		}
		$this->closeShowImage();
		$currentHTML = $out->getHTML();
		$out->clearHTML();
		Article::view();
		$articleContent = $out->getHTML();
		$out->clearHTML();
		$out->addHTML($currentHTML);

		$diffSeparator = "<h2>" . wfMessage('currentrev')->text() . "</h2>";
		$articleParts = explode($diffSeparator, $articleContent);
		if(count($articleParts) > 1){
			$out->addHTML($articleParts[0]);
		}
		$ih = new ImageHelper;
		$articles = $ih->getLinkedArticles($this->mTitle);
		
		if (ImageHelper::IMAGES_ON) {
			$ih->getConnectedImages($articles, $this->mTitle);
			$ih->getRelatedWikiHows($this->mTitle, $sk);
		}
		$ih->addSideWidgets($this, $this->mTitle, $this->getDisplayedFile());

		# No need to display noarticletext, we use our own message, output in openShowImage()
		if ( $this->getID() ) {
			

		} else {
			# Just need to set the right headers
			$out->setArticleFlag( true );
			$out->setRobotpolicy( 'noindex,nofollow' );
			$out->setPageTitle( $this->mTitle->getPrefixedText() );
			//$this->viewUpdates();
		}

		if ($wgUser && !$wgUser->isAnon()) {
			$this->imageHistory();
		}

		ImageHelper::displayBottomAds();

		if ( $showmeta ) {
			$out->addHTML( Xml::element(
				'h2',
				array( 'id' => 'metadata' ),
				wfMessage( 'metadata' )->text() ) . "\n" );
			$out->addWikiText( $this->makeMetadataTable( $formattedMetadata ) );
			$out->addModules( array( 'mediawiki.action.view.metadata' ) );
		}
	}
		
		
		/*
		*  We're not interested in displaying this so just return an empty string in the 
		* case where writeIt is false
		*/
		function uploadLinksBox($writeIt = true) { 
			if (!$writeIt) {
				return "";	
			}
		}

		/*
		*  We'll use this in place of the uploadLinksBox in file history
		*/
	   function uploadLinksMessage($writeIt = true) {
        global $wgUser, $wgOut, $wgTitle;

        if( !$this->getDisplayedFile()->isLocal() )
            return;

        $html .= '<br /><ul>';

        # "Upload a new version of this file" link
        # Disabling upload a new version of this file link per Bug #585
/*
		if (false && UploadForm::userCanReUpload($wgUser, $this->getDisplayedFile()->name) ) {
            $ulink = Linker::makeExternalLink( $this->getUploadUrl(), wfMessage( 'uploadnewversion-linktext' )->text() );
            $html .= "<li><div class='plainlinks'>{$ulink}</div></li>";
        }
*/

        # External editing link
        //$elink = Linker::makeKnownLinkObj( $this->mTitle, wfMsgHtml( 'edit-externally' ), array(), 'action=edit&externaledit=true&mode=file' );
        //$wgOut->addHtml( '<li>' . $elink . '<div>' . wfMsgWikiHtml( 'edit-externally-help' ) . '</div></li>' );

        //wikitext message
        $html .= '<li>' . wfMessage('image_instructions', $wgTitle->getFullText())->text() . '</li></ul>';

        if ($writeIt) {
            $wgOut->addHtml($html);
        }
        else {
            return $html;
        }
    }
	

}

/*
* JRS extend ImageHistoryList so we can override display functions
* for image page history
*/
class WikihowImageHistoryList extends ImageHistoryList {

	function __construct( $imagePage ) {
		parent::__construct( $imagePage);
		$this->showThumb = false;
	}

	public function beginImageHistoryList($navLink) {
	   global $wgOut;
        $s .= '<div class="minor_section">' . Xml::element( 'h2', array( 'id' => 'filehistory' ), wfMessage( 'filehist' )->text() )
            . '<div class="wh_block">'. $wgOut->parse( wfMsgNoTrans( 'filehist-help' ) )
            . Xml::openElement( 'table', array( 'class' => 'filehistory history_table' ) ) . "\n";
        return $s;
	}

	public function endImageHistoryList($navLink) {
		 return "</table>" . $this->imagePage->uploadLinksMessage(false) . "</div></div>\n";
	}

	public function onImagePageFileHistoryLine( $imagepage, $file, $line, $css) {
		return true;

	}
}
