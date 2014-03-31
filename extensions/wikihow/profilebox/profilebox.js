var fetchStats = false;
var fetchCreated = false;
var fetchCreatedAll = false;
var fetchEdited = false;
var fetchEditedAll = false;
var fetchThumbed = false;
var fetchThumbedAll = false;
var fetchFavs = false;
var contentCreated = '';
var contentCreatedAll = '';
var contentEdited = '';
var contentEditedAll = '';
var contentThumbed = '';
var contentThumbedAll = '';
var contentStats = '';
var statsCreated = 0;
var statsEdited = 0;
var statsPatrolled = 0;
var statsContribPage = 0;

function pbInitForm() {
	return;
/*
	new Ajax.Autocompleter("pbFav1", "autocomplete_choices1", "/Special:ProfileBox?type=favsselector", {
		paramName: "pbTitle",
		afterUpdateElement : getSelectionId
	});
	new Ajax.Autocompleter("pbFav2", "autocomplete_choices2", "/Special:ProfileBox?type=favsselector", {
		paramName: "pbTitle",
		afterUpdateElement : getSelectionId
	});
	new Ajax.Autocompleter("pbFav3", "autocomplete_choices3", "/Special:ProfileBox?type=favsselector", {
		paramName: "pbTitle",
		afterUpdateElement : getSelectionId
	});
*/
}

function getSelectionId(text, li) {
	if (jQuery('#pbFav1').value == text.value) { jQuery('#fav1').value = li.id; }
	else if (jQuery('#pbFav2').value == text.value) { jQuery('#fav2').value = li.id; }
	else if (jQuery('#pbFav3').value == text.value) { jQuery('#fav3').value = li.id; }
}

function removeUserPage(profilebox_name) {
	var conf = confirm("Are you sure you want to permanently remove your "+profilebox_name+"?");
	if (conf == true) {
		var url = '/Special:ProfileBox?type=remove';
	
		jQuery.get(url, function(data) { 
			gatTrack("Profile","Remove_profile","Remove_profile");
			jQuery('#profileBoxID').hide();
			jQuery('#pb_aboutme').hide();
//			window.location.reload();
		});
	}
	return false;
}

function deleteFav( fav ) {
	if (fav == 1) {
		jQuery('#fav1').value = '';
		jQuery('#pbFav1').value = '';
	} else if (fav == 2) {
		jQuery('#fav2').value = '';
		jQuery('#pbFav2').value = '';
	} else if (fav == 3) {
		jQuery('#fav3').value = '';
		jQuery('#pbFav3').value = '';
	}
}

function pbInit() {

	//pbGetStats();

	/*if (jQuery('#profileBoxInfo')) {
		if (pbstats_check) {
			//jQuery('#profileBoxStats').height(jQuery('#profileBoxInfo').height() - 23);
		} else {
			jQuery('#profileBoxInfo').css('width', '97%');
		}
	} else {
		if (pbstats_check) {
			jQuery('#profileBoxStats').css('width','97%');
		}
	}*/

	if (pbfavs_check) {
		pbGetFavs();
	}
}

function pbTabOn(obj) {
	var obj = jQuery(obj);
	if (!obj.hasClass('selected')) {
		jQuery('#pbTab1').attr('class', '');
		jQuery('#pbTab2').attr('class', '');
		obj.addClass('selected');

		if (obj.attr('id') == 'pbTab1') {
			pbShow_articlesCreated();
		} else if (obj.attr('id') == 'pbTab2') {
			pbShow_articlesEdited();
		} 
	}
}

function pbGetStats() {
	if (!fetchStats) {
		var url = '/Special:ProfileBox?type=ajax&element=stats&pagename=' +encodeURIComponent(wgPageName);

		jQuery.get(url, function(data) { 
			//var json = data.evalJSON(true);
			var json = eval("(" + data + ")");

			contentStats = json.created + ' Articles Started<br />';
			contentStats += json.edited + " <a href='"+json.contribpage + "'>Article Edits</a><br />";
			if( parseInt(json.patrolled) > 0)
				contentStats += json.patrolled + " <a href='/index.php?title=Special%3ALog&type=patrol&user="+profilebox_username+"'>Edits Patrolled</a><br />";
			if (json.thumbs_given != undefined && json.thumbs_received != undefined) {
				if (parseInt(json.thumbs_given) > 0) {
					contentStats += json.thumbs_given + ' thumbs up given<br />';
				}
				if (parseInt(json.thumbs_received) > 0) {
					contentStats += json.thumbs_received + ' thumbs up received<br />';
				}
			}
			contentStats += "" + json.viewership + ' views<br />';
			//contentStats += "<a href='"+json.contribpage + "'>"+msg_contributions+"</a><br />";
			contentStats += "<div style='clear:both;'></div>";

			if (pbstats_check) {
				jQuery('#profileBoxStatsContent').html(contentStats);
			}

			if (pbstartededited_check) {
				jQuery('#pbTab1').append(' ('+ json.created +')');
				jQuery('#pbTab2').append(' ('+ json.edited +')');
			}

			fetchStats = true;
		});
	}
}

