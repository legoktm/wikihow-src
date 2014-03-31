
var nfd_vote = 0;
var nfd_skip = 0;
var nfd_id   = 0;
var NFD_STANDINGS_TABLE_REFRESH = 600;
var show_delete_confirmation = true;

$("document").ready(function() {
	initToolTitle();
	addOptions();
	getNextNFD();
	jQuery('#nfd_delete_confirm .no').click(function(e){
		e.preventDefault();
		jQuery('#nfd_delete_confirm').dialog('close');
	});
	jQuery('#nfd_delete_confirm .yes').click(function(e){
		e.preventDefault();
		jQuery('#nfd_delete_confirm').dialog('close');
		nfdVote(true);
	});
});

function initToolTitle() {
	$(".firstHeading").before("<h5>" + $(".firstHeading").html() + "</h5>")
}

// asks the backend for a new article
//to patrol and loads it in the page
function getNextNFD() {
	$.get('/Special:NFDGuardian',
		{fetchInnards: true,
		  nfd_type: getCookie('nfdrule_choices'),
		},
		function (result) {
			loadResult(result);
		},
		'json'
	);
}

//keeps track via cookie which nfd category
//the user wants to see
function updateChoices() {
	var choices = [];
	$("#nfd_reasons select option:selected").each(function() {
		choices.push($(this).text());
	});
	setCookie('nfdrule_choices',choices.join());
}

function loadResult(result) {
	//clear stuff out
	$('#nfdcontents').remove();
	//$('#nfd_reasons_link').remove();
	$('#nfd_reasons').remove();
	
	//put in new html
	$(".firstHeading").html(result['title']);
	//$(".firstHeading").before(result['nfd_reasons_link']);
	//$(".firstHeading").after(result['nfd_reasons']);
	$(".tool_options").html(result['nfd_reasons']);
	show_delete_confirmation = result['nfd_discussion_count'] > 15;

	$('#nfd_reasons select').change( function() {
		updateChoices();
	});
	$('#nfdrules_submit').click( function(e) {
		e.preventDefault();
		$('#nfd_options').slideUp();
		getNextNFD();
	});
	
	if (result['done']) {
		$("#bodycontents").before("<div id='nfdcontents' class='tool'>"+result['msg']+"</div>");
	}
	else {
		$("#bodycontents").before("<div id='nfdcontents' class='tool'>"+result['html']+"</div>");
	}

	$('.nfd_options_link').click( function(e) {
		e.preventDefault();
		displayNFDOptions();
	});

	//make all links in the info section open in a new window
	$('#nfd_article_info a').attr('target', '_blank');

	$("#nfdcontent").show();
	$(".waiting").hide();
	
	nfd_id	= result['nfd_id'];
	nfd_page = result['nfd_page'];

	$("#tab_article").click(function(e){
		e.preventDefault();
		getArticle();
	});

	$("#nfd_save").click(function(e){
		e.preventDefault();
		getEditor();
		return false;
	});

	$("#tab_edit").click(function(e){
		e.preventDefault();
		getEditor();
		return false;
	});

	$("#tab_discuss").click(function(e){
		e.preventDefault();
		getDiscussion();
	});

	$(".discuss_link").click(function(e){
		e.preventDefault();
		getDiscussion();
	});

	$("#tab_history").click(function(e){
		e.preventDefault();
		getHistory('/Special:NFDGuardian?history=true&articleId=' + nfd_page);
	});
	
	//keep button
	$('#nfd_keep').click( function(e) {
		e.preventDefault();
		nfdVote(false);
	});
	
	//delete button
	$('#nfd_delete').click( function(e) {
		e.preventDefault();
		if(show_delete_confirmation){
			$('#nfd_delete_confirm').dialog({
			   width: 450,
			   modal: true,
			   title: 'NFD Guardian Confirmation',
			   show: 'slide',
			   closeText: 'Close',
			   closeOnEscape: true,
				position: 'center'
			});
		}
		else
			nfdVote(true);

	});

	$('#delete_confirmation_discussion').click(function(e){
		e.preventDefault();
		$('#nfd_delete_confirm').dialog('close');
		getDiscussion();
	});
	
	//skip
	$('#nfd_skip').click( function(e) {
		e.preventDefault();
		nfdSkip();
	});

	$('#nfd_article_info a.tooltip').hover(
		function() {
			getToolTip(this,true);
		},
		function() {
			getToolTip(this,false);
		}
	);
}

