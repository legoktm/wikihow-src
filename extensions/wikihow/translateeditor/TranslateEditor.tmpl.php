<script type="text/javascript">
var llText="";
<?php if($checkForLL) { ?>
function checkForLL() {
	var txt = $("#wpTextbox1").val();
	var re = /\[\[en:.+\]\]/;
	var m = txt.match(re);
	if(m == null) {
		if(llText != undefined && llText!="") {
			alert("You must have the following interwiki link in your translated wikitext before saving or previewing:\n " + llText); 
		}
		else {
			alert("You must have a valid language link to English before saving or previewing this translation.");
		}
		return(false);	
	}
	else if(llText!="" && m[0] != llText) {
		alert("You must have following and only the following English interwiki link in your translated wikitext before saving or previewing:\n " + llText);	
		return(false);
	}
	else {
		return(true);
	}
}
<?php } ?>
jQuery(document).ready(function() {
<?php if($checkForLL) { ?>
<?php //Hack to remove un-needed stuff from editor
?> 	$("#othereditlink").hide();
	$(".editpage_links").hide();
	$("#tabs").hide();
	$('#bodycontents #editform').contents().filter(function(){
    return this.nodeType === 3;
		}).remove();
	$("#wpSave").click(function(){
		return(checkForLL());	
	});
	$("#wpPreview").click(function(){
		return(checkForLL());	
	});

	<?php } ?>
	jQuery(".mw-newarticletext").hide();
<?php if($translateURL) { ?>
	jQuery("#editform").hide();
	jQuery("#translate").click(function(){
			var url=jQuery("#translate_url").val();
			$("#translate_url").attr("disabled","disabled");
			$("#translate").attr("disabled","disabled");
			var re= /http:\/\/www.wikihow.com\/(.+)/;
			var m = url.match(re);
			var translations = <?php print $translations ?>;
			var remove_templates = <?php print json_encode($remove_templates) ?>;
			if(m) {
					var article=m[1];
					$.ajax({'url':"/Special:TranslateEditor",'data':{'target':article,'action':"getarticle",'toTarget': wgTitle} ,'success':function(data) {
						var jData;
						try {
							jData = JSON.parse(data);
						}
						catch(err) {
							alert("Unable to fetch article. Please make sure you are logged in to wikiHow, and your internet connection is working properly.");
							return;
						}
						if(jData.success) {
								var revision = jData.text;
								for(var n in translations) {
									revision = revision.replace(new RegExp(translations[n].from,"gi"), translations[n].to);
								}
								for(var n in remove_templates) {
									revision = revision.replace(new RegExp(remove_templates[n],"gi"),"");	
								}

								llText = "[[en:" + article.replace(/-/g," ") + "]]";
								$("#wpTextbox1").val(revision + "\n" + llText);
								$("#editform").show();
							}
							else {
								alert(jData.error);
								$("#translate_url").removeAttr("disabled");
								$("#translate").removeAttr("disabled");

							}
						},error:function() {
							alert("Unable to fetch article. Please ensure your internet connection is working properly.");	
						}
				});
			}
			else {
		    $("#translate_url").removeAttr("disabled");
				$("#translate").removeAttr("disabled");
				alert("You must enter a url starting with http://www.wikihow.com to translate");	
			}
			});
<?php } ?>
});
</script>
<?php if($translateURL) { ?>
<div id="translatesection">
<form>
<div style="margin-left:15px;"> 
<p> 
<label for="translate_url">Enter the English URL you want to translate:</label><br/><br/><input type="text" style="width:600px;font-size:14pt;" id="translate_url" />
</div>
<input style="margin-left:13px;" type="button" id="translate" value="Fetch English Article to Translate" /><br/><br/>
<div id="article_editor">
</div>
</form>
</div>
<?php } ?>
