<?php
/**
 * Groups configuration for default Minify implementation
 * @package Minify
 */

/** 
 * You may wish to use the Minify URI Builder app to suggest
 * changes. http://yourdomain/min/builder/
 **/

return array(

	//XXCHANGED: reuben/wikihow added groups to reduce URL length

	// big web JS
	'whjs' => array(
		#'//extensions/wikihow/common/jquery-1.7.1.min.js',
		#'//skins/common/highlighter-0.6.js',
		'//skins/common/wikihowbits.js',
		'//skins/common/swfobject.js',
		'//extensions/wikihow/common/jquery.scrollTo/jquery.scrollTo.js',
		'//skins/common/fb.js',
		'//extensions/wikihow/GPlusLogin/gplus.js',
		'//skins/WikiHow/google_cse_search_box.js',
		#'//skins/common/mixpanel.js',
		'//skins/WikiHow/gaWHTracker.js',
		'//extensions/wikihow/common/CCSFG/popup.js',
		'//extensions/wikihow/loginreminder/LoginReminder.js',
		'//skins/common/jquery.menu-aim.js',
	),

	// AG - updated the version of jquery-ui-custom
	'jqui' => array('//extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js'),
	'wkt' => array('//extensions/wikihow/common/download.jQuery.js'),
	'rcw' => array('//extensions/wikihow/rcwidget/rcwidget.js'),
	'sp' => array('//skins/WikiHow/spotlightrotate.js'),
	'fl' => array('//extensions/wikihow/FollowWidget.js'),
	'slj' => array('//extensions/wikihow/slider/slider.js'),
	'ppj' => array('//extensions/wikihow/gallery/prettyPhoto-3.12/src/jquery.prettyPhoto.js'),
	'ads' => array('//extensions/wikihow/wikihowAds/wikihowAds.js'),
	'thm' => array('//extensions/wikihow/thumbsup/thumbsnotifications.js'),
	'stu' => array('//skins/common/stu.js'),
	'altj' => array('//extensions/wikihow/altmethodadder/altmethodadder.js'),
	'methj' => array('//extensions/wikihow/altmethodadder/methodeditor.js'),
	'meguj' => array('//extensions/wikihow/altmethodadder/methodguardian.js'),
	'tpt' => array('//extensions/wikihow/tipsandwarnings/toptentips.js'),
	'hp' => array('//extensions/wikihow/homepage/wikihowhomepage.js'),
	'ts' => array('//extensions/wikihow/textscroller/textscroller.js'),
	'ii' => array('//extensions/wikihow/imagefeedback/imagefeedback.js'),
	'ctt' => array('//extensions/wikihow/cattool/categorizer.js'),
	'whv' => array(
		'//extensions/wikihow/whvid/whvid.js',
		'//extensions/wikihow/common/flowplayer/flowplayer.min.js',
	),
	'catj' => array('//extensions/wikihow/categories-owl.js'),
	'qcj' => array('//extensions/wikihow/qc/qc.js'),
	'rcpj' => array(
		'//extensions/wikihow/common/mousetrap.min.js',
		'//extensions/wikihow/rcpatrol/rcpatrol.js',
	),
	'vaddj' => array('//extensions/wikihow/video/videoadder.js'),
	'vcooj' => array('//extensions/wikihow/video/cookie.js'),
	'suggj' => array('//extensions/wikihow/suggestedtopics.js'),
	'winpj' => array('//extensions/wikihow/winpop.js'),
	'csjs' => array('//skins/common/clientscript.js'),
	'nfdgj' => array('//extensions/wikihow/nfd/nfdGuardian.js'),
	'advj' => array('//extensions/wikihow/advancededitor.js'),
	'eiuj' => array('//extensions/wikihow/eiu/easyimageupload.js'),
	'jcookj' => array('//extensions/wikihow/common/jquery.cookie.js'),
	'pbj' => array('//extensions/wikihow/profilebox/profilebox.js'),
	'airj' => array('//extensions/wikihow/adminimageremoval.js'),
	'aej' => array('//extensions/wikihow/wikihowAds/adminadexclusions.js'),

	// big web CSS
	'whcss' => array(
		'//skins/owl/main.css',
		'//extensions/wikihow/tipsandwarnings/topten.css',
	),

	// article greenhouses
	'ag' => array(
		'//extensions/wikihow/common/mousetrap.min.js',
		'//extensions/wikihow/common/jquery.cookie.js',
		'//skins/common/clientscript.js',
		'//skins/common/preview.js',
		'//extensions/wikihow/editfinder/editfinder.js',
	),
	'mt' => array('//extensions/wikihow/common/mousetrap.min.js'),
	'jqck' => array('//extensions/wikihow/common/jquery.cookie.js'),


	'jquic' => array('//extensions/wikihow/common/jquery-ui-themes/jquery-ui.css'),
    'nona' => array('//skins/owl/nonarticle.css'),
	'liq' => array('//skins/owl/liquid.css'),
	'fix' => array('//skins/owl/fixed.css'),
	'hpc' => array('//skins/owl/home.css'),
	'li' => array(
		'//skins/WikiHow/loggedin.css',
		'//skins/owl/loggedin.css',
	),
	'slc' => array('//extensions/wikihow/slider/slider.css'),
	'ppc' => array('//extensions/wikihow/gallery/prettyPhoto-3.12/src/prettyPhoto.css'),
	'altc' => array('//extensions/wikihow/altmethodadder/altmethodadder.css'),
	'methc' => array('//extensions/wikihow/altmethodadder/methodeditor.css'),
	'meguc' => array('//extensions/wikihow/altmethodadder/methodguardian.css'),
	'tsc' => array('//extensions/wikihow/textscroller/textscroller.css'),
	'tptc' => array('//extensions/wikihow/tipsandwarnings/topten.css'),
	'iic' => array('//extensions/wikihow/imagefeedback/imagefeedback.css'),
	'tpc' => array('//extensions/wikihow/tipsandwarnings/tipspatrol.css'),
	'dvc' => array('//extensions/wikihow/docviewer/docviewer.css'),
	'spc' => array('//skins/owl/special.css'),
	'whvc' => array(
		'//extensions/wikihow/whvid/whvid.css',
		'//extensions/wikihow/common/flowplayer/skin/minimalist.css',
	),
	'catc' => array('//extensions/wikihow/categories-owl.css'),
	'qcc' => array('//extensions/wikihow/qc/qc.css'),
	'rcpc' => array('//extensions/wikihow/rcpatrol/rcpatrol.css'),
	'diffc' => array('//skins/common/diff.css'),
	'vaddc' => array('//extensions/wikihow/video/videoadder.css'),
	'suggc' => array('//extensions/wikihow/suggestedtopics.css'),
	'winpc' => array('//extensions/wikihow/winpop.css'),
	'src' => array('//skins/owl/searchresults.css'),
	'cttc' => array('//extensions/wikihow/cattool/categorizer.css'),
	'nfdgc' => array('//extensions/wikihow/nfd/nfdGuardian.css'),
	'pbc' => array('//extensions/wikihow/profilebox/profilebox.css'),
	'rcwc' => array('//extensions/wikihow/rcwidget/rcwidget.css'),
	'tbc' => array('//extensions/wikihow/interfaceelements/tipsbubble.css'),
	'pcc' => array('//extensions/wikihow/Patrolcount.css'),

	// Stubs / Hillary tool
	'stb' => array(
		'//extensions/wikihow/common/canv-gauge/gauge.js',
		'//extensions/wikihow/stubs/hillary.js'
	),
	'stbc' => array('//extensions/wikihow/stubs/hillary.css'),

	// mobile JS
	'mjq' => array('//extensions/wikihow/common/jquery-1.7.1.min.js'),
	'mwh' => array(
		'//extensions/wikihow/mobile/mobile.js',
	),
	'mga' => array('//skins/common/ga.js'),
	'mah' => array('//extensions/wikihow/mobile/add2home/add2home.js'),
	'mqg' => array('//extensions/wikihow/mqg/mqg.js'),
	'thr' => array('//extensions/wikihow/thumbratings/thumbratings.js'),
	'cm' => array('//extensions/wikihow/checkmarks/checkmarks.js'),
	'mscr' => array('//extensions/wikihow/common/jquery.scrollTo/jquery.scrollTo.js'),
	'mtip' => array('//extensions/wikihow/tipsandwarnings/tipsandwarnings.js',),
	'mtpt' => array('//extensions/wikihow/tipsandwarnings/toptentips.js'),
	'maim' => array('//extensions/wikihow/mobile/webtoolkit.aim.min.js'),
	'muci' => array('//extensions/wikihow/mobile/usercompletedimages.js'),

	// mobile CSS
	'mwhc' => array(
		'//extensions/wikihow/mobile/mobile.css',
	),
	'mwhf' => array('//extensions/wikihow/mobile/mobile-featured.css'),
	'mwhh' => array('//extensions/wikihow/mobile/mobile-home.css'),
	'mwhr' => array('//extensions/wikihow/mobile/mobile-results.css'),
	'mwha' => array('//extensions/wikihow/mobile/mobile-article.css'),
	'ma2h' => array('//extensions/wikihow/mobile/add2home/add2home.css'),
	'mqgc' => array('//extensions/wikihow/mqg/mqg.css'),
	'mcmc' => array('//extensions/wikihow/checkmarks/checkmarks.css'),
	'mthr' => array('//extensions/wikihow/thumbratings/thumbratings.css'),
	'msd' => array('//extensions/wikihow/docviewer/docviewer_m.css'),
	'mtptc' => array('//extensions/wikihow/tipsandwarnings/topten_m.css'),

    // 'js' => array('//js/file1.js', '//js/file2.js'),
    // 'css' => array('//css/file1.css', '//css/file2.css'),

    // custom source example
    /*'js2' => array(
        dirname(__FILE__) . '/../min_unit_tests/_test_files/js/before.js',
        // do NOT process this file
        new Minify_Source(array(
            'filepath' => dirname(__FILE__) . '/../min_unit_tests/_test_files/js/before.js',
            'minifier' => create_function('$a', 'return $a;')
        ))
    ),//*/

    /*'js3' => array(
        dirname(__FILE__) . '/../min_unit_tests/_test_files/js/before.js',
        // do NOT process this file
        new Minify_Source(array(
            'filepath' => dirname(__FILE__) . '/../min_unit_tests/_test_files/js/before.js',
            'minifier' => array('Minify_Packer', 'minify')
        ))
    ),//*/
);
