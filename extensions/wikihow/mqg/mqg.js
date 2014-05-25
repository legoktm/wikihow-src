(function ($) {
	var qc_vote = 0; 
	var qc_skip = 0;
	var qc_id   = 0;
	var qc_page = '/Special:MQG';
	var qc_num_votes = 0;
	var qc_loading = false;
	var qc_yes_responses = ["Nice!",  "Sweet!", "You are a rockstar!", "That's incredible!", "Bam! That was Spicy!", "So Fab!", "That's Hot!", "BooYah!"];
	var qc_no_responses = ["Get that outta here.", "Go away image!", "Adios.", "See ya later, alligator.", "Lame."];
	var qc_skip_responses = ["No prob.  We'll get you another."];



	jQuery.fn.center = function () {
		this.css("position","absolute");
		this.css("top", (($(window).height() - this.outerHeight()) / 2) + $(window).scrollTop() + "px");
		this.css("left", (($(window).width() - this.outerWidth()) / 2) + $(window).scrollLeft() + "px");
		return this;
	}

	function getNextQC() {
		//grab options
		qc_loading = true;
		$.get(
			qc_page, 
			{fetchInnards: true},
			function (result) {
				loadResult(result);
			},
			'json'
		);
	}

	function initEventListeners() {
		//yes button
		$('#mqg_yes').live('click', function() {
			qcVote(true);
			window.oTrackUserAction();
			return false;
		});
		
		//no button
		$('#mqg_no').live('click', function() {
			qcVote(false);
			window.oTrackUserAction();
			return false;
		});	
		
		//skip
		$('#mqg_skip').live('click', function() {
			qcSkip();
			return false;
		});

		// Sliding down email box
		$('#mqg_ok').live('click', function() {
			sendEmail("mqg");
			return false;
		});

		$('#mqg_finish_ok').live('click', function() {
			sendEmail("mqg_finish");
			// Let's us know not to show the email popup again if they've already submitted
			setCookie('mqgec', '1', 365 * 100);
			return false;
		});

		//skip
		$('#mqg_dismiss').live('click', function() {
			$('#mqg_eml_box').fadeOut('slow', function() {
				resetEmailPopup();
			});
			return false;
		});
	}

	function loadResult(result) {
		$('#mqg_spinner').css('display', 'none');
		var mqg_body = $('#mqg_body');
		mqg_body.html(result['html']);
		$('#mqg_pic img').addClass('mqg_rounded');
		mqg_body.slideDown(function() {
			qc_id = result['qc_id'];
			displayAddAppDiag();
			displayEmailPopup();
			qc_vote = 0;
			qc_skip = 0;
			qc_loading = false;
		});
		
		//document.title = result['title'];

	}

	function resetEmailPopup() {
		$('#mqg_eml_box').hide().css('margin-left', '0').css('top', '0');
		$('#mqg_eml').val('');
	}

	function displayEmailPopup() {
		// After 3 votes, show email collection prompt
		//if (true) {
		if (document.cookie.indexOf('mqgec') == -1 && 
			document.cookie.indexOf('mqge') == -1 && 
			qc_num_votes == 7 && 
			$('#mqg_pic').length && 
			$('#mqg_finish_eml').length == 0) {
			//resetEmailPopup();
			var display = function() {
				var marginLeft = $(window).width() / 2 - $('#mqg_eml_box').width() / 2;
				$('#mqg_eml_box').css('display', 'block').css('margin-left', marginLeft - 5).animate({top: '+=50'}, 500);
				setCookie('mqge', '1', 10);
			};
			setTimeout(display, 200);
		}
	}

	function displayAddAppDiag() {
		window.addToHomeConfig = {
			animationIn:'bubble',       // Animation In
			animationOut:'drop',        // Animation Out
			lifespan: 60 * 1000,        // The popup lives 60 seconds
			touchIcon:true
		};
		// After 5 votes, add the script to display the add2home balloon
		if (document.cookie.indexOf('mqgp') == -1 && qc_num_votes == 5) {
			//window.addToHomeConfig.debug = true;  // debug flag to always turn on popup even if not an iphone
			var base = '\/extensions\/min\/';
			$(document.head).append('<link rel="stylesheet" href="' + wfGetPad(base + '?g=ma2h&rev=' + WH_SITEREV) + '">');
			$(document.head).append('<script type="application/javascript" src="' + wfGetPad(base + '?g=mah&rev=' + WH_SITEREV) + '"><\/s' + 'cript>');

			// Show once (every 100 years) 
			setCookie('mqgp', '1', 365 * 100);
		}
	}

	function submitResponse() {
		showSpinner(function() {
			$.post(qc_page,
				{ 
				  qc_vote: qc_vote,
				  qc_skip: qc_skip,
				  qc_id: qc_id
				},
				function (result) {
					loadResult(result);
				},
				'json'
			);
		});
	}


	function transImage(callback) {
		resetEmailPopup();
		setTransResponse();
		$('#mqg_pic img').fadeOut('fast', function() {
			var mqg_class = '';
			if (qc_vote) {
				mqg_class = 'mqg_yes';	
			} else if (qc_skip) {
				mqg_class = 'mqg_skip';
			} else {
				mqg_class = 'mqg_no';
			}

			$('#mqg_pic').css('width', 160).addClass(mqg_class).fadeIn('slow', function() {
				setTimeout(callback, 500);
			});
		});
	}

	function transImageComplete() {
		submitResponse();
	}

	function setTransResponse() {
		var responses = [];
		if (qc_vote) {
			responses = qc_yes_responses;	
		} else if (qc_skip) {
			responses = qc_skip_responses;;	
		} else {
			responses = qc_no_responses;;	
		}

		var arrLength = responses.length;
		var rnd = Math.floor(Math.random() * arrLength);
		$('#mqg_trans_response').html(responses[rnd]).show();
	}

	function fadeInIntroImg() {
		$('#intro_img').fadeIn('slow', transImageComplete);
	}

	function qcVote(vote) {
		if (!qc_loading) {
			qc_loading = true;
			if (vote) {
				qc_vote = 1; 
				transImage(fadeInIntroImg);
			} else {
				qc_vote = 0;
				transImage(transImageComplete);
			}
			qc_skip = 0; 
			qc_num_votes++;
		}
	}

	function showSpinner(callback) {
			$('#mqg_body').slideUp('slow', function() {
				$('#mqg_spinner').center().css('display', 'block');	
				callback();
			});
	}

	function qcSkip() {
		if (!qc_loading) {
			qc_loading = true;
			qc_skip = 1; 
			transImage(transImageComplete);
		}
	}

	/** 
	 * A simple pad function.  Note that it won't match up with the output of
	 * the php.
	 */ 
	function wfGetPad(url) {
		if (url.search(/^http:\/\//) >= 0 || window.location.hostname.indexOf('wikidiy.com') != -1) {
			return url;
		} else {
			return 'http:\/\/pad1.whstatic.com' + url;
		}
	}   

	function setCookie(c_name, value, exdays) {
		var exdate = new Date();
		exdate.setDate(exdate.getDate() + exdays);
		var c_value = escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
		document.cookie = c_name + "=" + c_value;
	}

	function sendEmail(type) {
		var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\ ".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA -Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
		var email = $('#' + type + '_eml').val().trim();

		if (re.test(email)) {
			$('#' + type + '_eml_box').fadeOut('slow');
			if (!qc_loading) {
				qc_loading = true;
				$.get(qc_page, {'email': email}, function() {qc_loading = false;});
				//resetEmailPopup();
			}
		}
	}

	initEventListeners();
	getNextQC();
}(jQuery));
