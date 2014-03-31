/*
 * Edit Finder Class
 */
var editfinder_preview = false;
var g_bEdited = false;

var EF_WIDGET_LEADERBOARD_REFRESH = 10 * 60;


function EditFinder() {
	this.m_title = '';
	this.m_searchterms = '';
}


// Init shortcut key bindings
$(document).ready(function() {
	initToolTitle();
	$(".firstHeading").after($("#editfinder_cat_header"));

	var mod = Mousetrap.defaultModifierKeys;
	Mousetrap.bind(mod + 'e', function() {$('#editfinder_yes').click();});
	Mousetrap.bind(mod + 's', function() {$('#editfinder_skip').click();});
	Mousetrap.bind(mod + 'p', function() {$('#wpSave').click();});
	Mousetrap.bind(mod + 'v', function() {$('#wpPreview').click();});
	Mousetrap.bind(mod + 'c', function() {$('#edit_cancel_btn').click();});
});

EditFinder.prototype.init = function () {
	editFinder.getArticle();
	
	//bind skip link
	jQuery('#editfinder_skip').click(function(e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {
			editFinder.disableTopButtons()
			editFinder.getArticle();
		}
	});
	
	var interests = editFinder.getEditType() == 'Topic';
	/*category choosing*/
	jQuery('.editfinder_choose_cats').click(function(e){
		e.preventDefault();
		if (interests) {
			editFinder.getThoseInterests();
		}
		else {
			editFinder.getThoseCats();
		}
	});
	
	if (interests) {
		editFinder.getUserInterests();
	}
	else {
		editFinder.getUserCats();
	}
	
}


EditFinder.prototype.getEditType = function() {
	var pathParts = window.location.pathname.split('/');
	return pathParts[pathParts.length - 1];
}

EditFinder.prototype.getThoseInterests = function() {
	jQuery('#dialog-box').html('');
	jQuery('#dialog-box').load('/Special:CatSearchUI?embed=1', function() {
		jQuery('#dialog-box').dialog({
			width: 400,
			modal: true,
			title: 'Interests',
			closeText: 'Close',
			close: function(event, ui) {
				// Only auto-show this dialog once. Use this cookie as a variable to control
				$.cookie('ef_int', '1', {expires: 365 * 10, path: '/'});
				window.location.reload();
			}
		});
	});
}

EditFinder.prototype.getThoseCats = function() {
	jQuery('#dialog-box').html('');
	var efType = editFinder.getEditType();
	jQuery('#dialog-box').load('/Special:SuggestCategories?type=' + efType, function(){
		if (efType !== '') {
			jQuery('#suggest_cats').attr('action',"/Special:SuggestCategories?type=" + efType);
		}
		jQuery('#dialog-box').dialog( "option", "position", 'center' );
		jQuery('#dialog-box td').each(function(){
			var myInput = $(this).find('input');
			var position = $(this).position();
			$(myInput).css('top', position.top + 10 + "px");
			$(myInput).css('left', position.left + 10 + "px");
			$(this).click(function(){
				editFinder.choose_cat($(this).attr('id'));
			})
		})
		jQuery('#check_all_cats').click(function(){
			var cats = jQuery('form input:checkbox');
			var bChecked = jQuery(this).prop('checked');
			for (i=0;i<cats.length;i++) {
				var catid = cats[i].id.replace('check_','');
				editFinder.choose_cat(catid,bChecked);
			}
		});
	});
	jQuery('#dialog-box').dialog({
		width: 826,
		modal: true,
		closeText: 'Close',
		title: 'Categories'
	});
}

EditFinder.prototype.choose_cat = function(key,bChoose) {
	safekey = key.replace("&", "and");
 	var e = $("#" + safekey);
	
	//forcing it or based off the setting?
	if (bChoose == null)
		bChoose = (e.hasClass('not_chosen')) ? true : false;
	
 	if (bChoose) {
 		e.removeClass('not_chosen');
 		e.addClass('chosen');
 		document.suggest_cats.cats.value += ", " + key;
		jQuery('#check_' + safekey).prop('checked', true);
 	} else {
 		e.removeClass('chosen');
 		e.addClass('not_chosen');
 		var reg = new RegExp (key, "g");
 		document.suggest_cats.cats.value = document.suggest_cats.cats.value.replace(reg, '');
		jQuery('#check_' + safekey).prop('checked', false);
		jQuery('#check_all_cats').prop('checked', false);
 	}
}

