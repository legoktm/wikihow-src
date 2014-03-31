/* JavaScript for Drafts extension */

/* Classes */

var wgAjaxSaveDraft = {};

// Fields

wgAjaxSaveDraft.inprogress = false;
wgAjaxSaveDraft.insync = true;
wgAjaxSaveDraft.autosavetimer = null;
wgAjaxSaveDraft.autosavewait = null;

// Actions

wgAjaxSaveDraft.save = function() {
	wgAjaxSaveDraft.call(
		document.editform.wpDraftToken.value,
		document.editform.wpEditToken.value,
		document.editform.wpDraftID.value,
		document.editform.wpDraftTitle.value,
		document.editform.wpSection.value,
		document.editform.wpStarttime.value,
		document.editform.wpEdittime.value,
		document.editform.wpTextbox1.scrollTop,
		document.editform.wpTextbox1.value,
		document.editform.wpSummary.value,
		document.editform.wpMinoredit.checked ? 1 : 0
	);

	// Ensure timer is cleared in case we saved manually before it expired
	clearTimeout( wgAjaxSaveDraft.autosavetimer );
}


wgAjaxSaveDraft.request = null;
wgAjaxSaveDraft.text = ""; 

wgAjaxSaveDraft.buildWikihowArticleResponseHandler = function() {
    if ( wgAjaxSaveDraft.request.readyState == 4) {
        if ( wgAjaxSaveDraft.request.status == 200) {
			wgAjaxSaveDraft.text = wgAjaxSaveDraft.request.responseText;
			alert("From inside the box...: " + wgAjaxSaveDraft.text);
		}
	}
}

wgAjaxSaveDraft.save_guided = function() {

	checkMinLength = false;
	checkForm();
	checkMinLength = true;

	// setu p text
    var parameters = "";
    for (var i=0; i < document.editform.elements.length; i++) {
        var element = document.editform.elements[i];
        if (parameters != "") {
            parameters += "&";
        }
    
        parameters += element.name + "=" + encodeURIComponent(element.value);
    }       
    try {
        wgAjaxSaveDraft.request = new XMLHttpRequest();
    } catch (error) {
        try {
            wgAjaxSaveDraft.request = new ActiveXObject('Microsoft.XMLHTTP');
        } catch (error) {
            return false;
        }
    }
	var url = "http://" + window.location.hostname + "/Special:BuildWikihowArticle";
    wgAjaxSaveDraft.request.open('POST', url, false);
    wgAjaxSaveDraft.request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    wgAjaxSaveDraft.request.send(parameters);
	wgAjaxSaveDraft.text = wgAjaxSaveDraft.request.responseText;

    wgAjaxSaveDraft.call(
        document.editform.wpDraftToken.value,
        document.editform.wpEditToken.value,
        document.editform.wpDraftID.value,
        document.editform.wpDraftTitle.value,
        document.editform.wpSection.value,
        document.editform.wpStarttime.value,
        document.editform.wpEdittime.value,
		0,
		wgAjaxSaveDraft.text,
        document.editform.wpSummary.value,
        document.editform.wpMinoredit.checked ? 1 : 0
    );

    // Ensure timer is cleared in case we saved manually before it expired
    clearTimeout( wgAjaxSaveDraft.autosavetimer );
}

wgAjaxSaveDraft.change = function() {
	wgAjaxSaveDraft.insync = false;
	wgAjaxSaveDraft.setControlsUnsaved();

	// Clear if timer is pending
	if( wgAjaxSaveDraft.autosavetimer ) {
		clearTimeout( wgAjaxSaveDraft.autosavetimer );
	}
	// Set timer to save automatically
	if( wgAjaxSaveDraft.autosavewait && wgAjaxSaveDraft.autosavewait > 0 ) {
		//XXCHANGED
		wgAjaxSaveDraft.autosavetimer = setTimeout(
			isGuided ? wgAjaxSaveDraft.save_guided :  wgAjaxSaveDraft.save,
			wgAjaxSaveDraft.autosavewait * 1000
		);
	}
}

