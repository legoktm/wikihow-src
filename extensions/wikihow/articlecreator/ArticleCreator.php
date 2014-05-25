<?
if ( !defined( 'MEDIAWIKI' ) ) 
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Article Creator',
	'author' => 'Jordan Small',
	'description' => 'A friendly, non-wikitext way of creating articles',
);

$wgSpecialPages['ArticleCreator'] = 'ArticleCreator';
$wgAutoloadClasses['ArticleCreator'] = dirname(__FILE__) . '/ArticleCreator.body.php';
$wgExtensionMessagesFiles['ArticleCreator'] = dirname(__FILE__) . '/ArticleCreator.i18n.php';
$wgHooks['EditFormPreloadText'][] = 'ArticleCreator::onEditFormPreloadText';

$wgResourceModules['ext.wikihow.articlecreator'] = array(
		'scripts' => 'articlecreator.js',
		'localBasePath' => dirname(__FILE__) . '/',
		'remoteExtPath' => '/extensions/wikihow/articlecreator/',
		'messages' => array(
			'ac-section-intro-name',
			'ac-section-intro-desc',
			'ac-section-intro-button-txt',
			'ac-section-intro-placeholder',
			'ac-section-steps-name',
			'ac-section-steps-desc',
			'ac-section-steps-name-method-placeholder',
			'ac-section-steps-method-done-button-txt',
			'ac-section-steps-addstep-placeholder',
			'ac-section-steps-button-txt',
			'ac-section-tips-name',
			'ac-section-tips-desc',
			'ac-section-tips-button-txt',
			'ac-section-tips-placeholder',
			'ac-section-warnings-name',
			'ac-section-warnings-desc',
			'ac-section-warnings-button-txt',
			'ac-section-warnings-placeholder',
			'ac-section-sources-name',
			'ac-section-sources-desc',
			'ac-section-sources-button-txt',
			'ac-section-sources-placeholder',
			'ac-section-button-txt',
			'ac-edit-summary',
			'ac-invalid-edit-token'	=> 'Looks like you have an invalid edit token which means we can\'t go any further from here.',
			'ac-title-exists',
			'ac-html-title',
			'ac-successful-publish',
			'ac-copy-wikitext',	
			'ac-whats-this-txt',	
			'ac-validation-error-title',
			'ac-error-too-short',
			'ac-error-no-steps',
			'ac-error-missing-method-names',
			'ac-confirm-delete-step',
			'ac-confirm-delete-bullet',
			'ac-confirm-remove-method',
			'ac-confirm-discard-article',
			'ac-confirm-advanced-editor',
			'ac-question-neither',
			'ac-question-neither-title',
			'ac-question-methods',
			'ac-question-methods-title',
			'ac-question-parts',
			'ac-question-parts-title',
			'ac-save-unsaved-edits',
			'ac-formatting-warning-txt',
			'ac-formatting-warning-title',
			'ac-method-selector-txt',
			'ac-part-selector-txt',
			'ac-add-method-button-txt',
			'ac-add-part-button-txt',
			'ac-error-only-bullets'
		),
);