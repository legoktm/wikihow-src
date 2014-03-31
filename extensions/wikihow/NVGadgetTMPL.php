<?php

class NetVibes {
	/*****************************************
	 ** outHeader
	 **
	 **
	 **
	 *****************************************/
	function outHeader() {
		global $wgStylePath, $wgStyleVersion;
		global $wgOut;
		global $wgRequest;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();
		$ctype = $wgRequest->getVal('ctype','application/xml');
		$allowedctypes = array('application/xml','text/xml','application/rss+xml','application/atom+xml');
		$mimetype = in_array($ctype, $allowedctypes) ? $ctype : 'application/xml';

		#header( "Content-type: $mimetype; charset=UTF-8" );
		$wgOut->sendCacheControl();

		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" ' .
		'"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
?>

<html xmlns="http://www.w3.org/1999/xhtml" xmlns:widget="http://www.netvibes.com/ns/">

   <head> 

	<meta name="author" content="wikiHow" />
	<meta name="author_email" content="vu@wikihow.com" />
	<meta name="website" content="http://www.wikihow.com" />
	<meta name="description" content="wikiHow How to of the Day" />
	<meta name="keywords" content="wiki,how to" />
	<meta name="debugMode" content="false" />
 
	<title>How-to of the Day - wikiHow</title>
	<link rel="icon" type="image/x-icon" href="http://www.wikihow.com/favicon.ico" />

	<link rel="stylesheet" type="text/css" href="http://www.netvibes.com/themes/uwa/style.css" />
	<script type="text/javascript" src="http://www.netvibes.com/js/UWA/load.js.php?env=Standalone"></script>

	<style type="text/css">
		
		body#whwidget {
			margin: 0;
			margin-left: 0;
			padding: 0;
			background-color: #FFF;
			margin: 0px auto;
			font-family: Arial, Helvetica, sans-serif;
			font-size: 12px;
			color: #111;
			position: absolute;
			left: 0;
		}

		#whwidget a {
			color: #006398;
			text-decoration: none;
		}

		#whwidget a:hover { background-color: #FFA; }

		#whwidget a.imglink:hover { background-color: #FFF; }
	
		#whwidget a.new { color: #B30000; }
	
		#whwidget img { border: none; }
	
		.floatright {
  			float: right;
  			margin: 0 0 1px 1.5px;
		}
	
		#whwidget ul, ol { margin: 0 0 20px 20px; }
	
		#whwidget h1, h2, h3, h4, h5, h6 {
			letter-spacing: 1px;
			line-height: 17px;
			margin: 0 0 0.5em 0;
		}
	
		#gadget_header { padding-bottom: 10px; }
	
		#gadget_header p {
			float: right;
			margin-top: -12px;
		}
	
		#gadget_header p img { padding-left: 4px; }
	
		#summary { border-bottom: 1px; }
	
		#summary a { color: #006398; }
	
		#summary h3 { padding-top: 5px; }
	
		#summary h3 A {
			font-size: .9em;
			letter-spacing: 0;
			color: #006398;
			line-height: .9; 
		}
	
		#items {
			background-color: #FFF;
			padding: 3px;
			margin-top: 15px;
			padding: 5px 1px;
		}
	
		#items ul {
			margin-bottom: 1px;
			padding-bottom: 1px;
			color: #999;
		}
	
		#items ul a {
			font-size: 1em;
			color: #006398;
		}

	</style>

	<widget:preferences />

	<script type="text/javascript">
		widget.onLoad = function() {
		  widget.callback('onUpdateBody');
		}
	</script>

   </head>

   <body id="whwidget">
<?php
	} //outHeader NetVibes


	/*****************************************
	 ** outMain
	 **
	 **
	 **
	 *****************************************/
	function outMain( $title_text, $summary, $url ) {
		global $wgServer;
?> 	<div id="gadget_header">
		<a href="http://www.wikihow.com/Main-Page" target="_blank"><img src="http://www.wikihow.com/images/wikihow_gadget.gif" width="130" height="38" alt="wikiHow"  /></a>
	</div>

	<div id="summary">
		<h3><a href="<?php print $wgServer ?>/<?php print $url ?>" target="_blank"><?php print $title_text ?></a></h3>
		<?php print $summary ?>
	</div>

<?php
	} //outMain NetVibes


	/*****************************************
	 ** outItem
	 **
	 **
	 **
	 *****************************************/
	function outItem( $title_text, $summary, $url, $count ) {
		global $wgServer ;
		$item =  "\t\t\t" . '<li> <a href="'.$wgServer.'/'.$url.'" target="_blank">'.$title_text.'</a> </li>' . "\n";
		return $item;
	} //outItem NetVibes

	/*****************************************
	 ** outItemList
	 **
	 **
	 **
	 *****************************************/
	function outItemList ( $itemList ) {
?>
	<div id="items">
		<ul>
<?php print $itemList  ?>
		</ul>
	</div>
<?php
	} //outItemList NetVibes
	
	/*****************************************
	 ** outFooter
	 **
	 **
	 **
	 *****************************************/
	function outFooter() {
?>
   </body>
</html>
<?php
	} //outFooter NetVibes
} //class NetVibes
?>
