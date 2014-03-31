
function Slider() {
	this.m_link = '/Special:StarterTool';
	// Show the slider 3% of the time for the edit article tour CTA
	this.test_on = Math.random() <= .03;
}

Slider.prototype.init = function () {
	if (!slider.test_on) {
		return;
	}
		
	
	$('#sliderbox').animate({
		right: '+=500',
		bottom: '+=300'
	},function() {
	
		//initialize buttons/links
		slider.buttonize();

		//set a sesh cookie
		//document.cookie = 'sliderbox = 1';
	});

}

Slider.prototype.buttonize = function() {
	$('#slider_close_button').click(function() {
		//let us not speak of this again...
		var exdate = new Date();
		var expiredays = 365;
		exdate.setDate(exdate.getDate()+expiredays);
		document.cookie = "sliderbox=3;expires="+exdate.toGMTString();
		
		slider.closeSlider();
		return false;
	});
	$('#slider_edit_button').click(function(e) {
		e.preventDefault();
		document.location.href = '/' + mw.config.get('wgPageName') + '?action=edit&tour=fe';
		return false;
	});
}

Slider.prototype.closeSlider = function() {
	$('#sliderbox').animate({
		right: '-500px',
		bottom: '-310px'
	});
	
	//set a 30-day cookie
/*	var exdate = new Date();
	var expiredays = 30;
	exdate.setDate(exdate.getDate()+expiredays);
	document.cookie = "sliderbox=2;expires="+exdate.toGMTString();*/
}

//let's log the choice in the database
Slider.prototype.log = function(action) {
	var url = '/Special:Slider?action='+action;
	$.get(url);
}


var slider = new Slider();
