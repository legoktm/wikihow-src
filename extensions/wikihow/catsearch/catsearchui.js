$(function() {
	var ci_url = '/Special:CategoryInterests';
	var sorryLabel = "Sorry, nothing found. Try another search.";

	function addInterest(message, id) {
		if (isDup(id)) {
			$("#categories").children().each(function(i, cat) {
				if ($(cat).children('div:first').html() == id) {
					$(cat).addClass('csui_active');
					setTimeout(function() {$(cat).removeClass('csui_active');}, 1500);
				}
			});
			return;
		}

		if(isValid(id)) {
			$.get(ci_url, {a: 'add', cat: id}, function(data) {
				$("#csui_none").addClass("csui_hidden");
				var urlDiv = $("<div/>").text(id).addClass("csui_hidden");
				var trashIcon = "/skins/WikiHow/images/csui_minus.png";
				var closeSpan = $("<span/>").html("<img class='csui_minus_icon' src='" + trashIcon + "'/>").addClass("csui_close");
				$( "<div/>" ).text(message).append(closeSpan).append(urlDiv).addClass("csui_category ui-widget-content ui-corner-all csui_nodisplay").prependTo( "#categories" ).slideDown('fast');
			});
		}
	}
	
	function isDup(id) {
		var isDup = false;
		$("#categories").children().each(function(i, cat) {
			if ($(cat).children('div:first').html() == id) {
				isDup = true;
				return false;
			}
		});

		return isDup;
	}

	function isValid(id) {
		return id != sorryLabel;
	}


	$('#csui_close_popup').live('click', function (e) {
		$('#dialog-box').dialog('close');
	});

	$('#csui_close_popup').live('mouseover mouseout', function(e) {
		e.type == 'mouseover' ? button_swap(this) : button_unswap(this);
	});

	$(".csui_close").live('click', function(e) {
		var interestDiv = $(this).parent();
		var interest = $(this).parent().children('div:first').text();
		$.get(ci_url, {a: 'remove', cat: interest}, function(data) {
			$(interestDiv).slideUp('fast', function() {
				$(this).remove();

				if ($("#categories").children().size() == 1) {
					$('#csui_none').removeClass('csui_hidden');
				}
			});
		});
	});

	$(".csui_suggestion").live('click', function(e) {
		var id = $(this).children('div:first').text();
		var label = id.replace(/-/g, " ");
		addInterest(label, id);
	});

	$("#csui_interests").autocomplete({
		source: function( request, response ) {
			$.ajax({
				url: "/Special:CatSearch",
				dataType: "json",
				data: {
					q: request.term
				},
				success: function( data ) {
					if (!data.results.length) {
						data.results.push({label: sorryLabel, value: sorryLabel});
					}
					response( $.map( data.results, function( item ) {
						return {
							label: item.label,
							value: item.url
						}
					}));
				}
			});
		},
		minLength: 3,
		select: function( event, ui ) {
			$("#csui_interests").removeClass("ui-autocomplete-loading");
			addInterest(ui.item.label, ui.item.value);
			return false;
		},
		focus: function(event, ui) { 
			$('#csui_interests').val(ui.item.label); 
			return false;
		},
	});
});
