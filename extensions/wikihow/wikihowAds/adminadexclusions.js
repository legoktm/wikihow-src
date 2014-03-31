$("document").ready(function() {

	$("#adexclusions").submit(function(e){
		e.preventDefault();

		if($("#adexclusions input").hasClass("disabled"))
			return;

		urls = $("#urls").val().trim();

		if(urls == "") {
			alert("You must enter urls");
			return;
		}

		$("#adexclusions input").addClass("disabled");
		$("#adexclusions_results").html("");

		$.ajax({
			url: "/Special:AdminAdExclusions",
			dataType: "json",
			data: {
				urls: urls,
				submitted: true
			},
			success: function( data ) {
				$("#adexclusions input").removeClass("disabled");

				if(data.success) {
					$("#adexclusions_results").html("The urls have been added to the exclusion list.");
				}
				else {
					results = "We were not able to process the following urls:<br />";
					for (var i=0; i<data['errors'].length; i++) {
						results += data['errors'][i] + "<br />";
					}

					$("#adexclusions_results").html(results);
				}
			}
		})
	});
});
