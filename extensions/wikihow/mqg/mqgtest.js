// MQG Test source
(function ($) {

	jQuery.fn.center = function () {
		this.css("position","absolute");
		this.css("top", (($(window).height() - this.outerHeight()) / 2) + $(window).scrollTop() + "px");
		this.css("left", (($(window).width() - this.outerWidth()) / 2) + $(window).scrollLeft() + "px");
		return this;
	}

	function MQGTestType() {
		this.qc_vote = 0; 
		this.qc_skip = 0;
		this.qc_id   = 0;
		this.qc_rev_id = getParameterByName('qc_rev_id');
		this.qc_aid = getParameterByName('qc_aid');
		this.qc_type = getParameterByName('qc_type');
		this.qc_page = '/Special:MQG';
		this.qc_num_votes = 0;
		this.qc_loading = false;
		this.qc_yes_responses = ["Nice!",  "Sweet!", "You are a rockstar!", "That's incredible!", "Bam! That was Spicy!", "So Fab!", "That's Hot!", "BooYah!"];
		this.qc_no_responses = ["Get that outta here.", "Adios.", "See ya later, alligator.", "Lame."];
		this.qc_skip_responses = ["No prob.  We'll get you another."];
		this.qc_test_num = 0;
		this.qc_last = 0;

		this.getNextQC = function() {
			//grab options
			this.qc_loading = true;
			$.get(
				mqgTest.qc_page, 
				{fetchInnards: true, qc_rev_id: mqgTest.qc_rev_id, qc_type: mqgTest.qc_type},
				function (result) {
					mqgTest.loadResult(result);
					mqgTest.displayArticle(result, mqgTest.postDisplayArticle);
				},
				'json'
			);
		}

		this.loadResult = function(result) {
			$('#mqg_spinner').css('display', 'none');
			$('#mqg_body').html(result['html']);
			mqgTest.qc_type = $('#mqg_type').html();
			mqgTest.qc_rev_id = $('#mqg_rev_id').html();
			$('#mqg_pic img').addClass('mqg_rounded');
			mqgTest.qc_test_num++;
			//mqgTest.trackResult();
		}

		this.trackResult = function(){
			var vote = -1;
			if (mqgTest.qc_vote) {
				vote = 1;
			} else {
				// encode skip as 2
				vote = mqgTest.qc_skip ? 2 : 0;
			}
			$.get(mqgTest.qc_page, {'log' : mqgTest.qc_type + ',' + mqgTest.qc_aid + '-' + mqgTest.qc_test_num + '-' + vote});
			/*
			if (mqgTest.qc_test_num) {
			 	$.get(mqgTest.qc_page, {'log' : mqgTest.qc_type + ',' + mqgTest.qc_test_num});
			}
			*/
		}


		this.initEventListeners = function() {
			//skip
			$(document).on('click', '#mqg_skip', function(e) {
				e.preventDefault();
				mqgTest.qcSkip();
				mqgTest.transition();
				return false;
			});

			$(document).on('click', '#mqg_finish_ok', function(e) {
				e.preventDefault();
				sendEmail("mqg_finish");
				setCookie('mqgec', '1', 365 * 100);
				return false;
			});
		}

		this.displayArticle = function(result, callback) {
			var mqg_body = $('#mqg_body');
			mqg_body.slideDown(function() {
				mqgTest.qc_id = result['qc_id'];
				mqgTest.qc_vote = 0;
				mqgTest.qc_skip = 0;
				callback();
				mqgTest.qc_loading = false;
			});
		}

		this.qcVote = function(vote) {
			if (!this.qc_loading) {
				this.qc_loading = true;
				if (vote) {
					mqgTest.qc_vote = vote; 
				} else {
					mqgTest.qc_vote = 0;
				}
				mqgTest.qc_skip = 0; 
				mqgTest.qc_num_votes++;
			}
		}

		this.submitResponse = function() {
			$.post(mqgTest.qc_page,
				{ 
				  qc_vote: mqgTest.qc_vote,
				  qc_skip: mqgTest.qc_skip,
				  qc_id: mqgTest.qc_id,
				  qc_rev_id: mqgTest.qc_rev_id,
				  qc_type: mqgTest.qc_type,
				  qc_last: mqgTest.qc_last
				},
				function (result) {
					mqgTest.loadResult(result);
					mqgTest.displayArticle(result, mqgTest.postDisplayArticle);
				},
				'json'
			);
		}

		this.postDisplayArticle = function(){}

		this.resetEmailPopup = function() {
			$('#mqg_eml_box').hide().css('margin-left', '0').css('top', '0');
			$('#mqg_eml').val('');
		}

		this.transition = function() {
			//mqgTest.trackResult();
			mqgTest.displayResponseTxt();
			mqgTest.displayResponseImg();
		}

		this.completeTransition = function() {
			mqgTest.hideArticle(mqgTest.submitResponse);
		}

		this.displayResponseTxt = function() {
			var responses = [];
			if (mqgTest.qc_vote) {
				responses = mqgTest.qc_yes_responses;	
			} else if (mqgTest.qc_skip) {
				responses = mqgTest.qc_skip_responses;	
			} else {
				responses = mqgTest.qc_no_responses;
			}

			var arrLength = responses.length;
			var rnd = Math.floor(Math.random() * arrLength);
			$('#mqg_trans_response').html(responses[rnd]).show();
		}

		this.displayResponseImg = function() {
			var mqg_class = '';
			if (mqgTest.qc_vote) {
				mqg_class = 'mqg_yes';	
			} else if (mqgTest.qc_skip) {
				mqg_class = 'mqg_skip';
			} else {
				mqg_class = 'mqg_no';
			}

			$('#mqg_pic').css('width', 160).removeClass('mqg_pic_yesno').addClass(mqg_class).fadeIn('slow', function() {
				setTimeout(mqgTest.displayResponseImgCallback, 500);
			});
		}

		this.displayResponseImgCallback = function() {
        	mqgTest.completeTransition();
        }

		this.hideArticle = function(callback) {
			$('#mqg_body').slideUp('slow', function() {
				$('#mqg_spinner').center().css('display', 'block');	
				callback();
			});
		}

		this.qcSkip = function() {
			if (!mqgTest.qc_loading) {
				mqgTest.qc_loading = true;
				mqgTest.qc_skip = 1; 
			}
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
			if (!mqgTest.qc_loading) {
				mqgTest.qc_loading = true;
				$.get(mqgTest.qc_page, {'email': email}, function() {mqgTest.qc_loading = false;});
				mqgTest.resetEmailPopup();
			}
		}
	}

	function getParameterByName(name) {
	  name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
	  var regexS = "[\\?&]" + name + "=([^&#]*)";
	  var regex = new RegExp(regexS);
	  var results = regex.exec(window.location.search);
	  if(results == null)
		return "";
	  else
		return decodeURIComponent(results[1].replace(/\+/g, " "));
	}


	/*
	* MQGRecommendTest - use for recommend test types
	*/
	function MQGRecommendTest() {
	}

	/*
	* MQGRatingTest - use for star rating test types
	*/
	function MQGRatingTest() {
		this.loadResult = function(result) {
			mqgTestType.loadResult.call(this, result);
			if ($('input.mqg_star').length) {
				$('input.mqg_star').rating({
				callback: function(value, link){
					mqgTest.qcVote(value);
					mqgTest.transition();
				}
				});
			}
		}

		this.displayResponseTxt = function() {
			var responses = [];
			if (mqgTest.qc_skip) {
				responses = mqgTest.qc_skip_responses;	
			} else if (mqgTest.qc_vote >= 3) {
				responses = mqgTest.qc_yes_responses;	
			} else if (mqgTest.qc_vote < 3) {
				responses = mqgTest.qc_no_responses;
			}

			var arrLength = responses.length;
			var rnd = Math.floor(Math.random() * arrLength);
			$('#mqg_trans_response').html(responses[rnd]).show();
		}

		this.displayResponseImg = function() {
			var mqg_class = '';
			if (mqgTest.qc_skip) {
				mqg_class = 'mqg_skip';
			} else if (mqgTest.qc_vote >= 3) {
				mqg_class = 'mqg_yes';
			} else if (mqgTest.qc_vote < 3) {
				mqg_class = 'mqg_no';
			}

			$('#mqg_pic').css('width', 160).removeClass('mqg_pic_yesno').addClass(mqg_class).fadeIn('slow', function() {
				setTimeout(mqgTest.displayResponseImgCallback, 500);
			});
		}
	}

	/*
	* MQGTipTest - use for video test types
	*/
	function MQGTipTest() {
		this.loadResult = function(result) {
			mqgTestType.loadResult.call(this, result);

			var numTips = $('#tips:first li').size();	
			var numWarnings = $('#warnings:first li').size();	
			var testNum = mqgTest.qc_test_num;
			if (testNum >= numTips + numWarnings) {
				mqgTest.qc_last = 1;
			}

			var html = '';
			if (testNum <= numTips) {
				html = $('#tips:first ul').children('li').eq(testNum - 1).html();
			} else {
				html = $('#warnings:first ul').children('li').eq(testNum - numTips - 1).html();
			}
			$('#mqg_pic').addClass('mqg_tip').html(html);
		}

		this.displayResponseImg = function() {
			var mqg_class = '';
			if (mqgTest.qc_skip) {
				mqg_class = 'mqg_skip';
			} else if (mqgTest.qc_vote) {
				mqg_class = 'mqg_yes';
			} else {
				mqg_class = 'mqg_no';
			}

			$('#mqg_pic').empty().removeClass('mqg_tip').css('width', 160).removeClass('mqg_pic_yesno').addClass(mqg_class).fadeIn('slow', function() {
				setTimeout(mqgTest.displayResponseImgCallback, 500);
			});
		}
	}


	/*
	* MQGVideoTest - use for video test types
	*/
	function MQGVideoTest() {
		this.loadResult = function(result) {
			mqgTestType.loadResult.call(this, result);
			$('#mqg_pic').html($('#video:first center:first').clone());
		}

		this.displayResponseImg = function() {
			var mqg_class = '';
			if (mqgTest.qc_skip) {
				mqg_class = 'mqg_skip';
			} else if (mqgTest.qc_vote) {
				mqg_class = 'mqg_yes';
			} else {
				mqg_class = 'mqg_no';
			}

			$('#mqg_pic').empty().css('width', 160).removeClass('mqg_pic_yesno').addClass(mqg_class).fadeIn('slow', function() {
				setTimeout(mqgTest.displayResponseImgCallback, 500);
			});
		}
	}


	/*
	* MQGYesNoTest - use for yes/no test types
	*/
	function MQGYesNoTest() {
		this.initEventListeners = function() {
			// Init parent event listeners
			mqgTestType.initEventListeners.call(this);

			//yes button
			jQuery(document).on('click', '#mqg_yes', function(e) {
			    e.preventDefault();
				mqgTest.qcVote(1);
				mqgTest.transition();
				//setTimeout(mqgTest.completeTransition, 800);
			});
			
			//no button
			$(document).on('click', '#mqg_no', function(e) {
				e.preventDefault();
				mqgTest.qcVote(0);
				mqgTest.transition(mqgTest.completeTransition);
				return false;
			});	
		}
	}

	/*
	* MQGPhotoTest - use for photo test types
	*/
	function MQGPhotoTest() {
		this.postDisplayArticle = function() {
			//mqgTest.displayAddAppDiag();
			//mqgTest.displayEmailPopup();
		}


		this.displayResponseImg = function() {
			$('#mqg_pic img').fadeOut('fast', function() {
				mqgTestType.displayResponseImg.call(this);
			});
		}

		this.displayResponseImgCallback = function() {
			if (mqgTest.qc_vote) {
        		$('#intro_img').fadeIn('slow', mqgTest.completeTransition);	
			} else {
				mqgTest.completeTransition();
			}
        }


		this.displayEmailPopup = function() {
			// After 3 votes, show email collection prompt
			//if (true) {
			if (document.cookie.indexOf('mqgec') == -1 && 
				document.cookie.indexOf('mqge') == -1 && 
				mqgTest.qc_num_votes == 7 && 
				$('#mqg_pic').length && 
				$('#mqg_finish_eml').length == 0) {
				mqgTest.resetEmailPopup();
				var display = function() {
					var marginLeft = $(window).width() / 2 - $('#mqg_eml_box').width() / 2;
					$('#mqg_eml_box').css('display', 'block').css('margin-left', marginLeft - 5).animate({top: '+=50'}, 500);
					setCookie('mqge', '1', 10);
				};
				setTimeout(display, 200);
			}
		}

		this.displayAddAppDiag = function() {
			window.addToHomeConfig = {
				animationIn:'bubble',       // Animation In
				animationOut:'drop',        // Animation Out
				lifespan: 60 * 1000,        // The popup lives 60 seconds
				touchIcon:true
			};
			// After 5 votes, add the script to display the add2home balloon
			if (document.cookie.indexOf('mqgp') == -1 && mqgTest.qc_num_votes == 5) {
				//window.addToHomeConfig.debug = true;  // debug flag to always turn on popup even if not an iphone
				var base = '\/extensions\/min\/';
				$(document.head).append('<link rel="stylesheet" href="' + wfGetPad(base + '?g=ma2h&rev=' + WH_SITEREV) + '">');
				$(document.head).append('<script type="application/javascript" src="' + wfGetPad(base + '?g=mah&rev=' + WH_SITEREV) + '"><\/s' + 'cript>');

				// Show once (every 100 years) 
				setCookie('mqgp', '1', 365 * 100);
			}
		}

		this.initEventListeners = function() {
			// Init parent event listeners
			mqgTestType.initEventListeners.call(this);

			//yes button
			jQuery(document).on('click', '#mqg_yes', function(e) {
				e.preventDefault();
				mqgTest.qcVote(true);
				mqgTest.transition();
				return false;
			});
			
			//no button
			$(document).on('click', '#mqg_no', function(e) {
				e.preventDefault();
				mqgTest.qcVote(false);
				mqgTest.transition();
				return false;
			});	
			
			// Sliding down email box
			$(document).on('click', '#mqg_ok', function(e) {
				sendEmail("mqg");
				return false;
			});
		}
	}

	// Set prototypes 
	var mqgTestType = new MQGTestType();
	var mqgYesNoTest = null;
	var mqgTest = null;


	if (mqgTestType.qc_type == 'yesno') {
		MQGYesNoTest.prototype = mqgTestType;
		mqgTest = new MQGYesNoTest();
	} else if (mqgTestType.qc_type == 'tip') { 
		MQGYesNoTest.prototype = mqgTestType;
		mqgYesNoTest = new MQGYesNoTest();
		MQGTipTest.prototype = mqgYesNoTest;
		mqgTest = new MQGTipTest();
	} else if (mqgTestType.qc_type == 'video') { 
		MQGYesNoTest.prototype = mqgTestType;
		mqgYesNoTest = new MQGYesNoTest();
		MQGVideoTest.prototype = mqgYesNoTest;
		mqgTest = new MQGVideoTest();
	} else if (mqgTestType.qc_type == 'recommend') { 
		MQGYesNoTest.prototype = mqgTestType;
		mqgYesNoTest = new MQGYesNoTest();
		MQGRecommendTest.prototype = mqgYesNoTest;
		mqgTest = new MQGRecommendTest();
	} else if (mqgTestType.qc_type == 'rating') { 
		MQGRatingTest.prototype = mqgTestType;
		mqgTest = new MQGRatingTest();
	} else {
		MQGPhotoTest.prototype = mqgTestType;
		mqgTest = new MQGPhotoTest();
	}

	mqgTest.getNextQC();
	mqgTest.initEventListeners();
}(jQuery));
