Editing <a href="<?= $title->getLocalURL() ?>"><?= str_replace('-',' ',$title->getText()) ?></a> was recommended to <a href="/User:<?= $user->getName() ?>"><?= $user->getName()?></a>, because of edits to the following:
<ul>
<?php foreach($pageTitles as $pageTitle) { ?>
<li><a href="/index.php?title=<?= $pageTitle ?>&action=history"><?= str_replace("-"," ",$pageTitle)?></a></li>
<? } ?>
</ul>
