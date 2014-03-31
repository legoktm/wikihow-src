<?
class Sugg extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'Sugg' );
    }


    function execute ($par) {
		global $wgOut, $wgRequest, $wgUser;
		$wgOut->setArticleBodyOnly(true);
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$t = Title::newfromURL($target);
		$wgOut->addHTML("<h1>How to {$t->getText()}</h1>");
		return;	
	}
}
