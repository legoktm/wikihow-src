<?php
/**********************************************************
 ** Class GoogGadgetModule
 **
 **
 **
 **********************************************************/
class GoogleGadgetModule {

	/************************************************
	 ** Function outModulePrefs
	 **
	 **
	 **
	 ************************************************/
	function outModulePrefs() {
		global $wgServer, $wgOut, $wgRequest;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();
		print "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
?>


<Module>
	<ModulePrefs 
		title="wikiHow - How to of the Day"
		title_url="<?php echo $wgServer; ?>/Main-Page"
		description="Learn (and occasionally laugh) with wikiHow's, &quot;How-to of the Day&quot;.  Try a new skill every day after reading these articles with step by step images and videos. Learn new skills, solve everyday problems. From wikiHow, the wiki how-to manual."
		scrolling="false"
		screenshot="<?php echo $wgServer; ?>/images/igoogle_screenshot.png"
		thumbnail="<?php echo $wgServer; ?>/images/wikiHow_120.gif"
		author="wikihow.com" 
		author_email="support@wikihow.com" 
		author_location="San Francisco, CA" >
		<Require feature="dynamic-height"/>
		<Require feature="analytics" /> 
	</ModulePrefs>
<?php #	<UserPref name="ndisplay" display_name="Number of Articles" datatype="string" required="true" default_value="3" /> ?>

	<Content type="html" view="home" >
	<![CDATA[

		<link rel="stylesheet" type="text/css" href="<?php echo $wgServer; ?>/extensions/wikihow/igg2.css" /> 
		<div id="igcontent_div"></div>
		<script type="text/javascript" src="<?php echo $wgServer; ?>/extensions/wikihow/igg.js"></script>
		<script type="text/javascript" >
			var wgServer = "<?php echo $wgServer; ?>";
		
			_IG_RegisterOnloadHandler(getHTML);
			_IG_Analytics("UA-2375655-1", "/igGadgetHome");

		</script>
	
	]]>
	</Content>

	<Content type="html" view="canvas" >
	<![CDATA[

		<link rel="stylesheet" type="text/css" href="<?php print $wgServer; ?>/extensions/wikihow/igg2.css" /> 
		<div id="igcontent_div"></div>
		<script type="text/javascript" src="http://www.google.com/coop/cse/brand?form=cse-search-box&lang=en"></script>
		<script type="text/javascript" src="<?php echo $wgServer; ?>/extensions/wikihow/igg.js"></script>
		<script type="text/javascript" >
			var wgServer = "<?php echo $wgServer; ?>";
		
			_IG_RegisterOnloadHandler(getHTMLCanvas);
			_IG_Analytics("UA-2375655-1", "/igGadgetCanvas");

		</script>
	
	]]>
	</Content>

</Module>


<?php
	} // outModulePrefs


} //class GoogleGadgetModule


/**********************************************************
 ** Class GoogGadgetHome
 **
 **
 **
 **********************************************************/
class GoogleGadgetHome {

	/************************************************
	 ** Function outHeader
	 **
	 **
	 **
	 ************************************************/
	function outHeader() {
		global $wgServer, $wgOut, $wgRequest;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();

/*
<link rel="stylesheet" type="text/css" href="<?php print $wgServer ?>/extensions/wikihow/igg.css" /> 
*/
if ($wgRequest->getVal('debug') == 'true') { ?>
<link rel="stylesheet" type="text/css" href="<?php echo $wgServer ?>/extensions/wikihow/igg-test.css" /> 
<link rel="stylesheet" type="text/css" href="<?php echo $wgServer ?>/extensions/wikihow/igg.css" /> 
<?php }

		?>

<link rel="stylesheet" type="text/css" href="<?php echo $wgServer ?>/extensions/wikihow/igg.css" /> 
<div id="wh">
	<?php
	} //outHeader

	/************************************************
	 ** Function outMain
	 **
	 **
	 **
	 ************************************************/
	function outMain( $title_text, $summary, $imageval, $url ) {
		global $wgServer, $wgOut, $wgRequest;

?>

	<span style="float: left; text-align:left; margin: 0 0 0 0;">
	<h3><a href="<?php print $url ?>" target="_blank"><?php print $title_text ?></a></h3>
	<?php print $imageval . "\n"; ?>
	<p><?php print $summary . "\n"; ?></p>
	</span>

	<br />


<?php
	} //outMain


	/************************************************
	 ** Function outItem
	 **
	 **
	 **
	 ************************************************/
	function outItem( $title_text, $summary, $url, $count ) {
		$listitem = "\t\t<li><a href=\"".$url."\" target=\"_blank\">".$title_text."</a> </li> \n";
		return $listitem;
	} //outItem

	/************************************************
	 ** Function outItemList
	 **
	 **
	 **
	 ************************************************/
	function outItemList( $faItems ) {
?>
	<span style="width: 100%; float: left; text-align:left; margin: 0 0 0 0;">
	<ul>
<?php print $faItems;?>
	</ul>
	</span>
<?php
	} //outItemlist
	
	/************************************************
	 ** Function outFooter
	 **
	 **
	 **
	 ************************************************/
	function outFooter() {
?>
</div>
<?php
	} //outFooter

} //class


/**********************************************************
 ** Class GoogGadgetHome2
 **
 **
 **
 **********************************************************/
