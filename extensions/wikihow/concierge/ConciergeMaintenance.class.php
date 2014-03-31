<? 
class ConciergeMaintenance extends WAPMaintenance {

	protected function getSubject($subjectText, $lang) {
		$system = $this->wapConfig->getSystemName();
		return "$system: $subjectText";
	}
}
