<?

class FeaturedRSSFeed extends RSSFeed {

        function outHeader() {
                global $wgVersion, $wgServer, $wgStylePath, $wgScriptPath;

                $this->outXmlHeader();
		?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
        <channel>
                <title><?php print $this->getTitle() ?></title>
                <copyright>This work is licensed under a Creative Commons Attribution-NonCommercial-ShareAlike 2.5 License, except where noted otherwise.</copyright>
                <link><?php print $this->getUrl() ?></link>
                <description><?php print $this->getDescription() ?></description>
                <language><?php print $this->getLanguage() ?></language>
                <lastBuildDate><?php print $this->formatTime( wfTimestampNow() ) ?></lastBuildDate>
                <ttl>20</ttl>
                <image>
                        <title>wikiHow</title>
                        <width>144</width>
                        <height>37</height>
                        <link><?php print $this->getUrl() ?></link>
                        <url><?php print $wgServer . $wgStylePath ?>/WikiHow/wikiHow.gif</url>
                </image>
<?php
        }

        function outItem( $item ) {
        ?>
                <item>
                        <title><?php print $item->getTitle() ?></title>
                        <link><?php print $item->getUrl() ?></link>
                        <guid isPermaLink="true"><?php print $item->getUrl() ?></guid>
                        <description><?php print $item->getDescription() ?></description>
                        <?php if( $item->getDate() ) { ?><pubDate><?php print $this->formatTime( $item->getDate() ) ?></pubDate><?php } ?>
                        <?php if( $item->getAuthor() ) { ?><dc:creator><?php print $item->getAuthor() ?></dc:creator><?php }?>
                </item>
<?php
        }

        function outHeaderFullFeed() {
                global $wgVersion, $wgServer, $wgStylePath, $wgScriptPath, $wgLanguageCode;

                $this->outXmlHeader();

		?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" >
        <channel>
                <title><?php print $this->getTitle() ?></title>
                <copyright>This work is licensed under a Creative Commons Attribution-NonCommercial-ShareAlike 2.5 License, except where noted otherwise.</copyright>
                <link><?php print $this->getUrl() ?></link>
                <description><?php print $this->getDescription() ?></description>
                <language><?php print $this->getLanguage() ?></language>
                <lastBuildDate><?php print $this->formatTime( wfTimestampNow() ) ?></lastBuildDate>
                <ttl>20</ttl>
                <image>
                        <title>wikiHow</title>
                        <width>144</width>
                        <height>37</height>
                        <link><?php print $this->getUrl() ?></link>
                        <url><?php print $wgServer . $wgStylePath ?>/WikiHow/wikiHow.gif</url>
                </image>
<?php
        }

        function outItemFullFeed( $item, $content, $images ) {
			global $wgLanguageCode;
        ?>
                <item>
                        <title><?php print $item->getTitle() ?></title>
                        <link><?php print $item->getUrl() ?></link>
                        <guid isPermaLink="true"><?php print $item->getUrl() ?></guid>
                        <description><?php print $item->getDescription() ?></description>
                        <content:encoded><![CDATA[<?php print $content ?>]]></content:encoded>
                        <?php if( $item->getDate() && $wgLanguageCode == 'en') { ?>
								<pubDate><?php print $this->formatTime( $item->getDate() ) ?></pubDate>
								<?php } ?>
                        <?php if( $item->getAuthor() ) { ?><dc:creator><?php print $item->getAuthor() ?></dc:creator><?php }?>
                        <?php if (isset($images)) {
                           foreach ($images as $i) { $this->outImageMRSS($i); } 
                        } ?>
                </item>
<?php
        }

        function outHeaderMRSS() {
                global $wgServer, $wgStylePath;

                $this->outXmlHeader();
?>		
        <rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">
        <channel>
                <title><?php print $this->getTitle() ?></title>
                <copyright>This work is licensed under a Creative Commons Attribution-NonCommercial-ShareAlike 2.5 License, except where noted otherwise.</copyright>
                <link><?php print $this->getUrl() ?></link>
                <description><?php print $this->getDescription() ?></description>
                <language><?php print $this->getLanguage() ?></language>
                <lastBuildDate><?php print $this->formatTime( wfTimestampNow() ) ?></lastBuildDate>
                <ttl>20</ttl>
                <image>
                        <title>wikiHow</title>
                        <width>144</width>
                        <height>37</height>
                        <link><?php print $this->getUrl() ?></link>
                        <url><?php print $wgServer . $wgStylePath ?>/WikiHow/wikiHow.gif</url>
                </image>
<?php
        }

        function outImageMRSS($img) {
				global $wgServer;
?>
<media:content url="<?= wfGetPad( $img['src'] ) ?>" type="<?= $img['mime'] ?>" medium="image"  />
<?php
        }

        function outItemMRSS( $item, $images ) {
?>
                <item>
                        <title><?php print $item->getTitle() ?></title>
                        <link><?php print $item->getUrl() ?></link>
                        <guid isPermaLink="true"><?php print $item->getUrl() ?></guid>
                        <description><?php print $item->getDescription() ?></description>
                        <?php if( $item->getDate() ) { ?><pubDate><?php print $this->formatTime( $item->getDate() ) ?></pubDate><?php } ?>
                        <?php if( $item->getAuthor() ) { ?><dc:creator><?php print $item->getAuthor() ?></dc:creator><?php }?>
                        <?php foreach ($images as $i) { $this->outImageMRSS($i); } ?>
                </item>
<?php
        }
}

?>
