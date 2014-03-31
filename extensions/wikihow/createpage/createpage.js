function keyxxx (e) {
	var key;
	if(window.event) {
		// for IE, e.keyCode or window.event.keyCode can be used
		key = e.keyCode;
	}
	else if(e.which) {
		// netscape
		key = e.which;
	}
	else {
		// no event, so pass through
		return true;
	}

	if (key == 13) {
		document.editform.related.options[document.editform.related.length] = new Option(document.editform.q.value,document.editform.q.value);
		document.editform.q.value = "";
		document.editform.q.focus();
		return false;
	}
}


var cp_request = null;

function cp_Handler() {
	if ( cp_request.readyState == 4) {
		if ( cp_request.status == 200) {
			var e = document.getElementById('createpage_search_results');
			e.innerHTML = cp_request.responseText;
			document.getElementById('cp_next').disabled = false;
		}
	}
}


function searchTopics() {
	try {
		cp_request = new XMLHttpRequest();
	} catch (error) {
		try {
			cp_request = new ActiveXObject('Microsoft.XMLHTTP');
		} catch (error) {
			return false;
		}
	}

	var t = document.getElementById('createpage_title').value;
	cp_request.open('GET', "http://" + window.location.hostname + "/Special:CreatePageTitleResults?target=" + encodeURIComponent(t));
	cp_request.send('');
	cp_request.onreadystatechange = cp_Handler;

	var e = document.getElementById('createpage_search_results');
	e.innerHTML = "<center><img src='/extensions/wikihow/rotate.gif'><br/>Searching...</center>";
	return true;
}

