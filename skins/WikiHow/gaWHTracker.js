var gatUser = 'Anon_Editing';
if (typeof(wgUserName) != "undefined") {
	if (wgUserName != null) {
		gatUser = 'Registered_Editing';
	}
}

function gatTrack(category, action, label, value) {
	//alert('in gatTracker: '+category+', '+action+', '+label+', '+value);
	var call = '';

	if (typeof(value) != "undefined") {
		call = category+', '+action+', '+label+', '+value;
		_gaq.push(['_trackEvent',category, action, label, value]);
	} else if (typeof(label) != "undefined") {
		call = category+', '+action+', '+label;
		_gaq.push(['_trackEvent',category, action, label]);
	} else {
		call = category+', '+action;
		_gaq.push(['_trackEvent',category, action]);
	}

	//alert('Track:'+call);
}

function gatStartObservers() {
	//alert('gatStartObserver');

	var regClickEvent = function(id, param2, param3) {
		var evtType, fn;
		if (typeof param2 == 'string') {
			evtType = param2;
			fn = param3;
		} else {
			evtType = 'click';
			fn = param2;
		}

		if (evtType == 'click') {
			jQuery('#' + id).click(fn);
		} else if (evtType == 'submit') {
			jQuery('#' + id).submit(fn);
		}
	};

	var regClickEventByClass = function(klass, param2, param3) {
		var evtType, fn;
		if (typeof param2 == 'string') {
			evtType = param2;
			fn = param3;
		} else {
			evtType = 'click';
			fn = param2;
		}

		if (evtType == 'click') {
			jQuery('.' + klass).click(fn);
		} else if (evtType == 'submit') {
			jQuery('.' + klass).submit(fn);
		}
	};


	if (document.getElementById("gatEdit")) {
		regClickEvent('gatEdit', function(e) {
			gatTrack(gatUser,"Edit","Edit_page");
		});
	}

	if (document.getElementById("gatEditFooter")) {
		regClickEvent('gatEditFooter', function(e) {
			gatTrack(gatUser,"Edit","Edit_footer");
		});
	}

	// WITH MULTIPLE SECTIONS HAD TO PUT THIS INLINE
	/* if (document.getElementById("gatEditSection")) {
		regClickEvent('gatEditSection', function(e) {
			gatTrack(gatUser,"Edit","Edit_section");
		});
	} */
	if (document.getElementById("gatWriteAnArticle")) {
		regClickEvent('gatWriteAnArticle', function(e) {
			gatTrack(gatUser,"Write1","Write1");
		});
	}

	if (document.getElementById("gatWriteAnArticleFooter")) {
		regClickEvent('gatWriteAnArticleFooter', function(e) {
			gatTrack(gatUser,"Write1","Write1_footer");
		});
	}

	if (document.getElementById("gatSuggestedTitle")) {
		regClickEvent('gatSuggestedTitle', function(e) {
			gatTrack(gatUser,"Write2","Articles_to_write");
		});
	}

	/* if (document.getElementById("gatRandom")) {
		regClickEvent('gatRandom', function(e) {
			gatTrack(gatUser,"Edit","Edit_section");
		});
	} */

	if (document.getElementById("gatCreateForm")) {
		regClickEvent('gatCreateForm', 'submit', function(e) {
			gatTrack(gatUser,"Write2","Own_topic");
		});
	}

	if (document.getElementById('gatCreateFormTopics')) {
		regClickEvent('gatCreateFormTopics', 'submit', function(e) {
			gatTrack(gatUser,"Write2","Suggest_topic");
		});
	}

	if (document.getElementById('gatPubAssist')) {
		regClickEvent('gatPubAssist', function(e) {
			gatTrack(gatUser,"Pub_assist");
		});
	}

	if (document.getElementById('gatCreateSubmitAnyway')) {
		regClickEvent('gatCreateSubmitAnyway', function(e) {
			gatTrack(gatUser,"Write3");
		});
	}

	if (document.getElementById('cp_next')) {
		regClickEvent('cp_next', function(e) {
			gatTrack(gatUser,"Write3");
		});
	}

	if (document.getElementById('gatTalkPost')) {
		regClickEvent('gatTalkPost', 'submit', function(e) {
			gatTrack("Communication","Talk_post");
		});
	}

	if (document.getElementById('gatDiscussionPost')) {
		regClickEvent('gatDiscussionPost', 'submit', function(e) {
			gatTrack("Communication","Discussion_post");
		});
	}

	if (document.getElementById("gatDiscussionFooter")) {
		regClickEvent('gatDiscussionFooter', function(e) {
			gatTrack("Communication","Discussion_page","Discuss_footer");
		});
	}

	if (document.getElementById("gatDiscussionTab")) {
		regClickEvent('gatDiscussionTab', function(e) {
			gatTrack("Communication","Discussion_page","Discuss_tab");
		});
	}

	/* if (document.getElementById('gatTalk')) {
		regClickEvent('gatTalk', function(e) {
			gatTrack(gatUser,"XXX");
		});
	} */

	if (document.getElementById('gatNewMessage')) {
		regClickEvent('gatNewMessage', function(e) {
			gatTrack("Communication","Msg_notification","Msg_notification");
		});
	}

	if (document.getElementById('nav_login') && (gatUser == 'Anon_Editing')) {
		regClickEvent('nav_login', function(e) {
			gatTrack("Accounts","Begin_login","Begin_login");
		});
	}

	if (document.getElementById('nav_signup') && (gatUser == 'Anon_Editing')) {
		regClickEvent('nav_signup', function(e) {
			gatTrack("Accounts","Begin_login","Begin_login");
		});
	}

	if (document.getElementById('userloginlink_signup')) {
		regClickEvent('userloginlink_signup', function(e) {
			gatTrack("Accounts","Create_account","Any_page");
		});
	}

	if (document.getElementById('userloginlink_login')) {
		regClickEvent('userloginlink_login', function(e) {
			gatTrack("Accounts","Create_account","Any_page");
		});
	}

	if (document.getElementById('wpLoginattempt')) {
		regClickEvent('wpLoginattempt', function(e) {
			gatTrack("Accounts","Login");
		});
	}

	if (document.getElementById('wpCreateaccount')) {
		regClickEvent('wpCreateaccount', function(e) {
			gatTrack("Accounts","Finish_account");
		});
	}

	if (document.getElementById('bubble_search')) {
		regClickEvent('bubble_search', 'submit', function(e) {
			gatTrack("Search","Search","L-search");
		});
	}
	if (document.getElementById('footer_search')) {
		regClickEvent('footer_search', 'submit', function(e) {
			gatTrack("Search","Search","L-search");
		});
	}

	/* if (document.getElementById('cse-search-box')) {
		regClickEvent('cse-search-box', 'submit', function(e) {
			gatTrack("Search","Search","Custom_search");
		});
	} */
	window.oTrackUserAction = function() {
		window['optimizely'] = window['optimizely'] || [];

		// Optimizely 'hack' to track the total number of actions (i.e. one user may possibly do multiple actions)
		window['optimizely'].push(["trackEvent","user_action",{'anonymous':true}]);
		
		var actions = getCookie('nuacts');
		if(actions === undefined) {
			actions = 0;	
		}
		actions++;
		$.cookie('nuacts', actions, {expires: 365, path: '/'});
		if(actions == 1) {
			window['optimizely'].push(["trackEvent","user_action1"]);
		}
		else if(actions == 5) {
			window['optimizely'].push(["trackEvent","user_action5"]);	
		}
		else if(actions == 10) {
			window['optimizely'].push(["trackEvent","user_action10"]);	
		}
	}

	var oTrackEdit = function() {
		if(typeof window['wgNamespaceNumber'] !== 'undefined' && window.wgNamespaceNumber == 0) {
			window['optimizely'] = window['optimizely'] || [];
			
			// Optimizely 'hack' to track the total number of edits (i.e. one user may possibly do multiple edits)
			window['optimizely'].push(["trackEvent","edit",{'anonymous':true}]);
			var edits = getCookie('num_edits');
			if(edits === undefined) {
				edits = 0;	
			}
			edits++;
			$.cookie('num_edits', edits, {expires: 365, path: '/'});
			if(edits == 1) {
				window['optimizely'].push(["trackEvent","edit1"]);
			}
			else if(edits == 5) {
				window['optimizely'].push(["trackEvent","edit5"]);	
			}
			else if(edits == 10) {
				window['optimizely'].push(["trackEvent","edit10"]);	
			}
			window.oTrackUserAction();	
		}
	}

	// save preview edit page
	if (document.getElementById('wpSave')) {
		if(typeof isGuided !== "undefined" && isGuided) {
			regClickEventByClass('wpSave', function(e) {
				oTrackEdit();
				gatTrack(gatUser,"Save","Save_button");
			});
		}
		else {
			regClickEvent('wpSave', function(e) {
				oTrackEdit();
				gatTrack(gatUser,"Save","Save_button");
			});
		}
	}

	if (document.getElementById('wpPreview')) {
		regClickEvent('wpPreview', function(e) {
			gatTrack(gatUser,"Preview","Preview_button");
		});
	}

	if (document.getElementById('gatPSBSave')) {
		regClickEvent('gatPSBSave', function(e) {
			gatTrack(gatUser,"Save","Save_bar");
		});
	}

	if (document.getElementById('gatPSBPreview')) {
		regClickEvent('gatPSBPreview', function(e) {
			gatTrack(gatUser,"Preview","Preview_bar");
		});
	}

	if (document.getElementById('gatGuidedSave')) {
		regClickEvent('gatGuidedSave', function(e) {
			gatTrack(gatUser,"Save","Save_button");
		});
	}

	if (document.getElementById('gatGuidedPreview')) {
		regClickEvent('gatGuidedPreview', function(e) {
			gatTrack(gatUser,"Preview","Preview_button");
		});
	}

	if (document.getElementById('gatImagePopup')) {
		regClickEvent('gatImagePopup', function(e) {
			gatTrack(gatUser,"Add_img","Editing_page");
		});
	}

	if (document.getElementById('gatWPUploadPopup')) {
		regClickEvent('gatWPUploadPopup', function(e) {
			// NOTE: Hardcoding category since user has to be logged in and 
			// wgUser var not present
			gatTrack("Registered_Editing","Upload_img","Editing_page");
		});
	}

	if (document.getElementById('gatImageUpload')) {
		regClickEvent('gatImageUpload', function(e) {
			gatTrack(gatUser,"Add_img","Editing_tools");
		});
	}

	if (document.getElementById('gatWPUpload')) {
		regClickEvent('gatWPUpload', function(e) {
			gatTrack(gatUser,"Upload_img","Editing_tools");
		});
	}

	if (document.getElementById('gatVideoImport')) {
		regClickEvent('gatVideoImport', function(e) {
			gatTrack(gatUser,"Choose_video","Editing_tools");
		});
	}

	if (document.getElementById('gatVideoImportIt')) {
		regClickEvent('gatVideoImportIt', function(e) {
			gatTrack(gatUser,"Import_video","Editing_tools");
		});
	}

	if (document.getElementById('gatVideoImportEdit')) {
		regClickEvent('gatVideoImportEdit', function(e) {
			gatTrack(gatUser,"Choose_video","Editing_page");
		});
	}

	if (document.getElementById('gatVideoImportItFormEdit')) {
		regClickEvent('gatVideoImportItFormEdit', 'submit', function(e) {
			// NOTE: Hardcoding category since user has to be logged in and 
			// wgUser var not present
			gatTrack("Registered_Editing","Import_video","Editing_page");
		});
	}

	if (document.getElementById('gatCreateArticle')) {
		regClickEvent('gatCreateArticle', function(e) {
			gatTrack(gatUser,"Create_request");
		});
	}

	if (document.getElementById('gatWidgetBottom')) {
		regClickEvent('gatWidgetBottom', function(e) {
			gatTrack("Accounts","Create_account","RC_widget");
		});
	}

	if (document.getElementById('rcElement_list')) {
		regClickEvent('rcElement_list', function(e) {
			gatTrack("Browsing","Widget_click");
		});
	}

	// sharing
	if (document.getElementById("gatSharingEmail")) {
		regClickEvent('gatSharingEmail', function(e) {
			gatTrack("Sharing","Share_article","Email");
		});
	}

	if (document.getElementById("gatSharingFacebook")) {
		regClickEvent('gatSharingFacebook', function(e) {
			gatTrack("Sharing","Share_article","Facebook");
		});
	}

	if (document.getElementById("gatSharingTwitter")) {
		regClickEvent('gatSharingTwitter', function(e) {
			gatTrack("Sharing","Share_article","Twitter");
		});
	}

	if (document.getElementById("gatSharingStumbleupon")) {
		regClickEvent('gatSharingStumbleupon', function(e) {
			gatTrack("Sharing","Share_article","Stumbleupon");
		});
	}

	if (document.getElementById("gatSharingDigg")) {
		regClickEvent('gatSharingDigg', function(e) {
			gatTrack("Sharing","Share_article","Digg");
		});
	}

	if (document.getElementById("gatSharingBlogger")) {
		regClickEvent('gatSharingBlogger', function(e) {
			gatTrack("Sharing","Share_article","Blogger");
		});
	}

	if (document.getElementById("gatSharingDelicious")) {
		regClickEvent('gatSharingDelicious', function(e) {
			gatTrack("Sharing","Share_article","Delicious");
		});
	}

	if (document.getElementById("gatSharingGoogleBookmarks")) {
		regClickEvent('gatSharingGoogleBookmarks', function(e) {
			gatTrack("Sharing","Share_article","Google_bookmarks");
		});
	}

	if (document.getElementById("gatSharingEmbedding")) {
		regClickEvent('gatSharingEmbedding', function(e) {
			gatTrack("Sharing","Embedding","Embed_footer");
		});
	}

	if (document.getElementById("gatPrintView")) {
		regClickEvent('gatPrintView', function(e) {
			gatTrack("Using","Print_article","Footer");
		});
	}

	if (document.getElementById("gatThankAuthors")) {
		regClickEvent('gatThankAuthors', function(e) {
			gatTrack("Article_response","Kudos","Thank_authors");
		});
	}

	if (document.getElementById("gatAccuracyYes")) {
		regClickEvent('gatAccuracyYes', function(e) {
			gatTrack("Article_response","Accuracy","Accurate");
		});
	}

	if (document.getElementById("gatAccuracyNo")) {
		regClickEvent('gatAccuracyNo', function(e) {
			gatTrack("Article_response","Accuracy","Not_accurate");
		});
	}

	if (document.getElementById("gatIphoneNotice")) {
		regClickEvent('gatIphoneNotice', function(e) {
			gatTrack("Mobile_use","iPhone_download","iPhone_download");
		});
	}

	if (document.getElementById("gatIphoneNoticeHide")) {
		regClickEvent('gatIphoneNoticeHide', function(e) {
			gatTrack("Mobile_use","Hide_iPhone_DL","Hide_iPhone_DL");
		});
	}

	// profile avatar

	// inline in avatar.js since this object doesn't exist at load
	/* if (document.getElementById("gatUploadImageLink")) {
		regClickEvent('gatUploadImageLink', function(e) {
			gatTrack("Profile","Begin_avatar_upload","Begin_avatar_upload");
		});
	} */

	if (document.getElementById("gatAvatarImageSubmit")) {
		regClickEvent('gatAvatarImageSubmit', function(e) {
			gatTrack("Profile","Select_avatar_image","Select_avatar_image");
		});
	}

	if (document.getElementById("gatAvatarCropAndSave")) {
		regClickEvent('gatAvatarCropAndSave', function(e) {
			gatTrack("Profile","Save_avatar_image","Save_avatar_image");
		});
	}

	if (document.getElementById("gatProfileCreateButton")) {
		regClickEvent('gatProfileCreateButton', function(e) {
			gatTrack("Profile","Begin_profile","Begin_profile");
		});
	}

	if (document.getElementById("gatProfileSaveButton")) {
		regClickEvent('gatProfileSaveButton', function(e) {
			gatTrack("Profile","Save_profile","Save_profile");
		});
	}

	if (document.getElementById("gatProfileEditButton")) {
		regClickEvent('gatProfileEditButton', function(e) {
			gatTrack("Profile","Edit_profile","Edit_profile");
		});
	}

	// facebook login template
	if (document.getElementById("gatFBCLogin")) {
		regClickEvent('gatFBCLogin', function(e) {
			gatTrack("Accounts","Login","FB_connect_login");
		});
	}

	// facebook header
	if (document.getElementById("gatFBCHeader")) {
		regClickEvent('gatFBCHeader', function(e) {
			gatTrack("Accounts","Login","FB_connect_login");
		});
	}

	if (document.getElementById("gatFBCProfileSave")) {
		regClickEvent('gatFBCProfileSave', function(e) {
			gatTrack("Accounts","Create_account","FB_connect");
		});
	}

	if (document.getElementById("gatRandom")) {
		regClickEvent('gatRandom', function(e) {
			gatTrack("Browsing","Random_article","Random_article");
		});
	}

	if (document.getElementById("gatBreadCrumb")) {
		regClickEvent('gatBreadCrumb', function(e) {
			gatTrack("Browsing","Category_browsing","Bread_crumb");
		});
	}

    if(document.getElementById("method_toc")) {
        regClickEvent('method_toc', function(e) {
           gatTrack("Browsing", "Method_browsing", "alt_method");
        });
    }
	
	if (document.getElementById("gatFooterCategories")) {
		regClickEvent('gatFooterCategories', function(e) {
			gatTrack("Browsing","Category_browsing","Footer_categories");
		});
	}
	
	//Follow widget
	if(document.getElementById("gatFollowFacebook")){
		regClickEvent('gatFollowFacebook', function(e) {
			gatTrack("Followers","FB_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowTwitter")){
		regClickEvent('gatFollowTwitter', function(e) {
			gatTrack("Followers","Twitter_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowGoogle")){
		regClickEvent('gatFollowGoogle', function(e) {
			gatTrack("Followers","iGoogle_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowRss")){
		regClickEvent('gatFollowRss', function(e) {
			gatTrack("Followers","RSS_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowBuzz")){
		regClickEvent('gatFollowBuzz', function(e) {
			gatTrack("Followers","Buzz_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowEmail")){
		regClickEvent('gatFollowEmail', function(e) {
			gatTrack("Followers","Email_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowApp")){
		regClickEvent('gatFollowApp', function(e) {
			gatTrack("Followers","App_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowYahoo")){
		regClickEvent('gatFollowYahoo', function(e) {
			gatTrack("Followers","Yahoo_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowPinterest")){
		regClickEvent('gatFollowPinterest', function(e) {
			gatTrack("Followers","Pinterest_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowAndroid")){
		regClickEvent('gatFollowAndroid', function(e) {
			gatTrack("Followers","Android_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowTumblr")){
		regClickEvent('gatFollowTumblr', function(e) {
			gatTrack("Followers","Tumblr_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowPlus")){
		regClickEvent('gatFollowPlus', function(e) {
			gatTrack("Followers","Plus_follow","Follow_wgt");
		});
	}
	
	if(document.getElementById("gatFollowStumbleupon")){
		regClickEvent('gatFollowStumbleupon', function(e) {
			gatTrack("Followers","Stumbleupon_follow","Follow_wgt");
		});
	}
	
	//Sample page
	if(document.getElementById("gatSamplePdf1")){
		regClickEvent('gatSamplePdf1', function(e) {
			gatTrack("Samples","Pdf_download","Sample_Page");
		});
	}
	if(document.getElementById("gatSamplePdf2")){
		regClickEvent('gatSamplePdf2', function(e) {
			gatTrack("Samples","Pdf_download","Sample_Page");
		});
	}
	if(document.getElementById("gatSamplePdf3")){
		regClickEvent('gatSamplePdf3', function(e) {
			gatTrack("Samples","Pdf_download","Sample_Page");
		});
	}
	
	if(document.getElementById("gatSampleWord1")){
		regClickEvent('gatSampleWord1', function(e) {
			gatTrack("Samples","Word_download","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleWord2")){
		regClickEvent('gatSampleWord2', function(e) {
			gatTrack("Samples","Word_download","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleWord3")){
		regClickEvent('gatSampleWord3', function(e) {
			gatTrack("Samples","Word_download","Sample_Page");
		});
	}
	
	if(document.getElementById("gatSampleTxt1")){
		regClickEvent('gatSampleTxt1', function(e) {
			gatTrack("Samples","Txt_download","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleTxt2")){
		regClickEvent('gatSampleTxt2', function(e) {
			gatTrack("Samples","Txt_download","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleTxt3")){
		regClickEvent('gatSampleTxt3', function(e) {
			gatTrack("Samples","Txt_download","Sample_Page");
		});
	}
	
	if(document.getElementById("gatSampleGdoc1")){
		regClickEvent('gatSampleGdoc1', function(e) {
			gatTrack("Samples","Gdoc_open","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleGdoc2")){
		regClickEvent('gatSampleGdoc2', function(e) {
			gatTrack("Samples","Gdoc_open","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleGdoc3")){
		regClickEvent('gatSampleGdoc3', function(e) {
			gatTrack("Samples","Gdoc_open","Sample_Page");
		});
	}	
	
	if(document.getElementById("gatSampleXls1")){
		regClickEvent('gatSampleXls1', function(e) {
			gatTrack("Samples","Excel_download","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleXls2")){
		regClickEvent('gatSampleXls2', function(e) {
			gatTrack("Samples","Excel_download","Sample_Page");
		});
	}	
	if(document.getElementById("gatSampleXls3")){
		regClickEvent('gatSampleXls3', function(e) {
			gatTrack("Samples","Excel_download","Sample_Page");
		});
	}

}

function gup(name) {
	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
	var regexS = "[\\?&]"+name+"=([^&#]*)";
	var regex = new RegExp(regexS);
	var results = regex.exec(window.location.href);
	if (results == null)
		return "";
	else
		return results[1];
}

