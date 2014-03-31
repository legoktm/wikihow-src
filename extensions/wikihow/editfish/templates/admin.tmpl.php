<?=$css ?>
<?=$js ?>
<?=$nav?>
<h3>Bulk Operations</h3>
<ul>
	<li><a href='/Special:<?=$adminPage?>/tagArticles'>Tag Articles</a></li>
	<li><a href='/Special:<?=$adminPage?>/removeTagArticles'>Remove Tags from Articles</a></li>
</ul>
<ul>
	<li><a href='/Special:<?=$adminPage?>/tagUsers'>Assign Tags to Users</a></li>
	<li><a href='/Special:<?=$adminPage?>/assignUser'>Assign Articles to User (Reserve Articles)</a></li>
	<li><a href='/Special:<?=$adminPage?>/completeArticles'>Mark Articles Complete for User</a></li>
	<li><a href='/Special:<?=$adminPage?>/removeTagUsers'>Remove Tags from Users</a></li>
	<li><a href='/Special:<?=$adminPage?>/releaseArticles'>Remove Articles from User (Release Articles)</a></li>
</ul>
<ul>
	<li><a href='/Special:<?=$adminPage?>/addUser'>Add User to <?=$system?></a></li>
	<li><a href='/Special:<?=$adminPage?>/removeUser'>Remove User from <?=$system?></a></li>
	<li><a href='/Special:<?=$adminPage?>/removeTagSystem'>Remove Tags from <?=$system?></a></li>
	<li><a href='/Special:<?=$adminPage?>/removeArticles'>Remove Articles from <?=$system?></a></li>
</ul>
<h3>Tag Lists</h3>
<div>
<?
if (sizeof($tags)) {
	echo "<ul class='wap_multi_col'><li>";
	echo implode("</li>\n<li>", $linker->linkTags($tags));
	echo "</li></ul>";
} else {
	echo "No articles assigned to tags";
}
?>
</div>
<h3>User Details</h3>
<div>
<?
if (sizeof($users)) {
	echo "<ul class='wap_multi_col'><li>";
	echo implode("</li>\n<li>", $linker->linkUsers($users));
	echo "</li></ul>";
}
?>
</div>

<h3>Reports</h3>
<ul>
<li><a href='/Special:<?=$adminPage?>/completedReport'>Completed Report (Last 6 Weeks)</a></li>
<li><a id='rpt_untagged_unassigned' href='#'>Untagged and Unassigned Articles</a></li>
<li><a href='/Special:<?=$adminPage?>/customReport'>Custom Report</a></li>
</ul>
<h3>Article Details</h3>
<div>
<label for='url'>URL<label> <input id='url' type='text' class='input_med' name='url'/>
<input type='button' id='article_details' name='details' class='button primary' value='Article Details'>
</div>
<div id='results'></div>