EditFinder.prototype.getArticle = function(the_id) {
	var url = '/Special:EditFinder?fetchArticle=1';
	var e = jQuery('.firstHeading a');
	if (e.html())
		url += '&skip=' + encodeURIComponent(e.html());
	var title = '';
	
	//add the edit type
	var efType = editFinder.getEditType();
	if (efType !== '') 
		url += '&edittype=' + efType;
		
	//add the article id if we need a specific one
	if (the_id) 
		url += '&id=' + the_id;
	
	jQuery('#editfinder_article_inner').fadeOut('fast');
	jQuery('#editfinder_preview').fadeOut('fast',function() {
		jQuery('#editfinder_spinner').fadeIn();
		
		jQuery.get(url, function (data) {
			var json = jQuery.parseJSON(data);
			
			aid = json['aid'];
			title = json['title'];
			aURL = json['url'];

			editFinder.display(title,aURL,aid,'editfinder_preview','intro');
		});
	});
	
	

}

// 
//
EditFinder.prototype.display = function (title, url, id, DIV, origin, currentStep) {
	this.m_title = title;
	this.m_product = 'editfinder';
	this.m_textAreaID = 'summary';
	this.m_currentStep = 0;

	// set up post- dialog load callback
	var showBox = this.m_currentStep !== 0;
	var that = this;

		
	var urlget = '/Special:EditFinder?show-article=1&aid=' + id;
	
	//add the edit type
	var efType = editFinder.getEditType();
	if (efType !== '') 
		urlget += '&edittype=' + efType;
		
	jQuery.get(urlget, function(data) {
		jQuery('#' + DIV).html(data);

		//stop spinning and show stuff
		jQuery('#editfinder_spinner').fadeOut('fast',function() {
		
			
			//fill in the blanks
			if (title == undefined) {
				editFinder.disableTopButtons();	
				titlelink = '[No articles found]';
			}
			else {
				titlelink = '<a href="'+url+'">'+title+'</a>';
				editFinder.resetTopButtons();
				jQuery('#editfinder_yes').unbind('click');
				jQuery('#editfinder_yes').click(function(e) {
					e.preventDefault();
					if (!jQuery(this).hasClass('clickfail')) {
						editFinder.edit(id);
					}
				});
			}
			jQuery(".firstHeading").html(titlelink);
			//jQuery('#editfinder_article_inner').html(titlelink);
			
			jQuery('#editfinder_article_inner').fadeIn();
			jQuery('#' + DIV).fadeIn();
		});
	});

}

EditFinder.prototype.edit = function (id,title) {
	var url = '/Special:EditFinder?edit-article=1&aid=' + id;
	
	jQuery.ajax({
		url: url,
		success: function(data) {
			document.getElementById('editfinder_preview').innerHTML = data;
			jQuery('#weave_button').css('display','none');
			jQuery('#easyimageupload_button').css('display','none');
			editFinder.restoreToolbarButtons();
			//Preview button
			jQuery('#wpPreview').click(function() {
				editfinder_preview = true;
			});
			//Publish button
			jQuery('#wpSave').click(function() {
				editfinder_preview = false;
			});
			//form submit
			jQuery('#editform').submit(function(e) {
				e.preventDefault();
				//just a preview?
				if (editfinder_preview) {
					editFinder.showPreview(id);
					jQuery('html, body').animate({scrollTop:0});
				}
				else {
					//pop conf modal
					if (editFinder.getEditType() == 'Topic') {
						editFinder.closeConfirmation(true);
						return false;
					}
					else {
						editFinder.displayConfirmation(id);
					}
				}
			});
	
			//pre-fill summary
			jQuery('#wpSummary').val("Edit from "+WH.lang['app-name']+": " + editFinder.getEditType().toUpperCase());

			//cancel link update
			var cancel_link = jQuery('#mw-editform-cancel').attr('href');
			cancel_link += '/'+editFinder.getEditType();
			jQuery('#mw-editform-cancel').attr('href',cancel_link);

			//make Cancel do the right thing
			jQuery('.editButtons #edit_cancel_btn').unbind('click');
			jQuery('.editButtons #edit_cancel_btn').click(function(e) {
				e.preventDefault();
				//do we need to make the preview disappear?
				if (editfinder_preview) {
					jQuery('#editfinder_preview_updated').fadeOut('fast');
				}
				editFinder.cancelConfirmationModal(id);
			});


			// change titles for buttons with shortcut keys
			var mod = Mousetrap.defaultModifierKeys;
			mod = mod.substring(0, mod.length - 1);
			$('#wpTextbox1').addClass('mousetrap');
			$('#wpSave').attr('title', 'publish [' + mod + ' p]').attr('accesskey', '');
			$('#wpPreview').attr('title', 'preview [' + mod + '  v]').attr('accesskey', '');
			$('.editButtons #edit_cancel_btn').attr('title', 'cancel [' + mod + ' c]').attr('accesskey', '');
			
			//disable edit/skip choices
			editFinder.disableTopButtons();		
			
			
			//throw cursor in the textarea
			jQuery('#wpTextbox1').change(function() {
				g_bEdited = true;
			});
	
			//add the id to the action url
			jQuery('#editform').attr('action',jQuery('#editform').attr('action')+'&aid='+id+'&type='+ editFinder.getEditType());
		}
	});
}

