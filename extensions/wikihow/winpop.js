var winPopH, winPopW;
var resized = false;

var extraHeight = 90;
var specialWidth = 750;
var regularWidth = 679;
var replacelinks = true;

// isSpecial is for boxes that need to be wider than the standard. 
// If isSpecial is true, width will be set to specialWidth, otherwise
// not.
function popModal(url,w,h, isSpecial, onloadFunc) {
    // Add the HTML to the body    
    theBody = document.getElementsByTagName('BODY')[0];
    if (document.getElementById('winpop_overlay')) { return false; }
    
    //overlay
    popmask = document.createElement('div');
    popmask.id = 'winpop_overlay';

    //window
    popcont = document.createElement('div');

    popcont.id = 'winpop_outer';
	if(isSpecial)
		popcont.className = 'winpop_special';
    popcont.innerHTML = '<p style="font:1em Arial, Helvetica;"><img src="/extensions/wikihow/rotate.gif"/></p>';	//temp text
	if(isSpecial)
		popcont.style.width = specialWidth +'px';
	else
	    popcont.style.width = regularWidth +'px';
    
    theBody.appendChild(popcont);
    theBody.appendChild(popmask);


	//alert(winWidth + ", " + w + ", " + marginLeft + "\n" + winHeight + ", " + h + ", " + marginTop);
    getContent(url,'winpop_outer',w,h, isSpecial, onloadFunc);

	if (!resized) {
		try {
			if (window.attachEvent) {
				window.attachEvent('resize', resizeModal);
			} else if (window.addEventListener)  {
				window.addEventListener('resize', resizeModal, false);
			} else if (document.addEventListener)  {
				document.addEventListener('resize', resizeModal, false);
			}
		} catch (e) {
		}
		resized= true;
	}
}

function resizeModal() {
	var winWidth = 0;
	var winHeight = 0;
	if (navigator.userAgent.indexOf('MSIE') > 0) {
		winWidth = document.documentElement.clientWidth;
		winHeight = document.documentElement.clientHeight;
	} else {
		winWidth = window.innerWidth;
		winHeight = window.innerHeight;
	}
	//alert("available height: " + winHeight);
	if (winWidth == 0 || winHeight == 0) 
		return;
	
	if(winPopH + extraHeight > winHeight)
		winPopH = winHeight - extraHeight;
	var padding = (winWidth - winPopW)  / 2;	
	var marginLeft = Math.round(padding / winWidth * 100);	
	var ratio = padding / winWidth;
	var padding = (winHeight - winPopH)  / 2;	
	var ratio = padding / winHeight;
	//var marginTop  = Math.round(ratio  * 100);	
	var marginTop = Math.round(100*((winHeight - winPopH - extraHeight)/winHeight)/2);
	if (winPopH > winHeight - 100) 
		marginTop = 0;
	//alert(winWidth + ", " + winPopW + ", " + marginLeft + "\n" + winHeight + ", " + winPopH + ", " + marginTop);
	//alert("resetting height to : " + winPopH);
	document.getElementById('winpop_inner').style.height = winPopH + "px";
	//setStyle(popcont, 'left: ' + marginLeft + '%; top: ' + marginTop + '%;'); 
}

function getAvailableHeight(){
	if (navigator.userAgent.indexOf('MSIE') > 0) {
		return document.documentElement.clientHeight;
	} else {
		return window.innerHeight;
	}	
}