function submitResponse() {
	$(".nfd_tabs_content").hide();
	$(".waiting").show();
	$.post('/Special:NFDGuardian',
		{ 
		  nfd_vote: nfd_vote,
		  nfd_skip: nfd_skip,
		  nfd_type: getCookie('nfdrule_choices'),
		  nfd_id: nfd_id
		},
		function (result) {
			if (!nfd_skip) {
				getVoteBlock();
			}
			loadResult(result);
		},
		'json'
	);
}

function nfdVote(vote) {
	(vote) ? (nfd_vote = 1) :(nfd_vote = 0);
	nfd_skip = 0;
	incCounters(); 
	submitResponse();
}

function nfdSkip() {
	nfd_skip = 1;
	submitResponse();
}

updateStandingsTable = function() {
    var url = '/Special:Standings/nfdStandingsGroup';
    jQuery.get(url, function (data) {
        jQuery('#iia_standings_table').html(data['html']);
    },
	'json'
	);
	$("#stup").html(NFD_STANDINGS_TABLE_REFRESH / 60);
	//reset timer
	window.setTimeout(updateStandingsTable, 1000 * NFD_STANDINGS_TABLE_REFRESH);
}

window.setTimeout(updateWidgetTimer, 60*1000);
window.setTimeout(updateStandingsTable, 1000 * NFD_STANDINGS_TABLE_REFRESH);

function updateWidgetTimer() {
    updateTimer('stup');
    window.setTimeout(updateWidgetTimer, 60*1000);
}

function getEditor(){
	$('.nfd_tabs_content').hide();
	$('.waiting').show();
	$.get('/Special:NFDGuardian', {
		edit: true,
		articleId: nfd_page,
		nfd_id:nfd_id,
		},
		function (result) {
			$('#article_tabs a').removeClass('on');
			$('#tab_edit').addClass('on');
			$('.nfd_tabs_content').hide();
			$('#articleEdit').show();
			$('.waiting').hide();
			document.getElementById('articleEdit').innerHTML = result;
			restoreToolbarButtons();
			jQuery('#wpSummary').val("Edit from NFD Guardian");
			jQuery('#wpPreview').click(function() {
				nfd_preview = true;
			});
			//Publish button
			jQuery('#wpSave').click(function() {
				nfd_preview = false;
			});
			jQuery('#editform').submit(function(e) {
				e.preventDefault();
				if(nfd_preview){
					var editform = jQuery('#wpTextbox1').val();
					var url = '/index.php?action=submit&wpPreview=true&live=true';

					jQuery.ajax({
						url: url,
						type: 'POST',
						data: 'wpTextbox1='+editform,
						success: function(data) {

							var XMLObject = data;
							var previewElement = jQuery(data).find('preview').first();

							/* Inject preview */
							var previewContainer = jQuery('#articleBody');
							if ( previewContainer && previewElement ) {
								previewContainer.html(previewElement.first().text());
								previewContainer.slideDown('slow');
							}
						}
					});
				}
				else{
					displayConfirmation(nfd_page);
				}
			});
			
		}
	);
}


function displayConfirmation() {
	var url = '/Special:NFDGuardian?confirmation=1&articleId='+nfd_page;

	jQuery('#dialog-box').load(url, function() {
		jQuery('#dialog-box').dialog({
		   width: 450,
		   modal: true,
		   title: 'NFG Guardian Confirmation',
			closeText: 'Close',
			closeOnEscape: true,
			position: 'center'
		});
	});
}

function closeConfirmation( bRemoveTemplate ) {

	//close modal window
	jQuery('#dialog-box').dialog('close');

	$(".waiting").show();
	$("#articleEdit").hide();
	$(window).scrollTop(0);
	$.post('/Special:NFDGuardian', {
		submitEditForm: true,
		url: jQuery('#editform').attr('action'),
		wpTextbox1: jQuery("#editform #wpTextbox1").val(),
		wpSummary: jQuery("#editform #wpSummary").val(),
		//data: jQuery('#editform').serialize(),
		removeTemplate: bRemoveTemplate,
		nfd_type: getCookie('nfdrule_choices'),
		nfd_id: nfd_id,
		articleId: nfd_page
		},
		function (result) {
			if(bRemoveTemplate)
				getVoteBlock();
			loadResult(result);
		},
		'json'
	);

	if(bRemoveTemplate){
		incCounters();
	}
}