EditFinder.prototype.showPreview = function (id) {
	var editform = jQuery('#wpTextbox1').val();	
	var url = '/index.php';
	//var url = '/index.php?action=submit&wpPreview=true&live=true';
	
	// Not sure why Rap works when other titles don't, but it does
	// According to MW, this is only used if the wikitext contains magic
	// words such as {{PAGENAME}}
	// See: http://www.mediawiki.org/wiki/Manual:Live_preview
	var thisTitle = 'Rap';

	jQuery.ajax({
		url: url,
		type: 'POST',
		data: $('#editform').serialize() + '&wpPreview=true&live=true&action=edit&title=' + thisTitle,
		success: function(data) {
			
			var XMLObject = data;
			var previewElement = jQuery(data).find('preview').first();

			/* Inject preview */
			var previewContainer = jQuery('#editfinder_preview_updated');
			if ( previewContainer && previewElement ) {
				previewContainer.html(previewElement.first().text());
				previewContainer.slideDown('slow');
			}		
		}
	});
}

EditFinder.prototype.upTheStats = function() {
	var edittype = editFinder.getEditType().toLowerCase();
	var statboxes = '#iia_stats_today_repair_'+edittype+',#iia_stats_week_repair_'+edittype+',#iia_stats_all_repair_'+edittype+',#iia_stats_group';
	$(statboxes).each(function(index, elem) {
			$(this).fadeOut(function () {
				var cur = parseInt($(this).html());
				$(this).html(cur + 1);
				$(this).fadeIn();
			});
		}
	);
}

EditFinder.prototype.displayConfirmation = function( id ) {
	var url = '/Special:EditFinder?confirmation=1&type=' + editFinder.getEditType() + '&aid=' + id;

	jQuery('#dialog-box').load(url, function() {
		jQuery('#dialog-box').dialog({
		   width: 450,
		   modal: true,
		   closeText: 'Close',
		   title: 'Article Greenhouse Confirmation',
			closeOnEscape: true,
			position: 'center'
		});
		var mod = Mousetrap.defaultModifierKeys;
		Mousetrap.bind(mod + 'y', function() {$('#ef_yes').click();});
		Mousetrap.bind(mod + 'n', function() {$('#ef_no').click();});
	});
}

EditFinder.prototype.closeConfirmation = function( bRemoveTemplate ) {	
	//removing the template?
	if (bRemoveTemplate) {
		var text = jQuery('#wpTextbox1').val();
		var reg = new RegExp('{{' + editFinder.getEditType() + '[^\r\n]*}}','i');
		jQuery('#wpTextbox1').val(text.replace(reg,''));
	}
	
	//close modal window
	if (jQuery('#dialog-box').hasClass('ui-dialog-content')) {
		jQuery('#dialog-box').dialog('close');
	}
	jQuery('#img-box').html('');
	editFinder.resetTopButtons();
	
	jQuery('#editfinder_article_inner').fadeOut('fast');
	jQuery('#editfinder_preview').fadeOut('fast');
	jQuery('#editfinder_preview_updated').fadeOut('fast', function() {
		jQuery('#editfinder_spinner').fadeIn();
		jQuery('html, body').animate({scrollTop:0});
	});
	
	//submit
	jQuery.ajax({
		type: 'POST',
		url: jQuery('#editform').attr('action'),
		data: jQuery('#editform').serialize()
	});	
	
	editFinder.upTheStats();
	
	//next!
	editFinder.getArticle();
}