function getRequestObject() {
    http_request = false;
    if (window.XMLHttpRequest) { // Mozilla, Safari,...
        http_request = new XMLHttpRequest();
        if (http_request.overrideMimeType) {
            http_request.overrideMimeType('text/html');
        }
    } else if (window.ActiveXObject) { // IE
        try {
            http_request = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try {
                http_request = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {}
        }
    }
	return http_request;
}
function getContent(url,divID,w,h, isSpecial, onloadFunc) {
	//alert("getting content " + w + " " + h);
	availableHeight = getAvailableHeight();
	//alert((h+extraHeight) + " " + availableHeight);
	if(parseFloat(h) + extraHeight > availableHeight)
		h = availableHeight - extraHeight;
	//alert("getting content2 " + w + " " + h + " " + availableHeight);
	winPopH = parseFloat(h); 
	//winPopW = w;
	winPopW = isSpecial?specialWidth:regularWidth;
    
    //add random parameter to prevent caching
    if (url.indexOf("?") == -1)
        url += "?";
    else
        url += "&"; 
    url += "rpsc="+new Date().getTime();
    
	http_request = getRequestObject();
    if (!http_request) {
        alert('Giving up :( Cannot create an XMLHTTP instance');
        return false;
    }
//	url = 'http://redesign_sc.wikidiy.com/Special:Importvideo?popup=true&target=Write-a-Light-Novel&rpsc=1378442466572';
    http_request.open("GET", url, true);
    http_request.onreadystatechange = function() { getWinPopData(http_request,divID,winPopW,h, onloadFunc); };
    http_request.send(null);
}


function postForm(url, name, method) {
	var params = "";
	var form = false;
	var e = document.getElementById('winpop_inner');
   	var forms = e.getElementsByTagName("FORM");
    for (i=0; i < forms.length; i++) {
        if (forms[i].name == name) {
			form = forms[i];
			break;
		}
    }
	method = method.toUpperCase();
	for (i=0; i< form.elements.length; i++) {
		name = form.elements[i].name;
		value= form.elements[i].value;
		params += name + "=" + encodeURIComponent(value) + "&";
	}
	http_request = getRequestObject();
    if (!http_request) {
        alert('Giving up :( Cannot create an XMLHTTP instance');
        return false;
    }
    http_request.onreadystatechange = function() { getWinPopData(http_request,'winpop_outer',winPopW,winPopH); };
	if (method === 'GET'){
		url += "?" + params;
	}
    http_request.open(method, url, true);
	if (method === 'POST'){
		http_request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		http_request.setRequestHeader("Content-length", params.length);
    	http_request.send(params);
	} else {
    	http_request.send(null);
	}	
	return false;
}

function runCode() {
	var e = document.getElementById('winpop_inner');
	var scripts = e.getElementsByTagName('script');
	for (i = 0; i < scripts.length; i++) {
		if( scripts[i].innerHTML != "") {
			eval(scripts[i].innerHTML);
		}
	}
} 
function replaceLinks() {
	var e = document.getElementById('winpop_inner');
    var links = e.getElementsByTagName('A');
    for (i=0; i < links.length; i++) {
		if (links[i].href.indexOf("javascript:") == 0) continue;
		if (links[i].href.indexOf(wgServer) >= 0)
			links[i].href="javascript:getContent('" +  links[i].href + "', 'winpop_outer', " + winPopW + ", " + winPopH + ");";
		else
			links[i].target='new';
    }
	var forms = e.getElementsByTagName("FORM");
	for (i=0; i < forms.length; i++) {
		forms[i].setAttribute('onsubmit', "return postForm('" + forms[i].action + "', '" + forms[i].name + "', '" + forms[i].method + "');");
	}
	window.setTimeout(runCode, 500);
}
function getWinPopData(http_request,divID,w,h, onloadFunc) {
    if (http_request.readyState==4) {
        if (http_request.status == 200) {
			//alert("making height: " + h);
            var html = '' +  
            '<a hrer="#" id="winpop_close" onclick="closeModal();" />Close</a>' +
            '<div id="winpop_inner">' +
            http_request.responseText + '</div>';
			// use $(divID).update(html) instead of $(divID).innerHTML = html
			// because update() runs any inline JS
            var div = document.getElementById(divID);
            if (typeof div.update == 'function') {
                div.update(html);
            } else {
                div.innerHTML = html;
            }
			if (replacelinks) {
				window.setTimeout(replaceLinks, 500);
			}
			resizeModal();
			if (typeof onloadFunc !== 'undefined' && onloadFunc) onloadFunc();
        }
        else {
            alert('There was a problem with the request.');
        }
    }	
}



function throwit() {
    var word = document.getElementById("importvideo_comment").value;
	window.top.document.videouploadform.description.value = word;
    closeModal();
	window.top.document.videouploadform.submit();
}

function closeit() {
    closeModal();
    window.top.document.videouploadform.submit();
}

function closeModal() {
    var theBody = document.getElementsByTagName('BODY')[0];
    var theOverlay = document.getElementById('winpop_overlay');
    var thePopped = document.getElementById('winpop_outer');
    
    theBody.removeChild(theOverlay);
    theBody.removeChild(thePopped);
	try {
    	if (window.detachEvent) {
        	window.detachEvent('resize', 'resizeModal');
    	} else if (window.removeEventListener)  {
        	window.removeEventListener('resize', 'resizeModal', false);
   	 	} else if (document.removeEventListener)  {
       	 	document.removeEventListener('resize', 'resizeModal', false);
    	}
	} catch (e) {

	}

}
