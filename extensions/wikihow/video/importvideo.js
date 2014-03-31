function changeUrl() {
	var url = document.getElementById('url').value;
    var params = url;
    var base = url;
	if (params.indexOf("?") > 0 ) {
        params = params.substring(params.indexOf("?") + 1);
        base = base.substring(0,base.indexOf("?") );
    } else {
        params = "";
    }
    var parts = params.split("&");
    var newparams = "";
    for (var i = 0; i < parts.length; i++) {
        var x = parts[i].split("=");
        if (x[0] != "orderby") newparams += x[0] + "=" + x[1] + "&";
    }
    url = base+ '?orderby=' + document.getElementById('orderby').value + "&" + newparams;
	if (window.location.pathname == '/index.php') {
		getContent(url, 'winpop_outer', winPopW, winPopH);
	} else {
    	window.location.href = url;
	}
}

function importvideo(id) {
	document.videouploadform.video_id.value = id;
	if (window.location.pathname == '/index.php') {
		// see winpop.js
		postForm('http://' + window.location.hostname + '/Special:ImportvideoPopup', 'videouploadform', 'POST');
		return;
	}	
	$('#dialog-box').load('/Special:ImportvideoPopup',function() {
		$('#dialog-box').dialog({
			modal: true,
			width: 600,
			title: 'Add Description',
			closeText: 'Close',
		});
	});
}

var evc_request;
var evc_id;

function evc_Handler() {
    if ( evc_request.readyState == 4) {
        var b = document.getElementById("button_" + evc_id);
        b.disabled = false;
        var e = document.getElementById("comment_" + evc_id);
        e.disabled = false;
        if ( evc_request.status == 200) {
            // append the message to the end
            e.value = "";
            var e = document.getElementById("preview_" + evc_id);
            e.innerHTML += evc_request.responseText;
        } else {
            alert("error posting comment: " + evc_request.status + " "+ evc_request.responseText);
        }
    }
}

function evc_submitComment(id) {
    var e = document.getElementById("comment_" + id);
    e.disabled = true;
    var b = document.getElementById("button_" + id);
    b.disabled = true;
    evc_id = id;
    
    try {
        evc_request = new XMLHttpRequest();
    } catch (error) {
        try {
            evc_request = new ActiveXObject('Microsoft.XMLHTTP');
        } catch (error) {
            return false;
        }
    }
    var parameters = "comment=" + encodeURIComponent(e.value) + "&source=" + evc_source + "&id=" + id + "&target="+ encodeURIComponent(evc_target);
    evc_request.open('POST', evc_url);
    evc_request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    evc_request.send(parameters);
    evc_request.onreadystatechange = evc_Handler;
    return false;
}

var hidden = false;
function showhidesteps() {
    if (hidden) {
        setStyle(document.getElementById('stepsarea'), 'display: inline;' );
        setStyle(document.getElementById('showsteps'), 'display: none;' );
        setStyle(document.getElementById('hidesteps'), 'display: inline;' );
        hidden = false;
    } else {
        setStyle(document.getElementById('stepsarea'), 'display: none;' );
        setStyle(document.getElementById('showsteps'), 'display: inline;' );
        setStyle(document.getElementById('hidesteps'), 'display: none;' );
        hidden = true;
    }
}

function setStyle(e, s) {
	if (e) {
    	if (navigator.userAgent.indexOf('MSIE') > 0) {
        	e.style.setAttribute('csstext', s, 0);
        } else {
             e.setAttribute('style', s);
        }
    }
}


function throwdesc() {
    var word = document.getElementById("importvideo_comment").value;
	window.top.document.videouploadform.description.value = word;
    $('#dialog-box').dialog('close');
	window.top.document.videouploadform.submit();
}