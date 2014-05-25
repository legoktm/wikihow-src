var toolURL = "/Special:AltMethodAdder";
var defaultSteps = "Add your steps using an ordered list. For example:\n1. Step one\n2. Step two\n3. Step three";
var defaultMethod = "Name your method";

//add in the numbers steps automatically
$(document).on("keyup", "#altsteps", function(e) {
	/* ENTER PRESSED*/
	if (e.keyCode == 13) {
		oldText = $('#altsteps').val();
		steps = oldText.split("\n");
		
		var stepCount = 1;
		for (var i = 0; i < steps.length; i++) {
		  step = steps[i].trim();
		  if(step != "")
			  stepCount++;
		}
		
		$('#altsteps').val(oldText + stepCount + ". ");
		
	}
});

//Don't want to scroll the page or submit the form
//when the user presses the enter key
$(document).on("keypress", "#altmethod", function(e) {
	if (e.keyCode == 13) {
		e.preventDefault();
	}
});



(function($) {
	var clicked = [];
	
	$(document).on('click', '#addalt', function(e) {
		window.oTrackUserAction();
		e.preventDefault();
		
		$(this).hide();
		$('.alt_waiting').show();
		
		newMethod = $('#altmethod').val();
		newSteps = $('#altsteps').val();
		
		if(newMethod == "") {
			alert("You must give your method a title.");
			$(this).show();
			$('.alt_waiting').show();
		}
		else if(newSteps == "1." || newSteps == defaultSteps) {
			alert("You have not entered any steps.");
			$(this).show();
			$('.alt_waiting').show();
		}
		else {
			//first take out any extra spaces
            var reg = /^ +/mg;
            var alteredSteps = newSteps.replace(reg, "");

			//now need to munge the steps to put in the #
			reg = /^[0-9]*\./mg;
			alteredSteps = alteredSteps.replace(reg, "#");
			
			var data = {'aid' : wgArticleId, 'altMethod' : newMethod, 'altSteps' : alteredSteps};
			$.get(toolURL, data, function(result){
				newHtml = $('<div></div>').html(result.html);
				
				//in the php we had add extra wikitext to make it process correctly 
				//so now we need to strip it out.
				var section = $("#newaltmethod").parent();
				section.removeClass('altadder_section').addClass('steps sticky').prepend($(newHtml).find("#steps").html())
				
				//remove the form
				$("#newaltmethod").remove();
				$('#altheader').remove();
			}, "json");
		
		}
	});
	$("#altsteps").val(defaultSteps);
	$("#altmethod").val(defaultMethod);
	
	$("#altsteps").focus(function(){
		if($("#altsteps").val() == defaultSteps) {
			$("#altsteps").val("1. ");
			$("#altsteps").addClass("active");
			$("#altsteps").removeClass("inactive");
		}
	});
	$("#altsteps").blur(function(){
		newSteps = $.trim($("#altsteps").val());
		if(newSteps == "1."){
			$("#altsteps").val(defaultSteps);
			$("#altsteps").addClass("inactive");
			$("#altsteps").removeClass("active");
		}
	});
	$("#altmethod").focus(function(){
		if($("#altmethod").val() == defaultMethod) {
			$("#altmethod").val("");
			$("#altmethod").addClass("active");
			$("#altmethod").removeClass("inactive");
		}
	});
	$("#altmethod").blur(function(){
		if($("#altmethod").val() == "") {
			$("#altmethod").val(defaultMethod);
			$("#altmethod").addClass("inactive");
			$("#altmethod").removeClass("active");
		}
	});
	

})(jQuery);
