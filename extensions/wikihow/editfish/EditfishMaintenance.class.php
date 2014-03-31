<? 
class EditfishMaintenance extends WAPMaintenance {
	protected function getSubject($subjectText, $lang) {
		$system = $this->wapConfig->getSystemName();
		return "$system: $subjectText";
	}

	protected function handleUnassignedIdRemoval(&$idsToRemove, $lang, $subject) {
		echo "$subject - $lang: NO AUTOMATIC REMOVAL\n";
	}
}
