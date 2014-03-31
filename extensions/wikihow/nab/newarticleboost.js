
var nap_editUrl;
var nap_preview = false;
var needToConfirm = true;

function nap_editClick(url) {
	var strResult;
	nap_editUrl = url;
	$('#article_contents')
		.html('<b>Loading...</b>')
		.attr('onDblClick', '');
	$('#editButton').hide();

	// make sure this can't be clicked twice
	if ($('#article_contents').find('textarea').length > 0) {
		return false;
	}

	// document.write() call happens in the REST call below, so
	// we hack to override this
	document.write = function() {};

	$.get(url, function (data) {
		$('#article_contents').html(data);
		//document.editform.target = "_blank";
		//restoreToolbarButtons();
		$('#wpPreview')
			.unbind('click')
			.click( function() {
				nap_preview = true;
			});
		$('#wpSave')
			.unbind('click')
			.click( function() {
				nap_preview = false;
			});
		document.editform.setAttribute('onsubmit', 'return nap_SubmitForm();');
		document.editform.wpTextbox1.focus();
		$('#wpSummary').val(gAutoSummaryText);
		window.onbeforeunload = confirmExit;
	});

	return false;
}

function nap_clearEditForm() {
	$('#article_contents').html('Article saved.');
}

function nap_SubmitForm() {
	var parameters = "";
	for (var i=0; i < document.editform.elements.length; i++) {
   		var element = document.editform.elements[i];
		if (parameters != "") {
			parameters += "&";
		}
	
		if ( (element.name == 'wpPreview' && nap_preview) || (element.name == 'wpSave' && !nap_preview)) {
			parameters += element.name + "=" + encodeURIComponent(element.value);
		} else if (element.name != 'wpDiff' && element.name != 'wpPreview' && element.name != 'wpSave' && element.name.substring(0,7) != 'wpDraft')  {
			if (element.type == 'checkbox') {
				if (element.checked) {
					parameters += element.name + "=1";
				}
			} else {
				parameters += element.name + "=" + encodeURIComponent(element.value);
			}
		}
	}

	$.post(nap_editUrl + "&action=submit", parameters,
		function (data) {
			$('#article_contents')
				.html(data)
				.attr('style', '')
				.attr('onDblClick', 'nap_editClick("' + nap_editUrl + '");');
			$('#editButton').attr('style', '');
			if (nap_preview) {
				var previewButton = document.getElementById('wpPreview');
				previewButton.setAttribute('onclick', 'nap_preview=true;');
				var saveButton = document.getElementById('wpSave');
				saveButton.setAttribute('onclick', 'nap_preview=false;');
				document.editform.setAttribute('onsubmit', 'return nap_SubmitForm();');
				document.editform.wpTextbox1.focus();
			} else {
				nap_getDiffLink();
			}

		});
	window.onbeforeunload = null;

	return false; // block sending the forum
}

