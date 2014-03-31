//checks sidenav to see if it needs to be sticky or not
//top = top of article_main
//bottom = bottom of steps
function isSideNavScrollable() {

	var boundaryTop = $('#article_main').offset().top;
	var boundaryBottom = ($('#steps_end').offset().top - $('#sidenav').height() - 20);

    var docViewTop = $(window).scrollTop();
    var docViewBottom = docViewTop + $(window).height();
	
	//return (elemTop >= boundaryTop);
	//return 'et:'+elemTop+' - eb:'+elemBottom+' - dt:'+docViewTop+' - db:'+docViewBottom;
	//return ((docViewTop > boundaryTop) && (docViewTop < boundaryBottom));
	
}

function getSideNavTop() {
	var theTop = 0; //zero means scrolling
	
    var docViewTop = $(window).scrollTop(); //top of viewable area	
	var sideheight = $('#sidenav').height() - 30; //sidenav height w/ some padding	
	var boundaryTop = $('#article_main').offset().top; //top of article
	var boundaryBottom = ($('#steps_end').offset().top - sideheight); //bottom of steps w/ sideheight offset
	
	var new_top = $('#sidenav').offset().top; //the new top o' the side nav
	
	if (docViewTop < boundaryTop) {
		theTop = boundaryTop;
	}
	else if (docViewTop > boundaryBottom) {
		theTop = boundaryBottom;
	}
	
	return theTop;
}

$(document).ready(function(){
	$(window).bind('scroll', function(){
	
		theTop = getSideNavTop();
		$('#sidenav').css('top',theTop);

		if (theTop == 0) {
			//scrolling
			$('#sidenav').css('position','fixed');
		}
		else {
			//not scrolling
			$('#sidenav').css('position','absolute');
		}
	});

});