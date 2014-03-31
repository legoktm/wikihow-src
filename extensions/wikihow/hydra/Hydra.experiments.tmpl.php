<style type="text/css">
td { padding:3px; }
.hydra_right { text-align:right;}
</style>
<a href="https://docs.google.com/a/wikihow.com/spreadsheet/ccc?key=0Aoa6vV7YDqEhdDBMSnVHOHk4ZExNV2JHWkxFQ2hpaGc#gid=0">Experiment Screenshots</a>
<table>
<tbody>
<?php foreach($trials as $name => $results) {
?>
<tr><td style="width:50px;"><?php print $name; ?></td><td>
<table>
<tr style="background-color:gray;color:white;"><td style="width:80px;">Trial</td><td>Number in Trial</td><td>10+ Edits</td><td>1+ Edits</td><td>Avg. Total Edits</td><td></td><td style="width:220px;">Experiment Name</td></tr>
<?php foreach($results as $result) { ?>
	<tr><td><b><? if($result['experiment'] == 'control') { ?>control <? } else {?> trial <?= $result['trial'] ?><? } if($result['pct'] != NULL) {?> (<?= $result['pct'] ?>%) <? } ?></b></td><td class="hydra_right">  <?= $result['count'] ?></td><td class="hydra_right"><?= $result['success'] ?></td><td class="hydra right"><?= $result['success2'] ?></td><td class="hydra_right"><?= $result['avg_total'] ?></td><td><a href="?group=<?= $result['group']?>&experiment=<?= $result['experiment']?>">Users</a></td><td><?= $result['experiment'] ?></td></tr>	
<?php  } ?>
</table>
<?php
	} ?>
</table></td></tr>
</tbody>
</table>
