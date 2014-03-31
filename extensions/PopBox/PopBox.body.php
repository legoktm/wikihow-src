<?

class PopBox {
	
	function getGuidedEditorButton( ) {
		global $wgUser;
		return "<a class='" . ($wgUser->getID() == 0 ? " disabled" : "") . " id='weave_button' accesskey='" .wfMessage('popbox_accesskey')->text() ."'  onclick='PopItFromGuided(); return false;' href='#' " . ($wgUser->getID() == 0 ? " disabled=\"disabled\" " : "") . ">" . wfMessage('popbox_add_related')->text() . "</a>";
	           
	}
	
	function getToolbarButton( ) {
		 return "<div class='mw-toolbar-editbutton' id='weave_button' accesskey='" .wfMessage('popbox_accesskey')->text() ."' onclick='PopIt(document.editform.wpTextbox1); return false;' title='Weave links'></div>\n"  ;	
	}
	
	function getPopBoxJSAdvanced() {
	
		$js = "
	<script type=\"text/javascript\" src=\"" . wfGetPad('/extensions/min/f/extensions/PopBox/PopBox.js?rev=') . WH_SITEREV . "\"></script>
		<script language='javascript'>
			var activeElement = null;
		</script>
		"  . PopBox::getPopBoxJS();
		return $js;
	}
	
