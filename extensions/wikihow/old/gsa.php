<?	
	require_once('../../LocalKeys.php');

    function getResults($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            # error
            echo "curl error {$url}: " . curl_errno($ch);
        } else {

        }
        curl_close($ch);
        return $contents;
    }

	$host = "http://" . WH_GOOGLE_MINI_HOST. "/suggest";
	if (preg_match("@wikidiy.com@", $_SERVER['SERVER_NAME']))
		$host = "http://173.203.142.20/suggest";
	$url = $host . "?" . preg_replace("@.*suggest/@", "", $_SERVER['SCRIPT_URL']);
	header("Cache-Control: s-maxage=3600, must-revalidate, max-age=0");	
	header("Expires: " . gmdate( "D, d M Y H:i:s", time() + 3600) . " GMT");
	header("Last-modified: " . gmdate( "D, d M Y H:i:s", time() - 60) . " GMT");
	header("Content-type: text/plain");	
	echo getResults($url);
