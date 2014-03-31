<?
$messages = array();
$messages['en']= 
		array(
		'embedvideo-missing-params' => 'EmbedVideo is missing a required parameter.',
		'embedvideo-bad-params' => 'EmbedVideo received a bad parameter.',
		'embedvideo-unparsable-param-string' => 'EmbedVideo received the unparsable parameter string "<tt>$1</tt>".',
		'embedvideo-unrecognized-service' => 'EmbedVideo does not recognize the video service "<tt>$1</tt>".',
		'embedvideo-bad-id' => 'EmbedVideo received the bad id "$1" for the service "$2".',
		'embedvideo-illegal-width' => 'EmbedVideo received the illegal width parameter "$1".',
		'embedvideo-embed-clause' =>
			'<object width="$2" height="$3">'.
			'<param name="movie" value="$1"></param>'.
			'<param name="allowfullscreen" value="true"></param>' .
			'<param name="wmode" value="transparent"></param>'.
			'<embed src="$1" type="application/x-shockwave-flash" '.
			'wmode="transparent" width="$2" height="$3">'.
			'</embed></object>',
		'embedvideo-embed-clause-popcorn' =>
			'<iframe style="margin-left:-8px;" src="$1" width="640" height="403" frameborder="0" mozallowfullscreen webkitallowfullscreen allowfullscreen></iframe>',
		'embedvideo-embed-clause-howcast' =>
			'<object width="$2" height="$3" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="howcastplayer">'.
			'<param name="movie" value="$1"></param>'.
			'<param name="allowfullscreen" value="true"></param>' .
			'<param name="wmode" value="transparent"></param>'.
			'<param name="allowScriptAccess" value="always"></param>'.
			'<embed src="$1" type="application/x-shockwave-flash" '.
			'wmode="transparent" width="$2" height="$3" allowFullScreen="true" allowScriptAccess="always">'.
			'</embed></object>',
		'embedvideo-embed-clause-videojug' =>
			'<object width="$2" height="$3">'.
			'<param name="movie" value="$1"></param>'.
			'<param name="allowfullscreen" value="true"></param>' .
			'<param name="wmode" value="transparent"></param>'.
			'<param name="allowScriptAccess" value="always"></param>'.
			'<embed src="$1" type="application/x-shockwave-flash" '.
			'wmode="transparent" width="$2" height="$3" allowFullScreen="true" allowScriptAccess="always">'.
			'</embed></object>',
	);