function nap_Merge(title) {
	document.nap_form.template3_merge.checked = 1;
	document.nap_form.param3_param1.value=title.replace(/&#39;/, "'");
	document.nap_form.param3_param1.focus();
}

function nap_Dupe(title) {
	document.nap_form.template4_nfddup.checked = 1;
	document.nap_form.param4_param1.value = title.replace(/&#39;/, "'");
	document.nap_form.param4_param1.focus();
}

function nap_onlyDup1() {
	if (document.nap_form.template4_nfddup.checked == 1) {
		document.nap_form.template1_nfd.checked = 0;
	}
}
function nap_onlyDup2() {
	if (document.nap_form.template1_nfd.checked == 1) {
		document.nap_form.template4_nfddup.checked = 0;
	}
}

function checkNap() {
	// check existence of dup article
	if (document.nap_form.template4_nfddup.checked) {
		api_url = "http://" + window.location.hostname + "/api.php"
		params = "action=query&format=xml&titles=" + encodeURIComponent(document.nap_form.param4_param1.value);
		$.post(api_url, params,
			function (data) {
				if (data.indexOf("pageid=") < 0) {
					alert("Oops!  The title, \"How to " + document.nap_form.param4_param1.value + "\", doesn't match any articles.  Capitalization and spelling must match perfectly.  \n\nCan you fix this and resubmit it?");
				}
			},
			'xml');
	}
	return true;
}

function confirmExit() {
	if (needToConfirm) {
		return gChangesLost;
	}
	return '';
}

function nap_cCheck() {
	$('#nap_copyrightresults').html("<center><img src='/extensions/wikihow/rotate.gif'></center>"); 
	$.get(nap_cc_url, function(data) {
		$('#nap_copyrightresults').html(data);
	});
}

function nap_MarkRelated(id, p1, p2) {
	url = "http://" + window.location.hostname + "/Special:Markrelated?p1=" + p1 + "&p2=" + p2;
	$.get(url, function() {
		$("#mr_" + id)
			.fadeOut(400, function() {
				$("#mr_" + id)
					.html("<b>Done!</b>")
					.fadeIn();
			});
	});
}

function nap_copyVio(url) {
	document.nap_form.template5_copyvio.checked =true;
	document.nap_form.param5_param1.value = url;
	document.nap_form.param5_param1.focus();
	return false;
}

function nap_getDiffLink() {
	var target = document.nap_form.target.value;
	var url = "http://" + window.location.hostname + "/api.php?action=query&prop=revisions&titles=" + encodeURIComponent(target) + "&rvlimit=20&rvprop=timestamp|user|comment|ids&format=json";
	var pageid = document.nap_form.page.value;
	$.get(url, 
		function (data) {
			var first = data.query.pages[pageid].revisions[0].revid;
			var last = null;
			for (i = 1; i < data.query.pages[pageid].revisions.length; i++) {
				var rev = data.query.pages[pageid].revisions[i];
				if (rev.user != wgUserName) {
					last = rev.revid;
					break;
				}
			}
			$('#article_contents').append('<center><b><a href="/index.php?title=' +  encodeURIComponent(target) + '&diff=' + first + '&oldid=' + last +'" target="_blank">Link to Diff</a></center></b>');
		},
		'json');
}

function checkNewbieFlush() {
	if (confirm("Are you sure you want to clear the newbie queue?")) {
		document.location = "/Special:Newarticleboost?newbie=1&flushnewbie=1&flushlimit=" + $("#newbieflush_limit").val();
	}
}

function showDialog(url) {
	url = url.replace(/http:\/\//, '');
	url = url.replace(/\/.*/g, '');
	if (!url || url == 'www.wikihow.com') {
		// Do nothing for empty and wikihow links
		return false;
	}

	var url1 = url;
	var parts = url1.split(".").reverse();
	var url2 = parts[1] + "." + parts[0];
	
	var html = "<div style='font-size: 1.3em;'>Pick the domain you would like to blacklist: <br/><br/>";
	html += "<input type='radio' url' name='url' value='" + url1 + "'> " + url1 + "<br/>";
	html += "<input type='radio' name='url' value='" + url2  + "'> " + url2 + "<br/>";
	html += "<br/>";
	html += "<input type='button' id='spambutton' value='Save' style='float: right;'>";
	html += "</div>";

	$("#dialog-box")
		.html(html)
		.dialog({ 
			modal: true,
			title: 'Add to spam',
			closeText: 'Close',
			height: 200,
			width: 400
		});
	$("#spambutton").click(
		function() {
			var url = $('input:radio[name=url]:checked').val();
			url = url.replace(/\./g, '\\.');
			url = '\\b' + url;
			$("#dialog-box").html("<center><img src='/extensions/wikihow/rotate.gif'></center>");
			$.post('/Special:SpamDiffTool', 
				{ newurls: url,
				  confirm: true },
				function(data) {
					$("#dialog-box").dialog('close');
				}
			);
		}
	);

}

$(document).ready(function() {

	$("#article_contents a").each( function() {
		var href = $(this).attr('href');
		if (href) {
			$(this).click(function() {
				showDialog($(this).attr('href'));
				return false;
			});
		}
	});

});