wgAjaxSaveDraft.setControlsSaved = function() {
	document.editform.wpDraftSave.disabled = true;
	document.editform.wpDraftSave.className += ' disabled ';
	document.editform.wpDraftSave.value = document.editform.wpMsgSaved.value;
	document.editform.wpDraftSave.style.backgroundPosition = "0 -78px";
}
wgAjaxSaveDraft.setControlsUnsaved = function() {
	document.editform.wpDraftSave.disabled = false;
	document.editform.wpDraftSave.className = document.editform.wpDraftSave.className.replace(' disabled',''); //removed space after disabled b/c FF 3.0 doesn't include that space
	document.editform.wpDraftSave.value = document.editform.wpMsgSaveDraft.value;
}
wgAjaxSaveDraft.setControlsError = function() {
	document.editform.wpDraftSave.disabled = true;
	document.editform.wpDraftSave.className += ' disabled ';
	document.editform.wpDraftSave.value = document.editform.wpMsgError.value;
	document.editform.wpDraftSave.style.backgroundPosition = "0 -78px";
}

// Events

wgAjaxSaveDraft.onLoad = function() {
	// Check to see that the form and controls exist
	if ( document.editform && document.editform.wpDraftSave ) {
		// Handle saving
		var handler = isGuided ? wgAjaxSaveDraft.save_guided :  wgAjaxSaveDraft.save;
		addHandler(document.editform.wpDraftSave, 'click',handler); 
		
		// Detect changes
		//XXX CHANGED
		if (document.editform.wpTextbox1) {
			addHandler(document.editform.wpTextbox1, 'keypress', wgAjaxSaveDraft.change);
			addHandler(document.editform.wpTextbox1, 'paste', wgAjaxSaveDraft.change);
			addHandler(document.editform.wpTextbox1, 'cut', wgAjaxSaveDraft.change);
		} else {
			// GUIDED HANDLERS
			// var gT = new Array("steps", "tips", "warnings"); 
			var gT = new Array("summary", "ingredients", "steps", "tips", "warnings", "thingsyoullneed", "related", "sources"); 
			for (var i = 0; i < gT.length; i++) {
				addHandler(document.editform[gT[i]], 'keypress', wgAjaxSaveDraft.change);
				addHandler(document.editform[gT[i]], 'paste', wgAjaxSaveDraft.change);
				addHandler(document.editform[gT[i]], 'cut', wgAjaxSaveDraft.change);
			}	
		}
		addHandler(document.editform.wpSummary, 'keypress', wgAjaxSaveDraft.change);
		addHandler(document.editform.wpSummary, 'paste', wgAjaxSaveDraft.change);
		addHandler(document.editform.wpSummary, 'cut', wgAjaxSaveDraft.change);
		addHandler(document.editform.wpMinoredit, 'change', wgAjaxSaveDraft.change);
	
		// Use the configured autosave wait time
		wgAjaxSaveDraft.autosavewait = document.editform.wpDraftAutoSaveWait.value;
	}
}

wgAjaxSaveDraft.call = function( dtoken, etoken, id, title, section, starttime, edittime, scrolltop, text, summary, minoredit ) {
	// If in progress, exit now
	if( wgAjaxSaveDraft.inprogress )
		return;

	// Otherwise, declare we are now in progress
	wgAjaxSaveDraft.inprogress = true;

	// Perform Ajax call
	var old = sajax_request_type;
	sajax_request_type = "POST";
	sajax_do_call(
		"DraftHooks::AjaxSave",
		[ dtoken, etoken, id, title, section, starttime, edittime, scrolltop, text, summary, minoredit ],
		wgAjaxSaveDraft.processResult
	);
	sajax_request_type = old;

	// Reallow request if it is not done in 2 seconds
	wgAjaxSaveDraft.timeoutID = window.setTimeout( function() {
		wgAjaxSaveDraft.inprogress = false;
	}, 2000 );
}

wgAjaxSaveDraft.processResult = function( request ) {
	// Change UI state
	if( request.responseText > -1 ) {
		wgAjaxSaveDraft.setControlsSaved();
		document.editform.wpDraftID.value = request.responseText;
	} else {
		wgAjaxSaveDraft.setControlsError();
	}

	// Change object state
	wgAjaxSaveDraft.inprogress = false;
}

hookEvent( "load", wgAjaxSaveDraft.onLoad );
