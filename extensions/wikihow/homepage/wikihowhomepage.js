var nextNum;
var interval;
var desiredText;
var elementNumber;
var typeInterval;

var inputActive = false;

$(document).ready(function(){

    //clear anything that the browser might have cached here
    $("#cse-search-hp input.search_box").val("");

	$(".hp_nav").click(function(){
		if(!$(this).hasClass("on")) {
			window.nextNum = parseInt($(this).attr("id").substr(4));
			window.clearTimeout(window.interval);
			window.clearInterval(window.typeInterval);
			rotateImage();
		}
	});

	$("#cse-search-hp input").click(function(){
		window.inputActive = true;
		window.clearInterval(window.typeInterval);
		$("#hp_container .hp_title").html("");
	});

	typewriter(1);
});

function rotateImage() {
	var currentElement;
	var currentNum;
	var nextElement;

	currentElement = $(".hp_top:visible");
	currentNum = parseInt($(currentElement).attr("id").substr(7));
	if(window.nextNum == null) {
		if($("#hp_top_"+ (currentNum+1)).length != 0)
			window.nextNum = currentNum + 1;
		else
			window.nextNum = 1;
	}
	nextElement = $("#hp_top_" + nextNum);

	$(nextElement).fadeIn(800);
	$(currentElement).fadeOut(800);
	$("#nav_" + currentNum).removeClass("on");
	$("#nav_" + window.nextNum).addClass("on");

	if(!window.inputActive)
		typewriter(window.nextNum);

	window.nextNum = null;
}

function typewriter(elementNumber) {
	window.elementNumber = elementNumber;
	window.desiredText = $("#hp_top_" + elementNumber + " .hp_text").attr("title");

	$("#hp_container .hp_title").html("");

	window.typeInterval = window.setInterval(type, 150); //how fast the typing happens
}

/***
 *
 * Adds one letter to the text being typed
 *
 ***/
function type() {
	var currentString = $("#hp_container .hp_title").html();
	var currentLength = currentString.length;
	var newChar = window.desiredText.charAt(currentLength);
	$("#hp_container .hp_title").html(currentString + newChar);
	if(currentLength + 1 == window.desiredText.length) {
		window.clearInterval(window.typeInterval);
		window.interval = window.setTimeout(rotateImage, 3000);
	}
}

/*$.fn.teletype = function(opts){
	var $this = this,
		defaults = {
			animDelay: 50
		},
		settings = $.extend(defaults, opts);

	$.each(settings.text.split(''), function(i, letter){
		setTimeout(function(){
			$this.html($this.html() + letter);
		}, settings.animDelay * i);
	});
};

$(function(){
	$('#container').teletype({
		animDelay: 100,
		text: 'Now is the time for all good men to come to the aid of their country...'
	});
});*/
