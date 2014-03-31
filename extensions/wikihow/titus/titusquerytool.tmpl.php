<script type="text/javascript" src="/extensions/wikihow/common/download.jQuery.js"></script>
<script type="text/javascript" src="/extensions/wikihow/titus/jquery.sqlbuilder-0.06.js"></script>
<link rel="stylesheet" type="text/css" href="/extensions/wikihow/titus/jquery.sqlbuilder.css" />
<style type="text/css">
.urls {
	margin-top: 5px;
	height: 300px;
	width: 850px;
}
</style>

<script type="text/javascript">

$(document).ready(function() { 

    $('.sqlbuild').sqlquerybuilder({ 
		fields: <?=$dbfields?>,
        showgroup:false,
        showcolumn:true,
        showsort:false,
		showwhere:true
    }); 

    $('.fetch').click(function(){
		var sql = $('.sqlbuild').getSQBClause('all');
		if (!sql.length && (!sql.length && (!$('#urls').val().length && $('#filter_urls').is(':checked')))) {
			var answer = confirm("WARNING: You have not given me any conditions to filter this report.  Repeated intensive queries make me angry and cause me to destroy temples in holy lands. \n\n Click the OK button if this is really what you want.");
			if (!answer) {
				return false;
			}
		}
		var data = {
			'sql' : sql,
			'urls': $('.urls').val(),
			'page-filter': $('input[name=page-filter]:checked').val(),
			'csvtype' : $('input[name=csvtype]:checked').val(),
			'ti_exclude' : $('#ti_exclude').is(':checked') ? 1 : 0
		};
		$.download('/' + wgPageName, data);           
    
		return false;
    }); 


    $('#getsql').click(function(){
     	alert($('.sqlbuild').getSQBClause('all')); 
     	return false;
    }); 
	
	$('.sqlbuildercolumn').on('click', '.ti_page_title_column', function(e) {
		e.preventDefault();
		$(this).parent().slideUp(200, function() {
			$(this).remove();
			
		});
	});

	
	$('input.all').click(function() {
		$('.urls').slideUp('fast');
	});

	$('input[value=urls]').click(function() {
		$('.urls').slideDown('slow');
	});

    
});


</script>
<?php if(!IS_CLOUD_SITE && !IS_DEV_SITE) { ?>
<span style="color:red;background-color:#222;font-size:30pt">titus.wikiknowhow.com is deprecated. Please switch to Cloud Titus at <a href="https://cloudtitus.wikiknowhow.com/">https://cloudtitus.wikiknowhow.com</a>.</span>
<? } ?>
<p>
Titus <a href="https://docs.google.com/a/wikihow.com/spreadsheet/ccc?key=0Ag-sQmdx8taXdC1BWWlFdFVBa3FJM09rZUZhemliZEE#gid=0">cheat sheet</a>
</p>
<div id=sqlreport>
<div class="sqlbuild"></div>
</div>
<div style="margin-top: 10px">
		<input id="filter_all" class="all" type="radio" name="page-filter" value="all"> Across All Languages 
		<?php foreach($languages as $lg) { ?>
		<input class="all" id="filter_<?php print $lg['languageCode']?>" type="radio" name="page-filter" value="<?php print $lg['languageCode']?>"> Across all <?php print $lg['languageName'] ?>
		<?php } ?>
		<input id="filter_urls" type="radio" name="page-filter" value="urls" checked="checked"> Given the following URLs
</div>
<textarea class="urls" rows="500" name="urls" id="urls"></textarea><br/>
<input id="ti_exclude" name="ti_exclude" type="checkbox"></input> Filter invalid, not found and <strong>wikiphoto-article-exclude-list</strong> articles
<div style="margin-top: 10px">
<button class="fetch" style="padding: 5px;" value="CSV">Gimme</button>
<a id='getsql' href='#'>SQL</a>
</div>
