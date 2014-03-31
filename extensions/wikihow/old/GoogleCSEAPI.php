<?

class GoogleCSEAPI {

	function getResults($url) {
		$ch = curl_init();
		$useragent = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en-US; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13";
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		$contents = curl_exec($ch);
		if (curl_errno($ch)) {
			$wgLastCurlError = curl_error($ch);
			return null;
		} else {

		}
		curl_close($ch);
		return $contents;
	}

	function query($query) {
		$url = "https://www.googleapis.com/customsearch/v1?key=" . WH_GOOGLE_CSE_API_KEY 
			. "&cx=" . WH_GOOGLE_CSE_ID . "&q=" . urlencode($query); 
		$results = json_decode(self::getResults($url));
		return $results;	
	}
}
