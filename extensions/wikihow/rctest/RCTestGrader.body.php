<?

class RCTestGrader extends UnlistedSpecialPage {
	// Response Constants
	const RESP_QUICKNOTE = 1;
	const RESP_QUICKEDIT = 2;
	const RESP_ROLLBACK = 3;
	const RESP_SKIP = 4;
	const RESP_PATROLLED = 5;
	const RESP_THUMBSUP = 6;
	const RESP_LINK = 7;

	function __construct() {
		parent::__construct( 'RCTestGrader' );
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgRequest;

		if ( $wgUser->isAnon() ) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'prefsnologintext' );
			return;
		}

		$rcTest = new RCTest();
		$testId = $wgRequest->getVal('id');
		$response = $wgRequest->getVal('response');
		$result = $rcTest->gradeTest($testId, $response);
		$wgOut->setArticleBodyOnly(true);

		wfLoadExtensionMessages('RCTestGrader');
		$this->printResponse($result, $response);
	}

	function printResponse($testResult, $response) {
		global $wgOut, $wgUser;

		wfLoadExtensionMessages('RCTestGrader');
		
		$testResult['heading'] = wfMsg('rct_heading', $wgUser->getName());
		$testResult['intro'] = wfMsg('rct_intro');
		$testResult['img_class'] = $this->getImgClass($testResult, $response);
		$testResult['bg_class'] = $this->getBackgroundClass($testResult, $response);
		$testResult['response_heading'] = $this->getResponseHeading($testResult['correct'], $response);
		$testResult['response_txt'] = $this->getResponseText($testResult['ideal_responses'], $response, $testResult['correct']);
		$testResult['exp_heading'] = ($testResult['correct']) ? wfMsg('rct_exp_heading_correct') : wfMsg('rct_exp_heading_wrong');

		EasyTemplate::set_path( dirname(__FILE__).'/' );
		$html = EasyTemplate::html('RCTestGrader', $testResult);
		$wgOut->addHtml($html);
	}

	function getResponseText($idealResponses, $response, $isCorrect) {
		if ($response == RCTestGrader::RESP_LINK) {
			$txt = wfMsg('rct_link_txt');
		} else {
			$txt = $this->getIdealResponsesText($idealResponses, $response, $isCorrect);
		}
		return $txt;
	}

	function getIdealResponsesText($idealResponses, $response, $isCorrect) {
		$ideal = explode(",", $idealResponses);
		$cnt = sizeof($ideal);

		for ($i = 0; $i < $cnt; $i++) {
			$txt .= $cnt > 1 && $i == $cnt - 1 ? " or " : "";
			$txt .= "\"" . $this->getButtonText($ideal[$i]) . "\"";
			$txt .= $cnt > 2 && $i < $cnt - 2 ? ", " : "";
		}
		$txt .= $cnt > 1 ? " buttons." : " button.";

		if ($response == RCTestGrader::RESP_SKIP) {
			$txt = wfMsg('rct_skip_txt', $txt);
		} else if (!$isCorrect) {
			$txt = wfMsg('rct_incorrect_txt', $txt);
		} else {
			$txt = "";
		}
		$txt = "You pressed the \"" . $this->getButtonText($response) . "\" button." . $txt;

		return $txt;
	}

	public function getButtonText($response) {
		return wfMsg('rct_button_' . $response);
	}

	function getImgClass(&$testResult, $response) {
		if ($response == RCTestGrader::RESP_SKIP) {
			$class = "rct_skip";
		} else if ($response == RCTestGrader::RESP_LINK) {
			$class = "rct_skip";	
		} else {
			$class = $testResult['correct'] ? "rct_correct" : "rct_incorrect";
		}
		return $class;
	}
	
	function getBackgroundClass(&$testResult, $response) {
		if ($response == RCTestGrader::RESP_SKIP) {
			$class = "rct_background_neutral";
		} else if ($response == RCTestGrader::RESP_LINK) {
			$class = "rct_background_neutral";	
		} else {
			$class = $testResult['correct'] ? "rct_background_correct" : "rct_background_incorrect";
		}
		return $class;
	}
	
	function getResponseHeading($isCorrect, $response) {
		if ($response == RCTestGrader::RESP_SKIP) {
			$heading = wfMsg('rct_skip');
		} else if ($response == RCTestGrader::RESP_LINK) {
			$heading = wfMsg('rct_link');	
		}else {
			$heading = $isCorrect ? wfMsg('rct_correct') : wfMsg('rct_incorrect');
		}
		return $heading;
	}
}
