<?php
if ( ! defined( 'MEDIAWIKI' ) )
    die();

/**#@+
 *  Provides a way of importing properly licensed photos from flickr
 * 
 * @addtogroup Extensions
 *
 * @link http://www.mediawiki.org/wiki/Extension:ImportFreeImages Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

//$wgIFI_FlickrAPIKey = '';
$wgIFI_FlickrAPIKey = WH_FLICKR_API_KEY;
$wgIFI_CreditsTemplate = 'flickr'; // use this to format the image content with some key parameters
$wgIFI_GetOriginal = false; // import the original version of the photo
$wgIFI_PromptForFilename = false;  // prompt the user through javascript for the destination filename
$wgIFI_CheckForExistingFile = true;  // prompt the user through javascript for the destination filename

$wgIFI_ResultsPerPage = 20;
$wgIFI_ResultsPerRow = 4;
// see the flickr api page for more information on these params
// for licnese info http://www.flickr.com/services/api/flickr.photos.licenses.getInfo.html
// default 4 is CC Attribution License
$wgIFI_FlickrLicense = "4,5";
$wgIFI_FlickrSort = "relevance"; //"interestingness-desc";
$wgIFI_FlickrSearchBy = "text"; // Can be tags or text. See http://www.flickr.com/services/api/flickr.photos.search.html
$wgIFI_AppendRandomNumber = true; /// append random # to destination filename
$wgIFI_ThumbType = "s"; // s for square t for thumbnail


$wgIFI_UseAjax = true;
$wgIFI_AjaxKey = WH_GOOGLE_AJAX_IMAGE_SEARCH_API_KEY;
$wgIFI_AjaxDomain	= "wikimedia.org";
$wgIFI_AjaxTemplate = "commons";
$wgIFI_ValidDomains = array ("flickr.com"=>1 );
$wgIFI_ValidLicenses = array ("cc-by-sa-all", "PD", "GFDL", "cc-by-sa-3.0", "cc-by-sa-2.5", "FAL", "cc-by-3.0", "cc-by-2.5",
		"GDL-en", "cc-by-sa-2.0", "cc-by-2.0", "attribution"); 

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'ImportFreeImages',
    'author' => 'Travis Derouin',
    'description' => 'Provides a way of importing properly licensed photos from flickr.',
    'url' => 'http://www.mediawiki.org/wiki/Extension:ImportFreeImages',
);

$wgExtensionMessagesFiles['ImportFreeImages'] = dirname(__FILE__) . '/ImportFreeImages.i18n.php';
$wgExtensionMessagesFiles['ImportFreeImagesAliases'] = dirname(__FILE__) . '/ImportFreeImages.alias.php';

$wgSpecialPages['ImportFreeImages'] = 'ImportFreeImages';
$wgAutoloadClasses['ImportFreeImages'] = dirname( __FILE__ ) . '/ImportFreeImages.body.php';

