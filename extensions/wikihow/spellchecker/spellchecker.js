

var toolURL = "/Special:Spellchecker";
var articleId = 0;
var wordArray;
var exclusionArray;
var misspell = "misspell";
var sourcesHeader = "== Sources and Citations ==";
var sourcesHeaderHtml = "<h2> <span> Sources and Citations </span></h2>";
var sourcesPlaceholder = "<SOURCES>";
var relatedHeader = "== Related wikiHows ==";
var relatedHeaderHtml = "<h2> <span> Related wikiHows </span></h2>";
var relatedPlaceholder = "<RELATED>";
var SC_STANDINGS_TABLE_REFRESH = 600;
var WIKITEXT = 1;
var HTML = 2;

$("document").ready(function() {
	// Test for old IEs
	validIE = true;
	isIE = false;
	var clientPC = navigator.userAgent.toLowerCase(); // Get client info
	if (/msie (\d+\.\d+);/.test(clientPC)) { //test for MSIE x.x;
		 validIE  = 8 <= (new Number(RegExp.$1)); // capture x.x portion and store as a number
		 isIE = true;
	}
	if (!validIE) {
		$("#spch-error").html('Error: You have an outdated browser. Please upgrade to the latest version.');
		disableTopButtons();
		$('.spch-waiting').hide();
		return;
	}

	initToolTitle();
	
	$("#bodycontents .article_inner").removeClass("article_inner");
	articleName = extractParamFromUri(window.location.href, "articleName");
	
	getNextSpellchecker(articleName);
	
	$('#spch-skip').click(function (e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {
			var id = $('#spch-id').html();
			$.get(toolURL,
				{skip: 1, id: id}, 
				function(result) {
				loadResult(result);
				},
				'json'
			);
			disableTopButtons();
		}
	});
	
	$('#spch-yes').click(function(e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {
			var id = $('#spch-id').html();
			$("#spch-preview").slideUp();
			if (!jQuery(this).hasClass('clickfail')) {
				$.get(toolURL,
				{edit: 1, 
					id: id
				},
				function (result) {
					loadEdit(result);
				},
				'json'
				);
			}
			disableTopButtons();
		}
	});
	
	//add the html for the add words dialog (appears 2 places on the page)
	$('#dialog-box').html($('#spch-words').html());
	$('#dialog-box').hide();
	
	$('#spch-add-words').click(function(e) {
		//the words have been put in
		//when the article loads
		$('#dialog-box').dialog({
		   width: 600,
		   modal: true,
		   title: 'Add Words',
		   position: 'middle',
		   closeText: 'Close',
		});
	});
	
	$('.spch-add').click(function(e) {
		e.preventDefault();
		
		//need to add "this" so we will know which 
		//dialog they're using
		addWord(this);
	})
	
});

function initToolTitle() {
	$(".firstHeading").before("<h5>" + $(".firstHeading").html() + "</h5>")
}

// asks the backend for a new article
//to edit and loads it in the page
function getNextSpellchecker(articleName) {
	$.get(toolURL,
		{getNext: true,
		 articleName: articleName
		},
		function (result) {
			loadResult(result);
		},
		'json'
	);
}

/**
 * Loads the next article into the page
 * 
 **/
function loadResult(result) {
	if (result['error'] != undefined) {
		$("#spch-error").show();
		disableTopButtons();
		$('.spch-waiting').hide();
	}
	else {
		$('spch-error').hide();
		wordArray = result['words'];
		exclusionArray = result['exclusions'];
		var articleText = wrapWords(result['html'], result['words'], result['exclusions'], HTML);
		enableTopButtons();
		$("#spch-preview").html(articleText);
		$("h1.firstHeading").html(result['title']);
		articleId = result['articleId'];
		$('#wpSave').click(function() {
			saveArticle();
		});
		$(".misspell").dblclick(function(){
			var id = $('#spch-id').html();
			$("#spch-preview").slideUp();
			if (!jQuery(this).hasClass('clickfail')) {
				$.get(toolURL,
				{edit: 1, 
					id: id
				},
				function (result) {
					if($(document).scrollTop() > 270)
						$(document).scrollTop(270);
					loadEdit(result);
				},
				'json'
				);
			}
			disableTopButtons();
		})
		$("#spch-preview").show();
		$("#spch-id").html(articleId);
		$('.spch-waiting').hide();
		
		//load in the misspelled words into the 
		//div with checkboxes.
		//put the words into the list
		checkBoxHtml = '';
		//alert(wordArray);
		for(word in wordArray) {
			checkBoxHtml += '<input type="checkbox" name="words" value="' + wordArray[word] + '" /> ' + wordArray[word] + '<br />';
		}
		$('.spch-word-list').html(checkBoxHtml);
	}
}

