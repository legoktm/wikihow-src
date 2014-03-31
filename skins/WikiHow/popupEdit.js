
function initPopupEdit(editURL) {
	document.getElementById('editModalPage').style.display = 'block';
	nap_editClick(editURL);
	return false;
}

function popupEditClose() {
	document.getElementById('editModalPage').style.display = 'none';
	return false;
}


var nap_request; 
var nap_cc_request; 
var nap_editUrl;
var nap_preview = false;
var nap_close = false;
var needToConfirm = true;

function cleanForPopup(windowText) {

			windowText = windowText.replace(/<span class='editHelp'>.*opens in new window\)<\/span>/g, '<input type="button" value="Cancel"  onclick="popupEditClose();return false;">');
			windowText = windowText.replace(/<a href.*?>Guided Editing<\/a>/g, '');
			windowText = windowText.replace(/<input id='weave_button'.*?>/g, '');
			windowText = windowText.replace(/<input id='fixcaps_button'.*?>/g, '');
			windowText = windowText.replace(/<input id="wpDiff".*?\/>/g, '');
			windowText = windowText.replace(/<input name="wpMinoredit".*?>This is a minor edit<\/label>/g, '');
			windowText = windowText.replace(/<input name="wpWatchthis".*?>Watch<\/label>/g, '');
	
	return(windowText);
}

function nap_Handler() {
	if ( nap_request.readyState == 4) {
		if ( nap_request.status == 200) {
			var ac = document.getElementById('article_contents');

			var windowText = nap_request.responseText;
			ac.innerHTML = cleanForPopup(windowText);

			var textbxid = document.getElementById('wpTextbox1');
			textbxid.rows = 20;
			textbxid.cols = 70;

			var summary = document.getElementById('wpSummary');
			summary.value = gAutoSummaryText;

			document.editform.target = "_blank";
			var previewButton = document.getElementById('wpPreview');
			previewButton.setAttribute('onclick', 'nap_preview=true;');
			var saveButton = document.getElementById('wpSave');
			saveButton.setAttribute('onclick', 'nap_preview=false;');
			document.editform.setAttribute('onsubmit', 'return nap_SubmitForm();');
			document.editform.wpTextbox1.focus();

			window.onbeforeunload = confirmExit;
		}
	}
}

function nap_editClick(url) {

	var strResult;
	nap_editUrl = url;
	var ac = document.getElementById('article_contents');
	ac.innerHTML = '<b>Loading...</b>';	
	ac.setAttribute('onDblClick', '');

	try {
		nap_request = new XMLHttpRequest();
	} catch (error) {
		try {
			nap_request = new ActiveXObject('Microsoft.XMLHTTP');
		} catch (error) {
			return false;
		}
	}
	nap_request.open('GET', url,true);
	nap_request.send(''); 
	nap_request.onreadystatechange = nap_Handler;
}

function nap_clearEditForm() {
	var ac = document.getElementById('article_contents');
	ac.innerHTML = "Article saved.";
}

function nap_processEditHandler() {
    if ( nap_request.readyState == 4) {
        if ( nap_request.status == 200) {
            var ac = document.getElementById('article_contents');
				ac.innerHTML = cleanForPopup(nap_request.responseText);

//				ac.setAttribute('style', '');
//				ac.setAttribute('onDblClick', 'nap_editClick("' + nap_editUrl + '");');

//            document.editform.target = "_blank";
//            var save = document.getElementById('wpSave');
//            document.editform.setAttribute('onsubmit', 'return nap_SubmitForm();');
//            document.editform.wpTextbox1.focus();
			if (nap_preview) {
				var textbxid = document.getElementById('wpTextbox1');
				textbxid.rows = 20;
				textbxid.cols = 67;

				ac.body.dom.scrollTop = 0;

				var previewButton = document.getElementById('wpPreview');
				previewButton.setAttribute('onclick', 'nap_preview=true;');
				var saveButton = document.getElementById('wpSave');
				saveButton.setAttribute('onclick', 'nap_preview=false;');
				document.editform.setAttribute('onsubmit', 'return nap_SubmitForm();');
				document.editform.wpTextbox1.focus();
			}
            if ( nap_close ) {
                ac.innerHTML = 'Saving...';
                nap_close = false;

                var newdiffid = document.getElementById('mw-diff-ntitle3');
                var confirmEdit = '<br\/><div style="background: yellow;"><b>'+gQuickEditComplete+'<\/b><\/div>';
                if (newdiffid) newdiffid.innerHTML = newdiffid.innerHTML+confirmEdit;

                popupEditClose();
            }
        }
    } 
}

function nap_SubmitForm() {
	var parameters = "";
	for (var i=0; i < document.editform.elements.length; i++) {
   		var element = document.editform.elements[i];
		if (parameters != "") {
			parameters += "&";
		}
		if (element.name == 'wpSave' && !nap_preview) {
			nap_close = true;
		}
	
		if ( (element.name == 'wpPreview' && nap_preview) || (element.name == 'wpSave' && !nap_preview)) {
			parameters += element.name + "=" + encodeURIComponent(element.value);
		} else if (element.name != 'wpDiff' && element.name != 'wpPreview' && element.name != 'wpSave')  {
			if (element.type == 'checkbox') {
				if (element.checked) {
					parameters += element.name + "=1";
				}
			} else {
				parameters += element.name + "=" + encodeURIComponent(element.value);
			}
		}
	}
    nap_request.open('POST', nap_editUrl + "&action=submit",true);
	nap_request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    nap_request.send(parameters);
    nap_request.onreadystatechange = nap_processEditHandler;
	window.onbeforeunload = null;
		
	//window.setTimeout(nap_clearEditForm, 1000);
	return false; // block sending the forum
}

function nap_Merge(title) {
	document.nap_form.template3_merge.checked = 1;
	document.nap_form.param3_param1.value=title;
	document.nap_form.param3_param1.focus();
}

function confirmExit() {
 	if (needToConfirm) {
		//return gChangesLost;
	}
	return '';
}

function nap_cCheck_Handler() {
    if ( nap_cc_request.readyState == 4) {
    	var ac = document.getElementById('nap_copyrightresults');
        ac.innerHTML = nap_cc_request.responseText
    }
}

function nap_cCheck() {
    
	var ac = document.getElementById('nap_copyrightresults');
    ac.innerHTML = "<center><img src='/extensions/wikihow/rotate.gif'></center>"; 
    
    try {
        nap_cc_request = new XMLHttpRequest();
    } catch (error) {
        try {
            nap_cc_request = new ActiveXObject('Microsoft.XMLHTTP');
        } catch (error) {
            return false;
        }
    }
    nap_cc_request.open('GET', nap_cc_url,true);
    nap_cc_request.send(''); 
    nap_cc_request.onreadystatechange = nap_cCheck_Handler;
}