class GoogleGadgetHome2 {



	/************************************************
	 ** Function outHeader
	 **
	 **
	 **
	 ************************************************/
	function outHeader() {
		global $wgServer, $wgOut, $wgRequest;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();

?>

<div id="iggadget" class="iggadget">
<link rel="stylesheet" type="text/css" href="<?php print $wgServer; ?>/extensions/wikihow/igg2.css" /> 
<script type="text/javascript" src="<?php echo $wgServer; ?>/extensions/wikihow/igg.js"></script>
<script type="text/javascript" >
	var wgServer = "<?php echo $wgServer; ?>";
	init();
</script>

<?php /*
<div id="header" style="float: left;width: 100%;padding: 5px;">
<table >
<tr><td>
    <p id="logo">
        <a href='http://www.wikihow.com/Main-Page'   target="_blank" class="imglink">
        <img src="<?php print $wgServer; ?>/skins/WikiHow/wikiHow.gif" id="wikiHow" height="30" alt="wikiHow - The How-to Manual That You Can Edit" /><br />
        </a>
     </p>
</td>
</tr></table>
</div>
*/ ?>

<?php
	} //outHeader

	/************************************************
	 **
	 **
	 **
	 ************************************************/
	function outMain( $title_text, $summary, $url, $itemnum ) {
		global $wgServer;
?>
<div style="padding:3px;">
<a id="article-<?php echo $itemnum ?>-exp" onclick="expand(this);" >
<img id="article-<?php echo $itemnum ?>-img" src="<?php echo $wgServer ?>/extensions/wikihow/igg-plus-light.png" height="11px" width="11px" onmouseover="expMouseOver(this);" onmouseout="expMouseOut(this);"/></a>
<a href="<?php echo $wgServer ?>/<?php echo $url ?>" target="_blank"><?php print $title_text ?></a><br>
</div>

<div id="article-<?php echo $itemnum ?>" style="padding:3px;overflow:auto;height:400px;display:none;">
<div id="article">
<h3><a href="<?php echo $wgServer ?>/<?php echo $url ?>" target="_blank"><?php print $title_text ?></a></h3>
<?php print $summary ?>
</div>
</div>
<?php
	} //outMain


	/************************************************
	 ** Function outFooter
	 **
	 **
	 **
	 ************************************************/
	function outFooter() {
		echo '</div>' . "\n";
	} //outFooter




} //class


/**********************************************************
 ** Class GoogGadgetCanvas
 **
 **
 **
 **********************************************************/
class GoogleGadgetCanvas {



	/************************************************
	 ** Function outHeader
	 **
	 **
	 **
	 ************************************************/
	function outHeader() {
		global $wgServer, $wgOut, $wgRequest;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();

?>

<div class="iggadget">
<link rel="stylesheet" type="text/css" href="<?php print $wgServer; ?>/extensions/wikihow/igg2.css" /> 

<div id="header" style="float: left;width: 100%;padding: 5px;">
<table >
<tr><td>
    <p id="logo">
        <a href='http://www.wikihow.com/Main-Page'   target="_blank" class="imglink">
        <img src="<?php print $wgServer; ?>/skins/WikiHow/wikiHow.gif" id="wikiHow" height="30" alt="wikiHow - The How-to Manual That You Can Edit" /><br />
        </a>
     </p>
</td><td align="right" valign="top">

                <form action="<?php print $wgServer; ?>/Special:GoogSearch" id="cse-search-box">
                <input type="hidden" name="cx" value="partner-pub-9543332082073187:36zk2w-ig14" />
                <input type="hidden" name="cof" value="FORID:11" />
                <input type="hidden" name="ie" value="UTF-8" />
                <input type="text" name="q" size="40" value=""/>
                <input type="submit" name="sa" value="Search" />
                </form>
                <!-- <script type="text/javascript" src="http://www.google.com/coop/cse/brand?form=cse-search-box&lang=en"></script>  -->
</td></tr></table>
</div>

<div id="articleTop" style="float: left; width: 70%;padding: 10px 3px 10px 3px;">

<?php
	} //outHeader

	/************************************************
	 **
	 **
	 **
	 ************************************************/
	function outMain( $title_text, $summary, $url ) {
		global $wgServer;
?>
<span style="padding-bottom: 30px; margin-bottom: 30px;">
<div id="article">

<h3 style="font-size:16px;"><a href="<?php echo $wgServer ?>/<?php echo $url ?>" target="_blank"><?php print $title_text ?></a></h3>
<?php print $summary ?>
</div>
<br />
<br />
</span>
<?php
	} //outMain


	/************************************************
	 **
	 **
	 **
	 ************************************************/
	function outFeaturedArticles( $featured ) {
?>
</div>

<div id="aside">
<h3>Featured Articles</h3>
<ul>
<?php print $featured ?>
</ul>
<?php
	}

	/************************************************
	 **
	 **
	 **
	 ************************************************/
	function outRelatedArticles( $related ) {
		print '<h3>Related Articles</h3>'."\n";
		print $related;
	}

	/************************************************
	 ** Function outFooter
	 **
	 **
	 **
	 ************************************************/
	function outFooter() {
		echo '</div>' . "\n";
	} //outFooter




} //class

?>
