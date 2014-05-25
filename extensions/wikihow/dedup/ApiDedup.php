<?php

$wgExtensionsCredits['api'][] = array(
		'path' => __FILE__,
		'name' => 'Dedup API',
		'description' => 'An API for the dedupping tool',
		'descriptionmsg' => '',
		'version' => '1',
		'author' => 'Gershon Bialer'
		);

$wgAutoloadClasses['ApiDedup'] = dirname(__FILE__) . '/ApiDedup.body.php';

$wgAPIModules['dedup'] = 'ApiDedup';
