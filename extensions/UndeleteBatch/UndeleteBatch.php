<?php
/**
 * UndeleteBatch - a special page to undelete a batch of pages
 *
 * @file
 * @ingroup Extensions
 * @author Nathan Larson
 * @version 1.0.0
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link http://www.mediawiki.org/wiki/Extension:UndeleteBatch Documentation
 */
if ( !defined( 'MEDIAWIKI' ) )
	die();

// Extension credits that will show up on Special:version
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Undelete Batch',
	'version' => '1.0.0',
	'author' => 'Nathan Larson',
	'url' => 'https://www.mediawiki.org/wiki/Extension:UndeleteBatch',
	'descriptionmsg' => 'undeletebatch-desc',
);

// New user right, required to use Special:UndeleteBatch
$wgAvailableRights[] = 'undeletebatch';
$wgGroupPermissions['bureaucrat']['undeletebatch'] = true;

// Set up the new special page
$dir = __DIR__ . '/';
$wgExtensionMessagesFiles['UndeleteBatch'] = $dir . 'UndeleteBatch.i18n.php';
$wgExtensionMessagesFiles['UndeleteBatchAlias'] = $dir . 'UndeleteBatch.alias.php';
$wgAutoloadClasses['SpecialUndeleteBatch'] = $dir . 'UndeleteBatch.body.php';
$wgSpecialPages['UndeleteBatch'] = 'SpecialUndeleteBatch';
$wgSpecialPageGroups['UndeleteBatch'] = 'pagetools';

// Hooks
$wgHooks['AdminLinks'][] = 'SpecialUndeleteBatch::addToAdminLinks'; // Admin Links extension
