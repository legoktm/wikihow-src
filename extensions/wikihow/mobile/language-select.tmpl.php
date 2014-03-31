    <div id="languages">
        <table cellpadding="0" cellspacing="0">
			<? foreach ($languages as $lang): ?>
				<tr>
					<td class="flag"><a href="#"><img src="<?= $lang['img'] ?>" alt="" /></a></td>
					<td class="description"><a href="<?= $lang['url'] ?>"><?= $lang['name'] ?></a></td>
					<td class="arrow"><a href="<?= $lang['url'] ?>"><img src="<?= wfGetPad('/extensions/wikihow/mobile/images/arrow.gif') ?>" alt="" /></a></td>
				</tr>
			<? endforeach; ?>
        </table>
    </div>
