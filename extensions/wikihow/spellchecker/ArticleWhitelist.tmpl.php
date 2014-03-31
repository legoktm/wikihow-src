<?= $message ?>
<h3><?= wfMsg("spch-articlelist-add") ?></h3>
<form action="/Special:SpellcheckerArticleWhitelist" method="POST">
	<?= wfMsg("spch-articlelist-url") ?> 
	<input type="text" name="articleName" style="width:200px" />
	<input type="submit" value="Add Article" />
</form>
<br /><br />
<h3><?= wfMsg("spch-articlelist-current") ?></h3>