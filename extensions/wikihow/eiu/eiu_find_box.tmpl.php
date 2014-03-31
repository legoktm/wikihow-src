<form id="eiu-image-search" action="#" onsubmit="easyImageUpload.loadImages('current', jQuery('#search_image_query').val(), 1); return false;">
<input type='text' id='search_image_query' value='<?= htmlspecialchars($title, ENT_QUOTES) ?>' /> 
<input type='submit' value='<?= wfMsg('eiu-find') ?>' class='search_button' />
</form>

<ul id="eiu-image-search-tabs">
	<li class="eiu-selected"><a id="eiu-flickr-link" href="#" onclick="easyImageUpload.loadImages('flickr', jQuery('#search_image_query').val(), 1); return false;"><?= wfMsg('eiu-flickr') ?></a></li>
	<li><a id="eiu-this-wiki-link" href="#" onclick="easyImageUpload.loadImages('wiki', jQuery('#search_image_query').val(), 1); return false;"><?= wfMsg('eiu-wikimedia') ?></a></li>
	<li><a id="eiu-local-link" href="#" onclick="easyImageUpload.loadImages('local', jQuery('#search_image_query').val(), 1); return false;"><?= wfMsg('eiu-localfile') ?></a></li>
</ul>
<div id='eiu_recently_uploaded' class='wh_block' style='text-align: center;'>