EditFinder.prototype.cancelConfirmationModal = function( id ) {
	var url = '/Special:EditFinder?cancel-confirmation=1&aid=' + id;

	if (g_bEdited) {
		jQuery('#dialog-box').load(url, function(data) {	
			//changes; get the box
			jQuery('#dialog-box').dialog({
			   width: 450,
			   modal: true,
			   closeText: 'Close',
			   title: 'Article Greenhouse Confirmation',
				closeOnEscape: true,
				position: 'center'
			});
			
			//initialize buttons
			jQuery('#efcc_yes').unbind('click');
			jQuery('#efcc_yes').click(function(e) {
				e.preventDefault();
				jQuery('#dialog-box').dialog('close');
				jQuery('html, body').animate({scrollTop:0});
				editFinder.resetTopButtons();
				editFinder.getArticle(id);
				
			});
			jQuery('#efcc_no').click(function() {
				jQuery('#dialog-box').dialog('close');
			});
		});
	}
	else {
		//no change; go back
		jQuery('html, body').animate({scrollTop:0});
		editFinder.resetTopButtons();
		editFinder.getArticle(id);
		return;
	}
}

EditFinder.prototype.disableTopButtons = function() {
	//disable edit/skip choices
	jQuery('#editfinder_yes').addClass('clickfail');	
	jQuery('#editfinder_skip').addClass('clickfail');
	return;
}

EditFinder.prototype.resetTopButtons = function() {
	//disable edit/skip choices
	jQuery('#editfinder_yes').removeClass('clickfail');
	jQuery('#editfinder_skip').removeClass('clickfail');
	return;
}

//grab an abbreviated list of a user's chosen interests
EditFinder.prototype.getUserInterests = function() {
	var url = '/Special:CategoryInterests?a=get';
	var cats = '';
	
	$.getJSON(url, function(data) {
			cats = data.interests.join(", ");
			cats = cats.replace(/-/g, " ");
			if (cats.length == 0) {
				if (!$.cookie('ef_int')) {
					editFinder.getThoseInterests();
				}

				cats = 'No interests selected';
			}

			if (cats.length > 50)
				cats = cats.substring(0,50) + '...';
				
			jQuery('#user_cats').html(cats);
	});
	return;
}

//grab an abbreviated list of a user's chosen categories
EditFinder.prototype.getUserCats = function() {
	var url = '/Special:SuggestCategories?getusercats=1';
	var cats = '';
	
	jQuery.ajax({
		url: url,
		success: function(data) {
			cats = data;
			if (cats.length > 50)
				cats = cats.substring(0,50) + '...';
				
			jQuery('#user_cats').html(cats);
		}
	});
	return;
}
/*

*  Adapted from EditPage.php code since we kind of hack the edit form in place in the greenhouse
*/
EditFinder.prototype.restoreToolbarButtons = function() {
	if(window.mw){
		mw.loader.using("mediawiki.action.edit", function() {
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Bold text", "\'\'\'", "\'\'\'", "Place bold text here", "mw-editbutton-bold");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Italic text", "\'\'", "\'\'", "Italic text", "mw-editbutton-italic");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Internal link", "[[", "]]", "Link title", "mw-editbutton-link");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "External link (remember http:// prefix)", "[", "]", "http://www.example.com link title", "mw-editbutton-extlink");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Level 2 headline", "\n== ", " ==\n", "Headline text", "mw-editbutton-headline");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Embedded image", "[[Image:", "]]", "Example.jpg", "mw-editbutton-image");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Media file link", "[[Media:", "]]", "Example.ogg", "mw-editbutton-media");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Ignore wiki formatting", "\x3cnowiki\x3e", "\x3c/nowiki\x3e", "Insert non-formatted text here", "mw-editbutton-nowiki");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Your signature with timestamp", "--~~~~", "", "", "mw-editbutton-signature");
		mw.toolbar.addButton("/skins/owl/images/1x1_transparent.gif", "Horizontal line (use sparingly)", "\n----\n", "", "", "mw-editbutton-hr");

		// Create button bar
		$(function() { mw.toolbar.init(); } );
		});
	}
}

var editFinder = new EditFinder();

//kick it
$(document).ready(function() {
	editFinder.init();
});

//stat stuff
updateStandingsTable = function() {
    var url = '/Special:Standings/EditFinderStandingsGroup?type=' + editFinder.getEditType();
    jQuery.get(url, function (data) {
        jQuery('#iia_standings_table').html(data['html']);
    },
	'json'
	);
	$("#stup").html(EF_WIDGET_LEADERBOARD_REFRESH / 60);
	window.setTimeout(updateStandingsTable, 1000 * EF_WIDGET_LEADERBOARD_REFRESH);
}

window.setTimeout(updateWidgetTimer, 60*1000);
window.setTimeout(updateStandingsTable, 100);


function updateWidgetTimer() {
    updateTimer('stup');
    window.setTimeout(updateWidgetTimer, 60*1000);
}



