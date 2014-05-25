<div class="wh_block">
	<h1 class="firstHeading">Creating "How to <?=htmlspecialchars($pageTitle)?>"</h1>
	<div class="editpage_links">
		<?=$advancedEditLink?>
	</div>
</div>
<div id='ac_token'><?=$token?></div>
<div id='<?=$idname?>' class="section <?=$idname?>">
	<h2><?=$name?></h2>
	<div class='ac_desc'><?=$desc?></div>
	<div class='ac_editor'>
		<div id='ac_content' >
			<ul class='ac_lis'></ul>
		</div>
		<div class='ac_li_adder'>
			<div class='clearall'></div>
			<textarea class='ac_new_li' placeholder='<?=$placeholder?>'></textarea>
			<a class='button secondary ac_add_li'><?=$buttonTxt?></a>
			<div class='clearall'></div>
		</div>
	</div>
</div>