/**
 * 
 * Loads the html for the edit
 * (similar to the advanced editor)
 * into the page and hides the 
 * article preview
 **/
function loadEdit(result) {
	$("#spch-content").unbind("DOMNodeRemoved", handleNodeRemoved);
	
	var articleText = wrapWords(result['html'], wordArray, exclusionArray, WIKITEXT);
	$("#spch-content").html(articleText);
	$("#spch-content").attr('contenteditable', true);
	$("#spch-summary").html(result['summary']);
	$("#spch-buttons").html(result['buttons']['save'] + result['buttons']['cancel']);
	$('#wpSave').click(function() {
		saveArticle();
	});
	
	$('#spch-cancel').click(function () {
		$(".spch-waiting").show();
		$("#spch-edit").slideUp();
		getNextSpellchecker();
	});
	
	$('.spch-waiting').hide();
	$("#spch-edit").slideDown();
	initEdit();
}

/**
 *
 * If a user edits one of the misspelled
 * words, remove the misspelled
 * class (regardless of how its changed)
 * 
 **/
function OnSubtreeModified(eventObj) {
	eventObj.currentTarget.removeEventListener ('DOMSubtreeModified', OnSubtreeModified, false);
	$(eventObj.currentTarget).removeClass(misspell);
}

function initEdit() {
	$('.' + misspell).each(function(index) {
		container = $(this).get(0);
		if (container.addEventListener) {
			container.addEventListener ('DOMSubtreeModified', OnSubtreeModified, false);
		}
	});
	$("#spch-content").bind("DOMNodeRemoved", handleNodeRemoved);
}

/***
 * This deals with the broswers that user a built-in
 * spellchecker that adds a font node when the word
 * is replaced
 ***/
function handleNodeRemoved(e) {
	nodeName = e.target.nodeName.toLowerCase();
	nodeColor = e.target.attributes.getNamedItem("color").value.toLowerCase();
	if(nodeName == "font" && (nodeColor == "#ff0000" || nodeColor == "#414141")) {
		$("#spch-content").unbind("DOMNodeRemoved", handleNodeRemoved);
		var word = e.target.innerHTML;
		$(e.target).before(document.createTextNode(word));
		$(e.target).remove();
		$("#spch-content").bind("DOMNodeRemoved", handleNodeRemoved);
	}
}

function saveArticle() {
	$(".spch-waiting").show();
	$("#spch-edit").hide();
	$(window).scrollTop(0);
	checkLineBreaks();
	$.post(toolURL, {
		submitEditForm: true,
		articleId: articleId,
		wpTextbox1: jQuery("#spch-content").html(),
		wpSummary: jQuery("#wpSummary").val(),
		isIE: isIE
		},
		function (result) {
			updateStats();
			loadResult(result);
		},
		'json'
	);
	window.oTrackUserAction();
}

/***
 *
 *  This function checks to see if line breaks need
 *  to be added. Since this tool uses HTML5 editable
 *  elements, it inserts tags that assume lines breaks
 *  (such as <p>) but then when those tags are stripped
 *  by MediaWiki, the line breaks aren't preserved. So
 *  we put them in by hand.
 *
 */
function checkLineBreaks() {
	var editableDiv = document.getElementById("spch-content");
	
	if(editableDiv.childNodes.length > 1) {
		//there's a new child node, so we need to make
		//sure enough line breaks are there
		for(var i = 1; i < editableDiv.childNodes.length; i++) {
			previous = editableDiv.childNodes[i-1];
			if(previous.childNodes.length > 0) {
				if(previous.childNodes[previous.childNodes.length - 1].nodeName.toLowerCase() != "br") {
					//need to insert a break 
					$(previous).append("<br >");
				}
			}
		}
	}
}

