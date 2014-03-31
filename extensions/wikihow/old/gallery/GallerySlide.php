<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['GallerySlide'] = 'GallerySlide';
$wgAutoloadClasses['GallerySlide'] = dirname( __FILE__ ) . '/GallerySlide.body.php';