function pbGetCreated() {
	if (!fetchCreated) {
		var url = '/Special:ProfileBox?type=ajax&element=created&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) { 
			contentCreated = data; 
			//contentCreated += "<span style='float:right;'><a onclick='pbShow_articlesCreated(\"more\"); return false;' href='#'>"+msg_edited_more+"</a></span>";
			jQuery('#created_more').show();
			jQuery('#created_less').hide();
			jQuery('#pb-created tbody').html(contentCreated);
			//fetchCreated = true;
		});
	}
}

function pbGetThumbed() {
	if (!fetchThumbed) {
		var url = '/Special:ProfileBox?type=ajax&element=thumbed&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) { 
			contentThumbed = data; 
			jQuery('#thumbed_more').show();
			jQuery('#thumbed_less').hide();
			jQuery('#pb-thumbed tbody').html(contentThumbed);
			fetchThumbed = true;
		});
	}
	else {
			jQuery('#thumbed_more').show();
			jQuery('#thumbed_less').hide();
			jQuery('#pb-thumbed tbody').html(contentThumbed);
	}
}

function pbGetThumbedAll() {
	if (!fetchThumbedAll) {
		var url = '/Special:ProfileBox?type=ajax&element=thumbedall&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) { 
			contentThumbedAll = data; 
			jQuery('#thumbed_more').hide();
			jQuery('#thumbed_less').show();
			jQuery('#pb-thumbed tbody').html(contentThumbedAll);
			fetchThumbedAll = true;
		});
	}
	else {
			jQuery('#thumbed_more').hide();
			jQuery('#thumbed_less').show();
			jQuery('#pb-thumbed tbody').html(contentThumbedAll);
	}
}

function pbGetCreatedAll() {
	if (!fetchCreatedAll) {
		var url = '/Special:ProfileBox?type=ajax&element=createdall&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) { 
			contentCreatedAll = data; 
			//contentCreatedAll += "<span style='float:right;'><a onclick='pbShow_articlesCreated(); return false;' href='#'>&laquo; Less</a></span>";
			jQuery('#created_more').hide();
			jQuery('#created_less').show();
			jQuery('#pb-created tbody').html(contentCreatedAll);
			//fetchCreatedAll = true;
		});
	}
}

function pbGetEdited() {
	if (!fetchEdited) {
		var url = '/Special:ProfileBox?type=ajax&element=edited&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) {
			contentEdited = data; 
			jQuery('#edit_more').show();
			jQuery('#edit_less').hide();
			jQuery('#pb-edited tbody').html(contentEdited);
			//fetchEdited = true;
		});
	}
}

function pbGetEditedAll() {
	if (!fetchEditedAll) {
		var url = '/Special:ProfileBox?type=ajax&element=editedall&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) { 
			contentEditedAll = data; 
			jQuery('#edit_more').hide();
			jQuery('#edit_less').show();
			jQuery('#pb-edited tbody').html(contentEditedAll);
			//fetchEditedAll = true;
		});
	}
}

function pbGetFavs() {
	if (!fetchFavs) {
		var url = '/Special:ProfileBox?type=ajax&element=favs&pagename=' + wgPageName;
	
		jQuery.get(url, function(data) { 
			jQuery('#pbFavsContent').html(data); 
			fetchFavs = true ;
		});
	}
}

function pbShow_Thumbed(howmany) {
	if (howmany == 'more') {
		pbGetThumbedAll();
	} else {
		pbGetThumbed();
	}
}

function pbShow_articlesCreated(howmany) {
	if (howmany == 'more') {
		if (!fetchCreatedAll) {
			pbGetCreatedAll();
		} else {
			jQuery('#pbTabsContent').html(contentCreatedAll);
		}
	} else {
		if (!fetchCreated) {
			pbGetCreated();
		} else {
			jQuery('#pbTabsContent').html(contentCreated);
		}
	}
}

function pbShow_articlesEdited(howmany) {
	if (howmany == 'more') {
		if (!fetchEditedAll) {
			pbGetEditedAll();
		} else {
			jQuery('#pbTabsContent').html(contentEditedAll);
		}
	} else {
		if (!fetchEdited) {
			pbGetEdited();
		} else {
			jQuery('#pbTabsContent').html(contentEdited);
		}
	}
}

function pbShow_articlesRS(){
	var content = '';
	content += "<a>How to Be Awesome</a> 2000 views, Rising Star<br />";
	content += "<br />";
	jQuery('#pbTabsContent').html(content);
}


