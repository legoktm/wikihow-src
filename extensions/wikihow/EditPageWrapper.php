<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgAutoloadClasses['EditPageWrapper'] = dirname(__FILE__) . '/EditPageWrapper.class.php';

$wgHooks['CustomEditor'][] = array('EditPageWrapper::onCustomEdit');
$wgHooks['MediaWikiPerformAction'][] = 'EditPageWrapper::onMediaWikiPerformAction';
$wgHooks['EditPage::showStandardInputs:options'][] = array('EditPageWrapper::addHiddenFormInputs');