function updateStats(){
	var statboxes = '#iia_stats_today_spellchecked,#iia_stats_week_spellchecked,#iia_stats_all_spellchecked,#iia_stats_group';
	$(statboxes).each(function(index, elem) {
			$(this).fadeOut(function () {
				var cur = parseInt($(this).html());
				$(this).html(cur + 1);
				$(this).fadeIn();
			});
		}
	);
}

//not in use yet
function checkReferences() {
	//look for ref tags that are of the format 
	// <ref ... /> not <ref ...>...</ref>
	
	var regString = "@<ref [^>]*/>@";
	var myExp = new RegExp(regString, "g");
	var replace = function($0) {
		if($0.search(/^[A-Z]*$/) >= 0) {
			//all caps so see if its in the exclusion list
			for(var i = 0; i < exclusions.length; i++) {
				if(exclusions[i] == $0)
					return $0;
			}
		}

		return "<span class='" + misspell + "'>" + $0 + "</span>";
	};
	
	articleText = articleText.replace(/([^[]+)((?:\[[^\]]+\])*)/g, function($0, $1, $2) {
		return $1.replace(myExp, replace) + ($2 || "");
	});
}

function wrapWords(articleText, words, exclusions, textFormat) {
	//first store and capture all the images in the article before messing with anything
	//only need to do this for HTML
	if(textFormat == HTML) {
		var div = $('<div></div>');
		$(div).html(articleText);
		var imageText = new Array();
		var images = $(div).find(".mwimg").each(function(index){
			imageText[index] = $(this).html();
		});
	}
	
	//remove sources section so that words in there don't get marked
	var sourcesText = "";
	if(textFormat == WIKITEXT) {
		var sourcesLoc = articleText.indexOf(sourcesHeader);
		if(sourcesLoc != -1) {
			var nextLoc = articleText.indexOf("== ", sourcesLoc + sourcesHeader.length);
			if(nextLoc == -1) {
				sourcesText = articleText.substring(sourcesLoc);
				articleText = articleText.substring(0, sourcesLoc) + sourcesPlaceholder;
			}
			else {
				sourcesText = articleText.substring(sourcesLoc, nextLoc);
				articleText = articleText.substring(0, sourcesLoc) + sourcesPlaceholder + articleText.substring(nextLoc);
			}

		}
	}else if(textFormat == HTML) {
		var sourcesLoc = articleText.indexOf(sourcesHeaderHtml);
		if(sourcesLoc != -1) {
			var nextLoc = articleText.indexOf("== ", sourcesLoc + sourcesHeaderHtml.length);
			if(nextLoc == -1) {
				sourcesText = articleText.substring(sourcesLoc);
				articleText = articleText.substring(0, sourcesLoc) + sourcesPlaceholder;
			}
			else {
				sourcesText = articleText.substring(sourcesLoc, nextLoc);
				articleText = articleText.substring(0, sourcesLoc) + sourcesPlaceholder + articleText.substring(nextLoc);
			}

		}
	}
	
	//remove related articles section so that words in there don't get marked
	var relatedText = "";
	if(textFormat == WIKITEXT) {
		var relatedLoc = articleText.indexOf(relatedHeader);
		if(relatedLoc != -1) {
			var nextLoc = articleText.indexOf("== ", relatedLoc + relatedHeader.length);
			if(nextLoc == -1) {
				relatedText = articleText.substring(relatedLoc);
				articleText = articleText.substring(0, relatedLoc) + relatedPlaceholder;
			}
			else {
				relatedText = articleText.substring(relatedLoc, nextLoc);
				articleText = articleText.substring(0, relatedLoc) + relatedPlaceholder + articleText.substring(nextLoc);
			}

		}
	}
	else if(textFormat == HTML) {
		var relatedLoc = articleText.indexOf(relatedHeaderHtml);
		if(relatedLoc != -1) {
			var nextLoc = articleText.indexOf("== ", relatedLoc + relatedHeaderHtml.length);
			if(nextLoc == -1) {
				relatedText = articleText.substring(relatedLoc);
				articleText = articleText.substring(0, relatedLoc) + relatedPlaceholder;
			}
			else {
				relatedText = articleText.substring(relatedLoc, nextLoc);
				articleText = articleText.substring(0, relatedLoc) + relatedPlaceholder + articleText.substring(nextLoc);
			}

		}
	}
	
	//wrap all instances of the word with a misspell class
	for (var i = 0; i < words.length; i++){
		var regString = "\\b" + words[i] + "\\b";
		var myExp = new RegExp(regString, "g");
		var excluded = false;
		var replace = function($0) {
			if($0.search(/^[A-Z]*$/) >= 0) {
				//all caps so see if its in the exclusion list
				for(var i = 0; i < exclusions.length; i++) {
					if(exclusions[i] == $0)
						return $0;
				}
			}
			
			return "<span class='" + misspell + "'>" + $0 + "</span>";
		};

		articleText = articleText.replace(/([^[]+)((?:\[[^\]]+\])*)/g, function($0, $1, $2) {
			return $1.replace(myExp, replace) + ($2 || "");
		});
	}
	
	//put back in the related articles section if we took one out
	if(relatedLoc != "") {
		articleText = articleText.replace(relatedPlaceholder, relatedText);
	}
	
	//put back in the sources section if we took one out
	if(sourcesText != "") {
		articleText = articleText.replace(sourcesPlaceholder, sourcesText);
	}
	
	//now undo all images b/c we don't want any changes to them
	//only if its html
	if(textFormat == HTML) {
		var newDiv = $('<div></div>');
		$(newDiv).html(articleText);
		$(newDiv).find(".mwimg").each(function(index){
			$(this).html(imageText[index]);
		})

		articleText = $(newDiv).html();
	}

	return articleText;
}

