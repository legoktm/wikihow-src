<?php

if ( !defined('MEDIAWIKI') ) die();

/**#@+
 *
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:RateArticle-Extension Documentation
 *
 *
 * @author Bebeth Steudel
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgShowRatings = false; // set this to false if you want your ratings hidden

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'RateItem',
    'author' => 'Bebeth <bebeth@wikihow.com>',
    'description' => 'Provides a basic ratings system for article, samples, etc',
);

$wgExtensionMessagesFiles['RateItem'] = dirname(__FILE__) . '/Rating.i18n.php';

$wgSpecialPages['RateItem'] = 'RateItem';
$wgAutoloadClasses['RateItem'] = dirname( __FILE__ ) . '/Rating.body.php';

$wgSpecialPages['ListRatings'] = 'ListRatings';
$wgAutoloadClasses['ListRatings'] = dirname( __FILE__ ) . '/Rating.body.php';

$wgSpecialPages['Clearratings'] = 'Clearratings';
$wgAutoloadClasses['Clearratings'] = dirname( __FILE__ ) . '/Rating.body.php';

$wgSpecialPages['AccuracyPatrol'] = 'AccuracyPatrol';
$wgAutoloadClasses['AccuracyPatrol'] = dirname( __FILE__ ) . '/Rating.body.php';

$wgSpecialPages['RatingReason'] = 'RatingReason';
$wgAutoloadClasses['RatingReason'] = dirname( __FILE__ ) . '/Rating.body.php';


$wgLogTypes[] = 'accuracy';
$wgLogNames['accuracy'] = 'accuracylogpage';
$wgLogHeaders['accuracy'] = 'accuracylogtext';
$wgLogTypes[] = 'acc_sample';
$wgLogNames['acc_sample'] = 'accsamplelogpage';
$wgLogHeaders['acc_sample'] = 'accsamplelogtext';