function getArticle(){
	$('.nfd_tabs_content').hide();
	$('.waiting').show();
	$.get('/Special:NFDGuardian', {
		article: true,
		articleId: nfd_page,
		},
		function (result) {
			$('#article_tabs a').removeClass('on');
			$('#tab_article').addClass('on');
			$('.nfd_tabs_content').hide();
			$('#articleBody').html(result);
			$('#articleBody').show();
			$('.waiting').hide();
		}
	);
}

function getDiscussion(){
	show_delete_confirmation = false;
	if($("#articleDiscussion").is(':empty')){
		$('.nfd_tabs_content').hide();
		$('.waiting').show();
		$.get('/Special:NFDGuardian', {
			discussion: true,
			articleId: nfd_page,
			},
			function (result) {
				$('#article_tabs a').removeClass('on');
				$('#tab_discuss').addClass('on');
				$('.nfd_tabs_content').hide();
				$('#articleDiscussion').html(result);
				$('#articleDiscussion').show();
				$('.waiting').hide();
			}
		);
	}
	else{
		$('#article_tabs a').removeClass('on');
		$('#tab_discuss').addClass('on');
		$('.nfd_tabs_content').hide();
		$('#articleDiscussion').show();
	}
}

function getHistory(url){
	$('.nfd_tabs_content').hide();
	$('.waiting').show();
	$.get(url,
		function (result) {
			$('#article_tabs a').removeClass('on');
			$('#tab_history').addClass('on');
			$('.nfd_tabs_content').hide();
			$('#articleHistory').html("<div id='bodycontents' class='minor_section bc_history'>" + result + "</div>");
			$('#articleHistory').show();
			$('.waiting').hide();
			//make all the links in the history table open in a new window
			$('#pagehistory a').attr('target', '_blank');

			$('#articleHistory a').not('#pagehistory a').click(function(e){
				e.preventDefault();
				getHistory($(this).attr("href"));
			});
			jQuery('#mw-history-compare').submit(function(e) {
				e.preventDefault();
				//just a preview?

				$('#articleHistory').hide();
				$('.waiting').show();

				//get diff and show in main page
				$.get('/Special:NFDGuardian', {
					diff: $("#pagehistory input[name!='oldid']:checked").val(),
					articleId: nfd_page,
					oldid: $("#pagehistory input[name='oldid']:checked").val()
					},
					function (result) {
						$('#article_tabs a').removeClass('on');
						$('#tab_article').addClass('on');
						$('.nfd_tabs_content').hide();
						$('#articleBody').html(result);
						$('#articleBody').show();
						$('.waiting').hide();
					}
				);
			});
		}
	);
}

function incCounters() {
	$("#iia_stats_week_nfd, #iia_stats_today_nfd, #iia_stats_all_nfd, #iia_stats_group").each(function (index, elem) {
			$(this).fadeOut(function () {
				val = parseInt($(this).html()) + 1;
				$(this).html(val);
				$(this).fadeIn(); 
			});
		}
	); 
}

function getVoteBlock() {
	var vote_block = '';

	$.get('/Special:NFDGuardian', {
		getVoteBlock: true,
		nfd_id: nfd_id,
		},
		function (result) {
			$('#nfd_voteblock').remove();

			vote_block = "<div id='nfd_voteblock' class='sidebox'>" + result + "</div>";

			$('#top_links').after(vote_block);

			//animate in
			$('#nfd_voteblock').animate({
				"height": "toggle",
				"opacity": "toggle"
				}, {duration: 800});

			//tooltip for changed by
			$('.nfd_avatar a.tooltip').hover(
				function() {
					getToolTip(this,true);
				},
				function() {
					getToolTip(this,false);
				}
			);
		}
	);

}

// show/hide checkboxes
function displayNFDOptions() {

	if ($('#nfd_reasons').css('display') == 'none') {
		//show it!
		$('#nfd_reasons').slideDown();
	}
	else {
		//hide it!
		$('#nfd_reasons').slideUp();
	}
}

