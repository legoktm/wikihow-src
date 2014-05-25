<div id='<?=$idname?>' class="ac_other_section section <?=$idname?>">
	<h2><?=$name?></h2>
	<div class='ac_desc'><?=$desc?></div>
	<div class='ac_editor'>
		<div class='ac_content' class='ui-draggable'>
			<ul class='ac_lis <!--ui-sortable-->'></ul>
		</div>
		<div class='ac_li_adder'>
			<div class='clearall'></div>
			<textarea class='ac_new_li' placeholder='<?=$placeholder?>'></textarea>
			<a class='button secondary ac_add_li'><?=$buttonTxt?></a>
			<div class='clearall'></div>
		</div>
	</div>
</div>
