<h3>My Recent Activity</h3>
<div id="rcElement_list">
	<?php foreach($elements as $key => $element): ?>
		<? 
			if($key == "servertime" || $key == "unpatrolled") continue;
			if($key > 12) break;
		?>
		<div class="rc_widget_line <?= ($key%2==0?"even":"odd") ?>" style="">
			<div class="rc_widget_line_inner">
				<?= $element['text'] ?><br /><span class="rc_widget_time"><?= $element['ts'] ?></span>
			</div>
		</div>
	<?php endforeach ?>
</div>