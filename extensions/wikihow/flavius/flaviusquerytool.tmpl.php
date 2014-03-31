<link rel="stylesheet" type="text/css" href="/extensions/wikihow/titus/jquery.sqlbuilder.css" /
<h1>Dear Flavius Anicius Petronius Maximus. I come seeking.....</h1><br/>
<p><a href="https://docs.google.com/a/wikihow.com/document/d/1yiixeaX2dqsh8WuhdgjchBT7sL7-NJgM3YjVWLL9v2g/edit?usp=sharing">Flavius documenation</a></p>
<div style="top:25px;left:480px;position:relative;margin-top:-10px;">
<select id="days">
<option value="all">Across All Time</option>
<option value="lw">Across Last Week</option>
<option value="1">Across 1 Day</option>
<option value="7">Across 7 Days</option>
<option value="14">Across 14 Days</option>
<option value="30">Across 30 Days</option>
<option value="45">Across 45 Days</option>
<option value="60">Across 60 Days</option>
</select>
</div>

<div class="sqlbuild"></div>
<div style="background-color:#afc;">
<input class="all" id="activeUsers" checked name="chkUsers" checked type="radio" value="Across Active Users"/>Across Active Users (last 90 days)<br/>
<input class="all" id="allUsers" name="chkUsers" type="radio" value="Across All Users"/>Across All Users<br/>
<input class="these" id="theseUsers" name="chkUsers" type="radio" value="Across These Users"/>Across These Users<br/>
<textarea style="width:600px;height:180px;display:none;margin:5px;" id="userlist">
</textarea>
</div> 
<script type="text/javascript">
$(document).ready(function() {
	$('.sqlbuild').sqlquerybuilder({
	 fields:<?=json_encode($fields) ?>,
    showgroup:false,
    showcolumn:true,
    showsort:false,
    showwhere:true
	});
	$("#fetch").click(function(){
		var users = "";
		var usersType = false;

		if($("#theseUsers").is(':checked') === true) {
			users = $("#userlist").val();	
		}
		else if($("#allUsers").is(':checked') === true) {
			usersType = 'all';
		}
		else if($("#activeUsers").is(':checked') === true) {
			usersType = 'active';
		}
		var sql = $('.sqlbuild').getSQBClause('all');
		var data = {'sql' : sql,
							'users' : users,
							'usersType' : usersType,
							'days': $("#days").val()		
						  };
		$.download('/' + wgPageName, data);	
	});

	$("#userlist").hide();
	$("input.all").click(function() {
		$("#userlist").slideUp('fast');	
	});
	$("input.these").click(function(){
		$("#userlist").slideDown('slow');	
	});
});
</script>
<div>
</div>
<button id="fetch">Gimme some data...</button>

