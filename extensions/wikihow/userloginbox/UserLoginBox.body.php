<?

if (!defined('MEDIAWIKI')) die();


class UserLoginBox extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('UserLoginBox');
	}
	
	public function getSocialLogin($suffix='') {
		$html = '<div id="fb_connect'.$suffix.'"><a id="fb_login'.$suffix.'" href="#" ><span></span></a></div>
				<div id="gplus_connect'.$suffix.'"><a id="gplus_login'.$suffix.'" href="#"><span></span></a></div>';
	
		return $html;
	}
	
	public function getLogin($isHead = false) {
		global $wgTitle;
		
		$action_url = '/index.php?title=Special:Userlogin&action=submitlogin&'.
					'type=login&returnto='.urlencode($wgTitle->getPrefixedURL()).
					'&sitelogin=1';
					
		if (SSL_LOGIN_DOMAIN) $action_url = 'https://' . SSL_LOGIN_DOMAIN . $action_url;
		if($isHead) {
			$head_suffix = "_head";	
		}
		else {
			$head_suffix = "";	
		}
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'social_buttons' => self::getSocialLogin($head_suffix),
			'suffix' => ($isHead) ? '_head' : '',
			'action_url' => htmlspecialchars($action_url),
		));
		$html = $tmpl->execute('userloginbox.tmpl.php');

		return $html;
	}

	public function execute() {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		$wgOut->setArticleBodyOnly(true);
		
	}
}