function wrapCallback(match) {
	return "<span class='misspell'>" + match + "</span>";
}

function disableTopButtons() {
	//disable edit/skip choices
	$('#spch-yes').addClass('clickfail');	
	$('#spch-skip').addClass('clickfail');
}

function enableTopButtons() {
	//disable edit/skip choices
	$('#spch-yes').removeClass('clickfail');	
	$('#spch-skip').removeClass('clickfail');
}

function clearMisspellings(word) {
	
	//delete the misspellings from the edit window
	$("#spch-content .misspell").each(function(){
		//alert($(this).html() + " " + word);
		if($(this).html() == word){
			$(this).removeClass('misspell');
		}
	})
	
	//delete the misspellings from the preview window
	$("#spch-preview .misspell").each(function(){
		//alert($(this).html() + " " + word);
		if($(this).html() == word){
			$(this).removeClass('misspell');
		}
	})
}

/**
 * Adds a word to the custom dictionary.
 * Paremeter "elem" is the specific "add"
 * button that was clicked. It is used to
 * determine which add word field was used
 * to trigger this function (there are 2 
 * different places to add words)
 * 
 */
function addWord(elem){
	var addWords = new Array();
	$(elem).parent().find('.spch-word-list input').each(function(){
		if(this.checked) {
			addWords.push(this.value);
		}
	});
	//var word = $(elem).parent().find('.spch-word').val();
	if(addWords.length > 0) {
		$(elem).parent().find(".spch-message").html("");
		
		//submit word to backend for addition to personal dictionary
		$.post(toolURL, {
		addWords: true,
		words: addWords
		},
		function (result) {
			if(result.success) {
				$(elem).parent().find(".spch-message").html(addWords + " will be added to the dictionary within the hour");
				clearMisspellings(word);
			}
			else {
				$(elem).parent().find(".spch-message").html(addWords + " cannot be added to the dictionary");
			}
			$(elem).parent().find('.spch-word').val("");
		},
		'json'
	);
	}
}

updateStandingsTable = function() {
    var url = '/Special:Standings/SpellcheckerStandingsGroup';
    jQuery.get(url, function (data) {
        jQuery('#iia_standings_table').html(data['html']);
    },
	'json'
	);
	$("#stup").html(SC_STANDINGS_TABLE_REFRESH / 60);
	//reset timer
	window.setTimeout(updateStandingsTable, 1000 * SC_STANDINGS_TABLE_REFRESH);
}

window.setTimeout(updateWidgetTimer, 60*1000);
window.setTimeout(updateStandingsTable, 1000 * SC_STANDINGS_TABLE_REFRESH);

function updateWidgetTimer() {
    updateTimer('stup');
    window.setTimeout(updateWidgetTimer, 60*1000);
}
