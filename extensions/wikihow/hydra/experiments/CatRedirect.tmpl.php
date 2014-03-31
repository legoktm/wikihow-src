<style type="text/css">
     .no-close .ui-dialog-titlebar-close {
     display:none;
   }
  </style>
    <script type="text/javascript">
     (function($) {
       $(document).ready(function() {
			   var txt = "Thanks! You seem to know about \"<?= $catText ?>\". Can you help edit some more articles in the \"<?= $catText ?>\" category?<br/><br/>";
			   txt += "<div style='float:right;'><a href='#' style='text-decoration:underline' class='cancel_button' tabindex='1'>Cancel</a> <button tabindex='2' id='edit_redirect_tips_link' style='display:inline;border:none;' class='button220 button'>Yes, I want to help</button></div>";
			   $("#dialog-box").html(txt);
			   $("#dialog-box").dialog({
			     dialogClass:"no-close",
				 width: 600,
				 disabled:true,
				 modal: true,
				 closeText: 'Close',
				 title: 'Thank you for making an edit and improving this article!',
				 open: function() {
				 $(".cancel_button").click(function() {
							     $("#dialog-box").dialog("close");
							     return false;
							   });
				 $("#edit_redirect_tips_link").click(function(){
								       location = '/Special:EditRedirect2?type=greenhouse&cat=<?= $cat ?>';
								     });
				 $(".cancel_button").focus();
			       }
			     });
			 });
     })(jQuery);
</script>
