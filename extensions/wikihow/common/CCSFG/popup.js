(function($) {
	$(document).ready(function(){

		$('#ccsfg_btn').click(function(){
		
			var url = "/extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js";
			$.getScript(url, function() {

				jQuery('#dialog-box').load('/Special:FollowWidget?getEmailForm=1', function() {
					
					var _dialog = $('#ccsfg');
					var _title = _dialog.find('h4').html();
					_dialog.attr('title', _title);
					_dialog.find('h4').hide();
					var _action = _dialog.attr('action');
					var _submitBtn = _dialog.find('#signup');
					var _submitBtnText = _submitBtn.val();
					_submitBtn.remove();

					var dialog_buttons = {};
					dialog_buttons[_submitBtnText] = function(){

						query = $(_dialog).serializeArray()
						json = {};
						for (i in query) { json[query[i].name] = query[i].value	}
						json['RequestType'] = 'ajax';
						$(".waiting").show();
						$('.ui-dialog-buttonpane button').attr('disabled', 'disabled');
						$.get(_action, json,
						   function(data){
								tmp = $('<div id="tmp"></div>');
								$(tmp).html(data);
								code = $(tmp).find('#code').attr('title');
								if(code==201){
									if(json['SuccessURL']){ window.location = json['SuccessURL']; }
								}
								else {
									if(json['FailureURL']){ window.location = json['FailureURL']; }
								}
							$('#ccsfg').html(data);
							$('#close').click(function(){
								$('#ccsfg').dialog('close');
								return false;
							});
							_btnPane = $('.ui-dialog-buttonpane');
							_btnPane.remove();
						   });
					};
					
					_dialog.dialog({
						autoOpen:false,
						resizable:false,
						draggable:false,
						width: 500,
						buttons:dialog_buttons,
						modal: true,
						closeText: 'Close'
					});
					
					_dialog.dialog( 'open' );
					
					$('.ui-close').click(function(){
						$('#ccsfg').dialog('close');
					});
				});
				
				/*_dialog.dialog({
					autoOpen:false,modal:true, resizable:false, closeText:'close',
					draggable:false, width:500, buttons: dialog_buttons
				});*/

			});
		});

	});
})(jQuery);
