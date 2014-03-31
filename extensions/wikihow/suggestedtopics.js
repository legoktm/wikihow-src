var howtoDefaultText = "How to...";

jQuery(document).ready(function(){
	jQuery('#entry_howto').focus(howtoFocus);
	jQuery('#entry_howto').blur(howtoBlur);
	jQuery('#choose_cats').click(function(){
		jQuery('#dialog-box').html('');
		jQuery('#dialog-box').load('/Special:SuggestCategories', function(){
			jQuery('#dialog-box').dialog( "option", "position", 'center' );
			jQuery('#dialog-box td').each(function(){
				var myInput = $(this).find('input');
				var position = $(this).position();
				$(myInput).css('top', position.top + 10 + "px");
				$(myInput).css('left', position.left + 10 + "px");
				$(this).click(function(){
					choose_cat($(this).attr('id'));
				})
			})
			jQuery('#check_all_cats').click(function(){
				var cats = jQuery('form input:checkbox');
				var bChecked = jQuery(this).prop('checked');
				for (i=0;i<cats.length;i++) {
					var catid = cats[i].id.replace('check_','');
					choose_cat(catid,bChecked);
				}
			});
		});
		jQuery('#dialog-box').dialog({
			width: 826,
			modal: true,
			title: 'Categories'
		});
		return false;
	});
});

function openCategories(){
	
}

function howtoFocus(){
	if(jQuery(this).val() == howtoDefaultText)
		jQuery(this).val("");
}

function howtoBlur(){
	if(jQuery(this).val() == "")
		jQuery(this).val(howtoDefaultText);
}

function changeCat() {
	location.href='/Special:ListRequestedTopics?category=' + escape(document.getElementById('suggest_cat').value);
}

function saveSuggestion() {
	var n = document.getElementById('newsuggestion').value;
	var id = document.getElementById('title_id').value;
	document.suggested_topics_manage["st_newname_" + id].value = n;
	document.getElementById("st_display_id_" + id).innerHTML= n;
    for (i=0;i<document.suggested_topics_manage.elements.length;i++) {
        if (document.suggested_topics_manage.elements[i].type ==    'radio'
            && document.suggested_topics_manage.elements[i].name == 'ar_' + id
            && document.suggested_topics_manage.elements[i].value == 'accept') {
            document.suggested_topics_manage.elements[i].checked = true;
        }
    }
	
	$('#dialog-box').dialog('close');
}

var gName = null;
function editSuggestion(id) {
	gName = $('#st_display_id_' + id).html();
	$('#dialog-box').load('/Special:RenameSuggestion?name='+escape(gName)+'&id='+id);
	$('#dialog-box').dialog({
		modal: true,
		title: 'Edit title',
		closeText: 'Close',
		width: 500
	});
	return false;
}

function checkSTForm() {
	if (document.suggest_topic_form.suggest_topic.value =='') {
		alert(gEnterTitle);
		return false;
	}
	if (document.suggest_topic_form.suggest_category.value =='') {
		alert(gSelectCat);
		return false;
	}
	if (document.suggest_topic_form.suggest_email_me_check.checked && document.suggest_topic_form.suggest_email.value =='') {
		alert(gEnterEmail);
		return false;
	}
	return true;
}

 function choose_cat(key,bChoose) {
	var safekey = key.replace("&", "and");
 	var e = $("#" + safekey);
	
	//forcing it or based off the setting?
	if (bChoose == null)
		bChoose = (e.hasClass('not_chosen')) ? true : false;
	
 	if (bChoose) {
 		e.removeClass('not_chosen');
 		e.addClass('chosen');
 		document.suggest_cats.cats.value += ", " + key;
		jQuery('#check_' + safekey).prop('checked', true);
 	} else {
 		e.removeClass('chosen');
 		e.addClass('not_chosen');
 		var reg = new RegExp (key, "g");
 		document.suggest_cats.cats.value = document.suggest_cats.cats.value.replace(reg, '');
		jQuery('#check_' + safekey).prop('checked', false);
 	}
 }

function reloadTopRow(){
	$("#top_suggestions_top").fadeOut(400, function() {
		if (Math.random() < 0)  {
			// bat boy easter egg!
		    jQuery('#dialog-box').html('<center><img src="http://www.freakingnews.com/images/contest_images/bat-boy.jpg" style="height: 400px;"/><br/>');
    		//jQuery('#dialog-box').load(url);
    		jQuery('#dialog-box').dialog({
        		modal: true,
				width: 400,
        		title: 'Surprise!',
        		show: 'slide',
        		closeOnEscape: true,
        		position: 'center'
    		});
		}
		$("#top_suggestions_top").load('/Special:RecommendedArticles/TopRow',
            	function () {
				 	$("#top_suggestions_top").fadeIn();
            	}
        	);
	}
	);
}

