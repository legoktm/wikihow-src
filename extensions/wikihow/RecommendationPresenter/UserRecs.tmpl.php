<html>
<head>
<script type="text/javascript" src="/extensions/wikihow/common/jquery-1.7.1.min.js"></script>
<script type="text/javascript">
function showRecPopup(t,user_id, page_id) {
	$.ajax({'type':'post','url':'/Special:RecommendationAdmin?user_id=' + user_id + "&page_id=" + page_id, 'data' : {}, 'success':function(data) {
		var os = $(t).offset();
		$("#recpopup").offset(os);
		$("#recpopup").html(data)
		$("#recpopup").show();
	} }); 
}
</script>
<style>
.gray {
	background-color:#aaa;
}
</style>
</head>
<table>
<thead><tr><td>Username</td><td>Recommendations</td></tr></thead>
<tbody>
<?php
$n = 0;
foreach($rows as $row) {?>
<tr <? if($n % 2 == 0) {?> class="gray"<? } ?>><td><a href="/User:<?= $row['user_name'] ?>"><?= $row['user_name'] ?></a></td><?php
foreach($row['recommendations'] as $recommendation) {
?>
	<td id="<?= $row['user_id'] ?>_<?= $row['page_id']?>" onmouseover="javascript:showRecPopup(this, <?= $row['user_id']?>, <?= $recommendation['page_id']?>)" <?php if($recommendation['date_used']) {?> style="background-color:red;" <?php } elseif($recommendation['views'] > 0) {?> style="background-color:green;"<?php }?>><a href="?user_id=<?= $row['user_id'] ?>&page_id=<?= $recommendation['page_id']?>"><?= $recommendation['title'] ?></a>(<?= $recommendation['score']?>)</td>
<?php
} ?></tr>
<?php 
	$n++;
} ?>
</tbody>
</table>
<div id="recpopup" style="position:absolute;width:250px;height:250px;border:1px black solid;background-color:#DDD;display:hidden;">

</div>
</html>