	function getPopBoxJSGuided() {
	
		$js = "
	
	
	<script type=\"text/javascript\" src=\"" . wfGetPad('/extensions/min/f/extensions/PopBox/PopBox.js?rev=') . WH_SITEREV . "\"></script>
		<script language='javascript'>
	function blurHandler(evt) {
		//activeElement = null;
	}
	function focusHandler(evt) {
		if (!resetAccessKey && navigator.userAgent.indexOf(\"MSIE\") >= 0) {
			document.getElementById('weave_button').accessKey  = '';
		}
		resetAccessKey = true;
		var e = evt ? evt : window.event;
		if (!e) return;
		if (e.target)
			activeElement = e.target;
		else if(e.srcElement) activeElement = e.srcElement;
	}
	function loadHandler() {
		for (var j = 0; j < document.editform.elements.length; j++) {
			document.editform.elements[j].onfocus = focusHandler;
			document.editform.elements[j].onblur  = blurHandler;
		}
	}
	var activeElement = null;
	var resetAccessKey = false;
	jQuery(window).load(loadHandler);
	//window.onload = loadHandler;
	
	function PopItFromGuided() {
	//	PopIt(document.editform.steps);
		if (activeElement == null) {
			alert(\"" . wfMessage('popbox_noelement')->text() . "\");
			return;
		}
		PopIt(activeElement);
	}
	
	
			</script>
		";
		return $js . PopBox::getPopBoxJS();
	}
	
	function getPopBoxJS() {
		global $wgServer, $wgILPB_NumResults;
		return "
		<script language='javascript' type='text/javascript'>

	var response;
	var targetObj;
	var searchtext; 
	var lastKeyUpHandler;
	var requester;
	var sStart = -1;
	var sEnd = -1;

	function IEAccessKeyCheck(e) {
		if( !e ) {
			//if the browser did not pass the event information to the
			//function, we will have to obtain it from the event register
			if( window.event ) {
				//Internet Explorer
				e = window.event;
			} else {
				//total failure, we have no way of referencing the event
				return;
			}
		}

		if ((e.altKey) && (e.keyCode == 82)) {
			document.getElementById('weave_button').onclick();
		}
	}

	if (navigator.userAgent.indexOf(\"MSIE\")) {
		document.onkeyup = IEAccessKeyCheck;
	}

	function setSelectionRange(input, start, end) {
		// assumed IE
		input.focus()
		var range = input.createTextRange();
		range.collapse(true);
		range.moveStart('character', start);
		range.moveEnd('character', end - start);
		range.select();
	}
	
	function processResponse() {
		if (requester.status == 0 || requester.status == 200) {
			//var theBox = document.getElementById('popbox_inner');
			var string = '';
			string = requester.responseText;
			var arr = string.split('\\n');
			var count = 0;
			//var obj = document.getElementById(targetObj);
			var obj = targetObj;
			if (document.selection) {
				var range = document.selection.createRange();
				text =  range.text;
				if (sStart < 0) {
					if (activeElement == null  && document.getElementById('wpTextbox1'))
						activeElement = document.getElementById('wpTextbox1');
					getSelectionStartEnd(activeElement);
				}
			} else {
				text  = (obj.value).substring(obj.selectionStart, obj.selectionEnd); 
			}
			html = '<p class=\"popbox_header\">Results for ' + searchtext + ':</p><ol>';
			for (i = 0; i < arr.length && count < $wgILPB_NumResults; i++) {
				y = arr[i].replace(/^\s+|\s+$/, '');
				key = y.replace(/^http:\/\/www.wikihow.com\//, '');
				if (key == wgPageName) {
					continue;
				}
				key = key.replace(/^" . str_replace("/", "\\/", $wgServer) . "\//, '');
				x = unescape(key.replace(/-/g, ' '));
				y = x.replace(/'/g, '" . '\\\\' . '\\' . '\'' . "');
				if (y != '') {
					if (y.indexOf('Category') == 0) {
						y = ':' + y;
					}
					line = '<li><a id=\"link' + (count+1) + '\"  href=\"javascript:PopIt(); updateSummary(); insertTagsWH(targetObj, \'[[' +  y + '|\',\']]\', \'\'); \">' + x + '</a></li>\\n';
					html += line;
					count++;
				}
			}   
			
			html += '</ol>';    
			if (count == 0) {
				html += '" . wfMessage('popbox_noresults')->text() . "';
			}
			
			$('#dialog-box').html(html);
			$('#dialog-box').dialog({
				modal: true,
				width: 400,
				title: '" . wfMessage('popbox_related_articles')->text() . "',
				minHeight: 200,
				closeText: 'Close',
				buttons: [ 
							{ text: \"". wfMessage('popbox_revise')->text() . "\", click: function() { return Revise(); } },
							{ text: \"". wfMessage('popbox_nothanks')->text() . "\", click: function() { $( this ).dialog( \"close\" ); } }
						],
			});
		}
	}

	function handleResponse() {
		if (!requester) {
			alert('Error encountered.');
			return;
		}
		if (requester.readyState == 4) {
			processResponse();
		}
	}

	function updateSummary() {
		var updateText = \"" . wfMessage('popbox_editdetails')->text() ."\";
		if (updateText != '' && document.editform.wpSummary.value.indexOf(updateText) < 0) {
			if (document.editform.wpSummary.value != '') {
				document.editform.wpSummary.value += ', ';
			}
			document.editform.wpSummary.value += updateText;
		}
		return true;
	}

	function SelectLink(e) {
	  if( !e ) {
	    //if the browser did not pass the event information to the
	    //function, we will have to obtain it from the event register
	    if( window.event ) {
	      //Internet Explorer
	      e = window.event;
	    } else {
	      //total failure, we have no way of referencing the event
	      return;
	    }
	  }
	  if( typeof( e.keyCode ) == 'number'  ) {
	    e = e.keyCode;
	  } else if( typeof( e.which ) == 'number' ) {
	    e = e.which;
	  } else if( typeof( e.charCode ) == 'number'  ) {
	    e = e.charCode;
	  } else {
	    return;
	  }
	
	
		if (e >= 48 && e <= 57) {
			var i = e - 48;
			var link = document.getElementById('link' + i);
			if (link && link.href != '') {
				window.location = link.href;
				return;
			}
		} else if (e == 27) {
			PopIt(this);
		} else if (e == 86 && !document.getElementById('revise_text')) {
			//86 is v
			Revise();
		}
	}
	
	function searchFormSubmit() {
		search(document.getElementById('revise_text').value);
		return false;
	}
	function fakeSubmit(e) {
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
	        if (key == '13') {
				searchFormSubmit();
			}
	}
	function Revise() {
		var agent = navigator.userAgent.toLowerCase();
		if (document.getElementById('wpTextbox1') && ( (agent.indexOf('firefox') >= 0) || (agent.indexOf('msie 8.0') >= 0) )) {
			$('#dialog-box').html('<p id=\"popbox_inner\"><input id=\"revise_text\" type=\"text\" name=\"revise\" class=\"search_input\" onKeyUp=\"fakeSubmit(event);\" onclick=\"\" value=\"' + searchtext + '\"  class=\"search_button\" /><img src=\"" . wfGetPad('/images/a/a8/Search_button.png') . "\" onclick=\"return searchFormSubmit();\"></p>');
		} else {
			$('#dialog-box').html('<p id=\"popbox_inner\"><input id=\"revise_text\" type=\"text\" name=\"revise\" class=\"search_input\" onKeyUp=\"fakeSubmit(event);\" onclick=\"\" value=\"' + searchtext + '\"/><button onclick=\"return searchFormSubmit();\" class=\"search_button\">"  .wfMessage('popbox_search')->text() . "</button></p>');
			document.getElementById('revise_text').focus();
		}
		return false;
	}
	
	
	function search(text) {
		requester = null;
	    try {
	        requester = new XMLHttpRequest();
	    } catch (error) {
	        try {
	            requester = new ActiveXObject('Microsoft.XMLHTTP');
	        } catch (error) {
	            return false;
	        }
	    }
	    requester.onreadystatechange =  handleResponse;
	    url = '" .wfMessage('popbox_searchurl', $wgServer)->text() . "';
	    requester.open('GET', url); 
	    requester.send(' ');
		searchtext = text;
	}
	
	function PopIt(obj) {
		if (obj != null) {
			targetObj = obj;
		}
	    if (!searchtext) {	
			lastKeyUpHandler = document.onkeyup;
			document.onkeyup = SelectLink;
	    } else {
			targetObj.focus();
			document.onkeyup = lastKeyUpHandler;
			lastKeyUpHandler = '';
			if (sEnd >= 0) {
				 setSelectionRange(activeElement,sStart, sEnd);
			}
			sStart = sEnd = -1;
			$('#dialog-box').dialog('close');
			searchtext = '';
	        return;
	    }
	    var text = '';
		if (document.selection) {
			text =  document.selection.createRange().text;      
		} else {
			text  = (obj.value).substring(obj.selectionStart, obj.selectionEnd); 
	    } 
		if (text == '') {
			html = '<p id=\"popbox_inner\">"  . wfMessage('popbox_no_text_selected')->text() . "</p>';
			
			$('#dialog-box').html(html);
			$('#dialog-box').dialog({
				modal: true,
				width: 400,
				title: '" . wfMessage('popbox_related_articles')->text() . "',
				minHeight: 200,
				closeText: 'Close',
			});
			return;
		}
		search(text);
	}
	
	function findPosPopBox(obj) {
	    var curleft = curtop = 0;
	    if (obj.offsetParent) {
	        curleft = obj.offsetLeft
	        curtop = obj.offsetTop
	        while (obj = obj.offsetParent) {
	            curleft += obj.offsetLeft
	            curtop += obj.offsetTop
	        }
	    }
	    return [curleft - 480,curtop + 20];
	}
	</script>";
	}
	
	function getPopBoxCSS() {
		return "
	<style type='text/css'>
	.poplink { background-color: #FFC; }
	
	#popbox {
	    position: absolute;
	    width: 254px;
	    display: none;
	    margin: 0;
	    padding: 0;
	    background-color: transparent;
	}
	
	#dialog-box {
	    padding: 8px; 
	    margin: 0;
	}
	
	#dialog-box P#nothanks A { color: #000; }
	#dialog-box OL LI { 
		list-style: none;
		margin-bottom: 1px; 
		padding: 5px 0;
	}
	
	.popbox_header { font-weight: bold; margin-bottom: .5em; }
	</style>
	";
	}
	function getPopBoxDiv() {
		global $wgStylePath, $wgILPB_HeaderImage;
	  return "
	
	<div id='popbox'>
	    <!--div id='popbox_hdr'>" . wfMessage('popbox_related_articles')->text() . "</div-->
	    <div id='popbox_inner'></div>
	</div>	
		";
	}
}
	
