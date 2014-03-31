/*
 * Intro Image Adder Class
 */

function IntroImageAdder() {
	this.m_title = '';
	this.m_searchterms = '';
}

IntroImageAdder.prototype.init = function () {
	//introImageAdder.getMessage();
	//introImageAdder.getStats();
	introImageAdder.getArticle();
}

IntroImageAdder.prototype.getMessage = function () {
	var url = '/Special:IntroImageAdder?fetchMessage=true';

	jQuery.get(url, function (data) {
		var json = jQuery.parseJSON(data);
		jQuery('#iia_msg').html(json);
	});
}

IntroImageAdder.prototype.updateStandingsTable = function() {
	updateWidget("#iia_standings_table", "IntroImageStandingsGroup");
}

IntroImageAdder.prototype.getArticle = function() {
	var url = '/Special:IntroImageAdder?fetchArticle=true';
	var e = jQuery('#article_title');
	if (e.html()) {
		url += '&skip=' + encodeURIComponent(e.html());;
	}	
	var title = '';
	var searchterms = '';

	jQuery.get(url, function (data) {
		var json = jQuery.parseJSON(data);
		title = json['title'];
		aURL = json['url'];
		searchterms = json['terms'];
		aid = json['aid'];

		//EasyImageUpload.prototype.doEIU_IIA( title, searchterms, 'iia_main', 'intro');
		easyImageUpload.doEIU_IIA(title, searchterms, 'iia_main', 'intro');
	});

}

IntroImageAdder.prototype.displayConfirmation = function( ret ) {
	if (getCookie('iia_modal') == null) {
		var url = '/Special:IntroImageAdder?confirmation=1&iiatitle='+ret['title']+'&imgtag='+ret['img'];

		jQuery('#dialog-box').load(url, function() {
			jQuery('#dialog-box').dialog({
			   width: 450,
			   modal: true,
			   title: 'Intro Image Adder Confirmation',
			   show: 'slide',
				closeOnEscape: true,
				position: 'center'
			});
		});
		
	}
}

IntroImageAdder.prototype.closeConfirmation = function() {
	if (jQuery('#confirmModalFlag').prop('checked')) {
		setCookie('iia_modal', 1, 365);
	}
	jQuery('#dialog-box').dialog('close');
}

var introImageAdder = new IntroImageAdder();
setInterval('introImageAdder.updateStandingsTable()', 60 * 10 * 1000);


setInterval('updateWidgetTimer()', 60*1000);

function updateWidgetTimer() {
	updateTimer('stup');
}

	
