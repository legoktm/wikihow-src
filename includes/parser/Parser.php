<?php
/**
 * PHP parser that converts wiki markup to HTML.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Parser
 */

/**
 * @defgroup Parser Parser
 */

/**
 * PHP Parser - Processes wiki markup (which uses a more user-friendly
 * syntax, such as "[[link]]" for making links), and provides a one-way
 * transformation of that wiki markup it into (X)HTML output / markup
 * (which in turn the browser understands, and can display).
 *
 * There are seven main entry points into the Parser class:
 *
 * - Parser::parse()
 *     produces HTML output
 * - Parser::preSaveTransform().
 *     produces altered wiki markup.
 * - Parser::preprocess()
 *     removes HTML comments and expands templates
 * - Parser::cleanSig() and Parser::cleanSigInSig()
 *     Cleans a signature before saving it to preferences
 * - Parser::getSection()
 *     Return the content of a section from an article for section editing
 * - Parser::replaceSection()
 *     Replaces a section by number inside an article
 * - Parser::getPreloadText()
 *     Removes <noinclude> sections, and <includeonly> tags.
 *
 * Globals used:
 *    object: $wgContLang
 *
 * @warning $wgUser or $wgTitle or $wgRequest or $wgLang. Keep them away!
 *
 * @par Settings:
 * $wgNamespacesWithSubpages
 *
 * @par Settings only within ParserOptions:
 * $wgAllowExternalImages
 * $wgAllowSpecialInclusion
 * $wgInterwikiMagic
 * $wgMaxArticleSize
 *
 * @ingroup Parser
 */
class Parser {
	/**
	 * Update this version number when the ParserOutput format
	 * changes in an incompatible way, so the parser cache
	 * can automatically discard old data.
	 */
	const VERSION = '1.6.4';

	/**
	 * Update this version number when the output of serialiseHalfParsedText()
	 * changes in an incompatible way
	 */
	const HALF_PARSED_VERSION = 2;

	# Flags for Parser::setFunctionHook
	# Also available as global constants from Defines.php
	const SFH_NO_HASH = 1;
	const SFH_OBJECT_ARGS = 2;

	# Constants needed for external link processing
	# Everything except bracket, space, or control characters
	# \p{Zs} is unicode 'separator, space' category. It covers the space 0x20
	# as well as U+3000 is IDEOGRAPHIC SPACE for bug 19052
	const EXT_LINK_URL_CLASS = '[^][<>"\\x00-\\x20\\x7F\p{Zs}]';
	const EXT_IMAGE_REGEX = '/^(http:\/\/|https:\/\/)([^][<>"\\x00-\\x20\\x7F\p{Zs}]+)
		\\/([A-Za-z0-9_.,~%\\-+&;#*?!=()@\\x80-\\xFF]+)\\.((?i)gif|png|jpg|jpeg)$/Sxu';

	# State constants for the definition list colon extraction
	const COLON_STATE_TEXT = 0;
	const COLON_STATE_TAG = 1;
	const COLON_STATE_TAGSTART = 2;
	const COLON_STATE_CLOSETAG = 3;
	const COLON_STATE_TAGSLASH = 4;
	const COLON_STATE_COMMENT = 5;
	const COLON_STATE_COMMENTDASH = 6;
	const COLON_STATE_COMMENTDASHDASH = 7;

	# Flags for preprocessToDom
	const PTD_FOR_INCLUSION = 1;

	# Allowed values for $this->mOutputType
	# Parameter to startExternalParse().
	const OT_HTML = 1; # like parse()
	const OT_WIKI = 2; # like preSaveTransform()
	const OT_PREPROCESS = 3; # like preprocess()
	const OT_MSG = 3;
	const OT_PLAIN = 4; # like extractSections() - portions of the original are returned unchanged.

	# Marker Suffix needs to be accessible staticly.
	const MARKER_SUFFIX = "-QINU\x7f";

	# Markers used for wrapping the table of contents
	const TOC_START = '<mw:toc>';
	const TOC_END = '</mw:toc>';

	# Persistent:
	var $mTagHooks = array();
	var $mTransparentTagHooks = array();
	var $mFunctionHooks = array();
	var $mFunctionSynonyms = array( 0 => array(), 1 => array() );
	var $mFunctionTagHooks = array();
	var $mStripList = array();
	var $mDefaultStripList = array();
	var $mVarCache = array();
	var $mImageParams = array();
	var $mImageParamsMagicArray = array();
	var $mMarkerIndex = 0;
	var $mFirstCall = true;

	# Initialised by initialiseVariables()

	/**
	 * @var MagicWordArray
	 */
	var $mVariables;

	/**
	 * @var MagicWordArray
	 */
	var $mSubstWords;
	var $mConf, $mPreprocessor, $mExtLinkBracketedRegex, $mUrlProtocols; # Initialised in constructor

	# Cleared with clearState():
	/**
	 * @var ParserOutput
	 */
	var $mOutput;
	var $mAutonumber, $mDTopen;

	/**
	 * @var StripState
	 */
	var $mStripState;

	var $mIncludeCount, $mArgStack, $mLastSection, $mInPre;
	/**
	 * @var LinkHolderArray
	 */
	var $mLinkHolders;

	var $mLinkID;
	var $mIncludeSizes, $mPPNodeCount, $mGeneratedPPNodeCount, $mHighestExpansionDepth;
	var $mDefaultSort;
	var $mTplExpandCache; # empty-frame expansion cache
	var $mTplRedirCache, $mTplDomCache, $mHeadings, $mDoubleUnderscores;
	var $mExpensiveFunctionCount; # number of expensive parser function calls
	var $mShowToc, $mForceTocPosition;

	/**
	 * @var User
	 */
	var $mUser; # User object; only used when doing pre-save transform

	# Temporary
	# These are variables reset at least once per parse regardless of $clearState

	/**
	 * @var ParserOptions
	 */
	var $mOptions;

	/**
	 * @var Title
	 */
	var $mTitle;        # Title context, used for self-link rendering and similar things
	var $mOutputType;   # Output type, one of the OT_xxx constants
	var $ot;            # Shortcut alias, see setOutputType()
	var $mRevisionObject; # The revision object of the specified revision ID
	var $mRevisionId;   # ID to display in {{REVISIONID}} tags
	var $mRevisionTimestamp; # The timestamp of the specified revision ID
	var $mRevisionUser; # User to display in {{REVISIONUSER}} tag
	var $mRevisionSize; # Size to display in {{REVISIONSIZE}} variable
	var $mRevIdForTs;   # The revision ID which was used to fetch the timestamp
	var $mInputSize = false; # For {{PAGESIZE}} on current page.

	/**
	 * @var string
	 */
	var $mUniqPrefix;

	/**
	 * @var Array with the language name of each language link (i.e. the
	 * interwiki prefix) in the key, value arbitrary. Used to avoid sending
	 * duplicate language links to the ParserOutput.
	 */
	var $mLangLinkLanguages;

	/**
	 * Constructor
	 *
	 * @param $conf array
	 */
	public function __construct( $conf = array() ) {
		$this->mConf = $conf;
		$this->mUrlProtocols = wfUrlProtocols();
		$this->mExtLinkBracketedRegex = '/\[(((?i)' . $this->mUrlProtocols . ')' .
			self::EXT_LINK_URL_CLASS . '+)\p{Zs}*([^\]\\x00-\\x08\\x0a-\\x1F]*?)\]/Su';
		if ( isset( $conf['preprocessorClass'] ) ) {
			$this->mPreprocessorClass = $conf['preprocessorClass'];
		} elseif ( defined( 'HPHP_VERSION' ) ) {
			# Preprocessor_Hash is much faster than Preprocessor_DOM under HipHop
			$this->mPreprocessorClass = 'Preprocessor_Hash';
		} elseif ( extension_loaded( 'domxml' ) ) {
			# PECL extension that conflicts with the core DOM extension (bug 13770)
			wfDebug( "Warning: you have the obsolete domxml extension for PHP. Please remove it!\n" );
			$this->mPreprocessorClass = 'Preprocessor_Hash';
		} elseif ( extension_loaded( 'dom' ) ) {
			$this->mPreprocessorClass = 'Preprocessor_DOM';
		} else {
			$this->mPreprocessorClass = 'Preprocessor_Hash';
		}
		wfDebug( __CLASS__ . ": using preprocessor: {$this->mPreprocessorClass}\n" );
	}

	/**
	 * Reduce memory usage to reduce the impact of circular references
	 */
	function __destruct() {
		if ( isset( $this->mLinkHolders ) ) {
			unset( $this->mLinkHolders );
		}
		foreach ( $this as $name => $value ) {
			unset( $this->$name );
		}
	}

	/**
	 * Allow extensions to clean up when the parser is cloned
	 */
	function __clone() {
		wfRunHooks( 'ParserCloned', array( $this ) );
	}

	/**
	 * Do various kinds of initialisation on the first call of the parser
	 */
	function firstCallInit() {
		if ( !$this->mFirstCall ) {
			return;
		}
		$this->mFirstCall = false;

		wfProfileIn( __METHOD__ );

		CoreParserFunctions::register( $this );
		CoreTagHooks::register( $this );
		$this->initialiseVariables();

		wfRunHooks( 'ParserFirstCallInit', array( &$this ) );
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Clear Parser state
	 *
	 * @private
	 */
	function clearState() {
		wfProfileIn( __METHOD__ );
		if ( $this->mFirstCall ) {
			$this->firstCallInit();
		}
		$this->mOutput = new ParserOutput;
		$this->mOptions->registerWatcher( array( $this->mOutput, 'recordOption' ) );
		$this->mAutonumber = 0;
		$this->mLastSection = '';
		$this->mDTopen = false;
		$this->mIncludeCount = array();
		$this->mArgStack = false;
		$this->mInPre = false;
		$this->mLinkHolders = new LinkHolderArray( $this );
		$this->mLinkID = 0;
		$this->mRevisionObject = $this->mRevisionTimestamp =
			$this->mRevisionId = $this->mRevisionUser = $this->mRevisionSize = null;
		$this->mVarCache = array();
		$this->mUser = null;
		$this->mLangLinkLanguages = array();

		/**
		 * Prefix for temporary replacement strings for the multipass parser.
		 * \x07 should never appear in input as it's disallowed in XML.
		 * Using it at the front also gives us a little extra robustness
		 * since it shouldn't match when butted up against identifier-like
		 * string constructs.
		 *
		 * Must not consist of all title characters, or else it will change
		 * the behavior of <nowiki> in a link.
		 */
		$this->mUniqPrefix = "\x7fUNIQ" . self::getRandomString();
		$this->mStripState = new StripState( $this->mUniqPrefix );

		# Clear these on every parse, bug 4549
		$this->mTplExpandCache = $this->mTplRedirCache = $this->mTplDomCache = array();

		$this->mShowToc = true;
		$this->mForceTocPosition = false;
		$this->mIncludeSizes = array(
			'post-expand' => 0,
			'arg' => 0,
		);
		$this->mPPNodeCount = 0;
		$this->mGeneratedPPNodeCount = 0;
		$this->mHighestExpansionDepth = 0;
		$this->mDefaultSort = false;
		$this->mHeadings = array();
		$this->mDoubleUnderscores = array();
		$this->mExpensiveFunctionCount = 0;

		# Fix cloning
		if ( isset( $this->mPreprocessor ) && $this->mPreprocessor->parser !== $this ) {
			$this->mPreprocessor = null;
		}

		wfRunHooks( 'ParserClearState', array( &$this ) );
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Convert wikitext to HTML
	 * Do not call this function recursively.
	 *
	 * @param string $text text we want to parse
	 * @param $title Title object
	 * @param $options ParserOptions
	 * @param $linestart boolean
	 * @param $clearState boolean
	 * @param int $revid number to pass in {{REVISIONID}}
	 * @return ParserOutput a ParserOutput
	 */
	public function parse( $text, Title $title, ParserOptions $options, $linestart = true, $clearState = true, $revid = null ) {
		/**
		 * First pass--just handle <nowiki> sections, pass the rest off
		 * to internalParse() which does all the real work.
		 */

		global $wgUseTidy, $wgAlwaysUseTidy, $wgShowHostnames;
		$fname = __METHOD__ . '-' . wfGetCaller();
		wfProfileIn( __METHOD__ );
		wfProfileIn( $fname );

		$this->startParse( $title, $options, self::OT_HTML, $clearState );

		// AG, for upgrade 1.21: added to get image sections set up
		global $wgParser;
		$oldParser = $wgParser;
		$wgParser = new Parser();
		WikihowArticleEditor::setImageSections($text);
		$wgParser = $oldParser;

		$this->mInputSize = strlen( $text );
		if ( $this->mOptions->getEnableLimitReport() ) {
			$this->mOutput->resetParseStartTime();
		}

		# Remove the strip marker tag prefix from the input, if present.
		if ( $clearState ) {
			$text = str_replace( $this->mUniqPrefix, '', $text );
		}

		$oldRevisionId = $this->mRevisionId;
		$oldRevisionObject = $this->mRevisionObject;
		$oldRevisionTimestamp = $this->mRevisionTimestamp;
		$oldRevisionUser = $this->mRevisionUser;
		$oldRevisionSize = $this->mRevisionSize;
		if ( $revid !== null ) {
			$this->mRevisionId = $revid;
			$this->mRevisionObject = null;
			$this->mRevisionTimestamp = null;
			$this->mRevisionUser = null;
			$this->mRevisionSize = null;
		}

		wfRunHooks( 'ParserBeforeStrip', array( &$this, &$text, &$this->mStripState ) );
		# No more strip!
		wfRunHooks( 'ParserAfterStrip', array( &$this, &$text, &$this->mStripState ) );
		$text = $this->internalParse( $text );
		wfRunHooks( 'ParserAfterParse', array( &$this, &$text, &$this->mStripState ) );

		$text = $this->mStripState->unstripGeneral( $text );

		# Clean up special characters, only run once, next-to-last before doBlockLevels
		$fixtags = array(
			# french spaces, last one Guillemet-left
			# only if there is something before the space
			'/(.) (?=\\?|:|;|!|%|\\302\\273)/' => '\\1&#160;',
			# french spaces, Guillemet-right
			'/(\\302\\253) /' => '\\1&#160;',
			'/&#160;(!\s*important)/' => ' \\1', # Beware of CSS magic word !important, bug #11874.
		);
		$text = preg_replace( array_keys( $fixtags ), array_values( $fixtags ), $text );

		$text = $this->doBlockLevels( $text, $linestart );

		$this->replaceLinkHolders( $text );

		/**
		 * The input doesn't get language converted if
		 * a) It's disabled
		 * b) Content isn't converted
		 * c) It's a conversion table
		 * d) it is an interface message (which is in the user language)
		 */
		if ( !( $options->getDisableContentConversion()
			|| isset( $this->mDoubleUnderscores['nocontentconvert'] ) )
		) {
			if ( !$this->mOptions->getInterfaceMessage() ) {
				# The position of the convert() call should not be changed. it
				# assumes that the links are all replaced and the only thing left
				# is the <nowiki> mark.
				$text = $this->getConverterLanguage()->convert( $text );
			}
		}

		/**
		 * A converted title will be provided in the output object if title and
		 * content conversion are enabled, the article text does not contain
		 * a conversion-suppressing double-underscore tag, and no
		 * {{DISPLAYTITLE:...}} is present. DISPLAYTITLE takes precedence over
		 * automatic link conversion.
		 */
		if ( !( $options->getDisableTitleConversion()
			|| isset( $this->mDoubleUnderscores['nocontentconvert'] )
			|| isset( $this->mDoubleUnderscores['notitleconvert'] )
			|| $this->mOutput->getDisplayTitle() !== false )
		) {
			$convruletitle = $this->getConverterLanguage()->getConvRuleTitle();
			if ( $convruletitle ) {
				$this->mOutput->setTitleText( $convruletitle );
			} else {
				$titleText = $this->getConverterLanguage()->convertTitle( $title );
				$this->mOutput->setTitleText( $titleText );
			}
		}

		$text = $this->mStripState->unstripNoWiki( $text );

		wfRunHooks( 'ParserBeforeTidy', array( &$this, &$text ) );

		$text = $this->replaceTransparentTags( $text );
		$text = $this->mStripState->unstripGeneral( $text );

		$text = Sanitizer::normalizeCharReferences( $text );

		if ( ( $wgUseTidy && $this->mOptions->getTidy() ) || $wgAlwaysUseTidy ) {
			$text = MWTidy::tidy( $text );
		} else {
			# attempt to sanitize at least some nesting problems
			# (bug #2702 and quite a few others)
			$tidyregs = array(
				# ''Something [http://www.cool.com cool''] -->
				# <i>Something</i><a href="http://www.cool.com"..><i>cool></i></a>
				'/(<([bi])>)(<([bi])>)?([^<]*)(<\/?a[^<]*>)([^<]*)(<\/\\4>)?(<\/\\2>)/' =>
				'\\1\\3\\5\\8\\9\\6\\1\\3\\7\\8\\9',
				# fix up an anchor inside another anchor, only
				# at least for a single single nested link (bug 3695)
				'/(<a[^>]+>)([^<]*)(<a[^>]+>[^<]*)<\/a>(.*)<\/a>/' =>
				'\\1\\2</a>\\3</a>\\1\\4</a>',
				# fix div inside inline elements- doBlockLevels won't wrap a line which
				# contains a div, so fix it up here; replace
				# div with escaped text
				'/(<([aib]) [^>]+>)([^<]*)(<div([^>]*)>)(.*)(<\/div>)([^<]*)(<\/\\2>)/' =>
				'\\1\\3&lt;div\\5&gt;\\6&lt;/div&gt;\\8\\9',
				# remove empty italic or bold tag pairs, some
				# introduced by rules above
				'/<([bi])><\/\\1>/' => '',
			);

			$text = preg_replace(
				array_keys( $tidyregs ),
				array_values( $tidyregs ),
				$text );
		}

		if ( $this->mExpensiveFunctionCount > $this->mOptions->getExpensiveParserFunctionLimit() ) {
			$this->limitationWarn( 'expensive-parserfunction',
				$this->mExpensiveFunctionCount,
				$this->mOptions->getExpensiveParserFunctionLimit()
			);
		}

		wfRunHooks( 'ParserAfterTidy', array( &$this, &$text ) );

		# Information on include size limits, for the benefit of users who try to skirt them
		if ( $this->mOptions->getEnableLimitReport() ) {
			$max = $this->mOptions->getMaxIncludeSize();

			$cpuTime = $this->mOutput->getTimeSinceStart( 'cpu' );
			if ( $cpuTime !== null ) {
				$this->mOutput->setLimitReportData( 'limitreport-cputime',
					sprintf( "%.3f", $cpuTime )
				);
			}

			$wallTime = $this->mOutput->getTimeSinceStart( 'wall' );
			$this->mOutput->setLimitReportData( 'limitreport-walltime',
				sprintf( "%.3f", $wallTime )
			);

			$this->mOutput->setLimitReportData( 'limitreport-ppvisitednodes',
				array( $this->mPPNodeCount, $this->mOptions->getMaxPPNodeCount() )
			);
			$this->mOutput->setLimitReportData( 'limitreport-ppgeneratednodes',
				array( $this->mGeneratedPPNodeCount, $this->mOptions->getMaxGeneratedPPNodeCount() )
			);
			$this->mOutput->setLimitReportData( 'limitreport-postexpandincludesize',
				array( $this->mIncludeSizes['post-expand'], $max )
			);
			$this->mOutput->setLimitReportData( 'limitreport-templateargumentsize',
				array( $this->mIncludeSizes['arg'], $max )
			);
			$this->mOutput->setLimitReportData( 'limitreport-expansiondepth',
				array( $this->mHighestExpansionDepth, $this->mOptions->getMaxPPExpandDepth() )
			);
			$this->mOutput->setLimitReportData( 'limitreport-expensivefunctioncount',
				array( $this->mExpensiveFunctionCount, $this->mOptions->getExpensiveParserFunctionLimit() )
			);
			wfRunHooks( 'ParserLimitReportPrepare', array( $this, $this->mOutput ) );

			$limitReport = "NewPP limit report\n";
			if ( $wgShowHostnames ) {
				$limitReport .= 'Parsed by ' . wfHostname() . "\n";
			}
			foreach ( $this->mOutput->getLimitReportData() as $key => $value ) {
				if ( wfRunHooks( 'ParserLimitReportFormat',
					array( $key, &$value, &$limitReport, false, false )
				) ) {
					$keyMsg = wfMessage( $key )->inLanguage( 'en' )->useDatabase( false );
					$valueMsg = wfMessage( array( "$key-value-text", "$key-value" ) )
						->inLanguage( 'en' )->useDatabase( false );
					if ( !$valueMsg->exists() ) {
						$valueMsg = new RawMessage( '$1' );
					}
					if ( !$keyMsg->isDisabled() && !$valueMsg->isDisabled() ) {
						$valueMsg->params( $value );
						$limitReport .= "{$keyMsg->text()}: {$valueMsg->text()}\n";
					}
				}
			}
			// Since we're not really outputting HTML, decode the entities and
			// then re-encode the things that need hiding inside HTML comments.
			$limitReport = htmlspecialchars_decode( $limitReport );
			wfRunHooks( 'ParserLimitReport', array( $this, &$limitReport ) );

			// Sanitize for comment. Note '‐' in the replacement is U+2010,
			// which looks much like the problematic '-'.
			$limitReport = str_replace( array( '-', '&' ), array( '‐', '&amp;' ), $limitReport );
			$text .= "\n<!-- \n$limitReport-->\n";

			if ( $this->mGeneratedPPNodeCount > $this->mOptions->getMaxGeneratedPPNodeCount() / 10 ) {
				wfDebugLog( 'generated-pp-node-count', $this->mGeneratedPPNodeCount . ' ' .
					$this->mTitle->getPrefixedDBkey() );
			}
		}
		$this->mOutput->setText( $text );

		$this->mRevisionId = $oldRevisionId;
		$this->mRevisionObject = $oldRevisionObject;
		$this->mRevisionTimestamp = $oldRevisionTimestamp;
		$this->mRevisionUser = $oldRevisionUser;
		$this->mRevisionSize = $oldRevisionSize;
		$this->mInputSize = false;
		wfProfileOut( $fname );
		wfProfileOut( __METHOD__ );

		return $this->mOutput;
	}

	/**
	 * Recursive parser entry point that can be called from an extension tag
	 * hook.
	 *
	 * If $frame is not provided, then template variables (e.g., {{{1}}}) within $text are not expanded
	 *
	 * @param string $text text extension wants to have parsed
	 * @param $frame PPFrame: The frame to use for expanding any template variables
	 *
	 * @return string
	 */
	function recursiveTagParse( $text, $frame = false ) {
		wfProfileIn( __METHOD__ );
		wfRunHooks( 'ParserBeforeStrip', array( &$this, &$text, &$this->mStripState ) );
		wfRunHooks( 'ParserAfterStrip', array( &$this, &$text, &$this->mStripState ) );
		$text = $this->internalParse( $text, false, $frame );
		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Expand templates and variables in the text, producing valid, static wikitext.
	 * Also removes comments.
	 * @return mixed|string
	 */
	function preprocess( $text, Title $title = null, ParserOptions $options, $revid = null ) {
		wfProfileIn( __METHOD__ );
		$this->startParse( $title, $options, self::OT_PREPROCESS, true );
		if ( $revid !== null ) {
			$this->mRevisionId = $revid;
		}
		wfRunHooks( 'ParserBeforeStrip', array( &$this, &$text, &$this->mStripState ) );
		wfRunHooks( 'ParserAfterStrip', array( &$this, &$text, &$this->mStripState ) );
		$text = $this->replaceVariables( $text );
		$text = $this->mStripState->unstripBoth( $text );
		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Recursive parser entry point that can be called from an extension tag
	 * hook.
	 *
	 * @param string $text text to be expanded
	 * @param $frame PPFrame: The frame to use for expanding any template variables
	 * @return String
	 * @since 1.19
	 */
	public function recursivePreprocess( $text, $frame = false ) {
		wfProfileIn( __METHOD__ );
		$text = $this->replaceVariables( $text, $frame );
		$text = $this->mStripState->unstripBoth( $text );
		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Process the wikitext for the "?preload=" feature. (bug 5210)
	 *
	 * "<noinclude>", "<includeonly>" etc. are parsed as for template
	 * transclusion, comments, templates, arguments, tags hooks and parser
	 * functions are untouched.
	 *
	 * @param $text String
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return String
	 */
	public function getPreloadText( $text, Title $title, ParserOptions $options ) {
		# Parser (re)initialisation
		$this->startParse( $title, $options, self::OT_PLAIN, true );

		$flags = PPFrame::NO_ARGS | PPFrame::NO_TEMPLATES;
		$dom = $this->preprocessToDom( $text, self::PTD_FOR_INCLUSION );
		$text = $this->getPreprocessor()->newFrame()->expand( $dom, $flags );
		$text = $this->mStripState->unstripBoth( $text );
		return $text;
	}

	/**
	 * Get a random string
	 *
	 * @return string
	 */
	public static function getRandomString() {
		return wfRandomString( 16 );
	}

	/**
	 * Set the current user.
	 * Should only be used when doing pre-save transform.
	 *
	 * @param $user Mixed: User object or null (to reset)
	 */
	function setUser( $user ) {
		$this->mUser = $user;
	}

	/**
	 * Accessor for mUniqPrefix.
	 *
	 * @return String
	 */
	public function uniqPrefix() {
		if ( !isset( $this->mUniqPrefix ) ) {
			# @todo FIXME: This is probably *horribly wrong*
			# LanguageConverter seems to want $wgParser's uniqPrefix, however
			# if this is called for a parser cache hit, the parser may not
			# have ever been initialized in the first place.
			# Not really sure what the heck is supposed to be going on here.
			return '';
			# throw new MWException( "Accessing uninitialized mUniqPrefix" );
		}
		return $this->mUniqPrefix;
	}

	/**
	 * Set the context title
	 *
	 * @param $t Title
	 */
	function setTitle( $t ) {
		if ( !$t || $t instanceof FakeTitle ) {
			$t = Title::newFromText( 'NO TITLE' );
		}

		if ( $t->hasFragment() ) {
			# Strip the fragment to avoid various odd effects
			$this->mTitle = clone $t;
			$this->mTitle->setFragment( '' );
		} else {
			$this->mTitle = $t;
		}
	}

	/**
	 * Accessor for the Title object
	 *
	 * @return Title object
	 */
	function getTitle() {
		return $this->mTitle;
	}

	/**
	 * Accessor/mutator for the Title object
	 *
	 * @param $x Title object or null to just get the current one
	 * @return Title object
	 */
	function Title( $x = null ) {
		return wfSetVar( $this->mTitle, $x );
	}

	/**
	 * Set the output type
	 *
	 * @param $ot Integer: new value
	 */
	function setOutputType( $ot ) {
		$this->mOutputType = $ot;
		# Shortcut alias
		$this->ot = array(
			'html' => $ot == self::OT_HTML,
			'wiki' => $ot == self::OT_WIKI,
			'pre' => $ot == self::OT_PREPROCESS,
			'plain' => $ot == self::OT_PLAIN,
		);
	}

	/**
	 * Accessor/mutator for the output type
	 *
	 * @param int|null $x New value or null to just get the current one
	 * @return Integer
	 */
	function OutputType( $x = null ) {
		return wfSetVar( $this->mOutputType, $x );
	}

	/**
	 * Get the ParserOutput object
	 *
	 * @return ParserOutput object
	 */
	function getOutput() {
		return $this->mOutput;
	}

	/**
	 * Get the ParserOptions object
	 *
	 * @return ParserOptions object
	 */
	function getOptions() {
		return $this->mOptions;
	}

	/**
	 * Accessor/mutator for the ParserOptions object
	 *
	 * @param $x ParserOptions New value or null to just get the current one
	 * @return ParserOptions Current ParserOptions object
	 */
	function Options( $x = null ) {
		return wfSetVar( $this->mOptions, $x );
	}

	/**
	 * @return int
	 */
	function nextLinkID() {
		return $this->mLinkID++;
	}

	/**
	 * @param $id int
	 */
	function setLinkID( $id ) {
		$this->mLinkID = $id;
	}

	/**
	 * Get a language object for use in parser functions such as {{FORMATNUM:}}
	 * @return Language
	 */
	function getFunctionLang() {
		return $this->getTargetLanguage();
	}

	/**
	 * Get the target language for the content being parsed. This is usually the
	 * language that the content is in.
	 *
	 * @since 1.19
	 *
	 * @throws MWException
	 * @return Language|null
	 */
	public function getTargetLanguage() {
		$target = $this->mOptions->getTargetLanguage();

		if ( $target !== null ) {
			return $target;
		} elseif ( $this->mOptions->getInterfaceMessage() ) {
			return $this->mOptions->getUserLangObj();
		} elseif ( is_null( $this->mTitle ) ) {
			throw new MWException( __METHOD__ . ': $this->mTitle is null' );
		}

		return $this->mTitle->getPageLanguage();
	}

	/**
	 * Get the language object for language conversion
	 */
	function getConverterLanguage() {
		return $this->getTargetLanguage();
	}

	/**
	 * Get a User object either from $this->mUser, if set, or from the
	 * ParserOptions object otherwise
	 *
	 * @return User object
	 */
	function getUser() {
		if ( !is_null( $this->mUser ) ) {
			return $this->mUser;
		}
		return $this->mOptions->getUser();
	}

	/**
	 * Get a preprocessor object
	 *
	 * @return Preprocessor instance
	 */
	function getPreprocessor() {
		if ( !isset( $this->mPreprocessor ) ) {
			$class = $this->mPreprocessorClass;
			$this->mPreprocessor = new $class( $this );
		}
		return $this->mPreprocessor;
	}

	/**
	 * Replaces all occurrences of HTML-style comments and the given tags
	 * in the text with a random marker and returns the next text. The output
	 * parameter $matches will be an associative array filled with data in
	 * the form:
	 *
	 * @code
	 *   'UNIQ-xxxxx' => array(
	 *     'element',
	 *     'tag content',
	 *     array( 'param' => 'x' ),
	 *     '<element param="x">tag content</element>' ) )
	 * @endcode
	 *
	 * @param array $elements list of element names. Comments are always extracted.
	 * @param string $text Source text string.
	 * @param array $matches Out parameter, Array: extracted tags
	 * @param $uniq_prefix string
	 * @return String: stripped text
	 */
	public static function extractTagsAndParams( $elements, $text, &$matches, $uniq_prefix = '' ) {
		static $n = 1;
		$stripped = '';
		$matches = array();

		$taglist = implode( '|', $elements );
		$start = "/<($taglist)(\\s+[^>]*?|\\s*?)(\/?" . ">)|<(!--)/i";

		while ( $text != '' ) {
			$p = preg_split( $start, $text, 2, PREG_SPLIT_DELIM_CAPTURE );
			$stripped .= $p[0];
			if ( count( $p ) < 5 ) {
				break;
			}
			if ( count( $p ) > 5 ) {
				# comment
				$element = $p[4];
				$attributes = '';
				$close = '';
				$inside = $p[5];
			} else {
				# tag
				$element = $p[1];
				$attributes = $p[2];
				$close = $p[3];
				$inside = $p[4];
			}

			$marker = "$uniq_prefix-$element-" . sprintf( '%08X', $n++ ) . self::MARKER_SUFFIX;
			$stripped .= $marker;

			if ( $close === '/>' ) {
				# Empty element tag, <tag />
				$content = null;
				$text = $inside;
				$tail = null;
			} else {
				if ( $element === '!--' ) {
					$end = '/(-->)/';
				} else {
					$end = "/(<\\/$element\\s*>)/i";
				}
				$q = preg_split( $end, $inside, 2, PREG_SPLIT_DELIM_CAPTURE );
				$content = $q[0];
				if ( count( $q ) < 3 ) {
					# No end tag -- let it run out to the end of the text.
					$tail = '';
					$text = '';
				} else {
					$tail = $q[1];
					$text = $q[2];
				}
			}

			$matches[$marker] = array( $element,
				$content,
				Sanitizer::decodeTagAttributes( $attributes ),
				"<$element$attributes$close$content$tail" );
		}
		return $stripped;
	}

	/**
	 * Get a list of strippable XML-like elements
	 *
	 * @return array
	 */
	function getStripList() {
		return $this->mStripList;
	}

	/**
	 * Add an item to the strip state
	 * Returns the unique tag which must be inserted into the stripped text
	 * The tag will be replaced with the original text in unstrip()
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	function insertStripItem( $text ) {
		$rnd = "{$this->mUniqPrefix}-item-{$this->mMarkerIndex}-" . self::MARKER_SUFFIX;
		$this->mMarkerIndex++;
		$this->mStripState->addGeneral( $rnd, $text );
		return $rnd;
	}

	/**
	 * parse the wiki syntax used to render tables
	 *
	 * @private
	 * @return string
	 */
	function doTableStuff( $text ) {
		wfProfileIn( __METHOD__ );

		$lines = StringUtils::explode( "\n", $text );
		$out = '';
		$td_history = array(); # Is currently a td tag open?
		$last_tag_history = array(); # Save history of last lag activated (td, th or caption)
		$tr_history = array(); # Is currently a tr tag open?
		$tr_attributes = array(); # history of tr attributes
		$has_opened_tr = array(); # Did this table open a <tr> element?
		$indent_level = 0; # indent level of the table

		foreach ( $lines as $outLine ) {
			$line = trim( $outLine );

			if ( $line === '' ) { # empty line, go to next line
				$out .= $outLine . "\n";
				continue;
			}

			$first_character = $line[0];
			$matches = array();

			if ( preg_match( '/^(:*)\{\|(.*)$/', $line, $matches ) ) {
				# First check if we are starting a new table
				$indent_level = strlen( $matches[1] );

				$attributes = $this->mStripState->unstripBoth( $matches[2] );
				$attributes = Sanitizer::fixTagAttributes( $attributes, 'table' );

				$outLine = str_repeat( '<dl><dd>', $indent_level ) . "<table{$attributes}>";
				array_push( $td_history, false );
				array_push( $last_tag_history, '' );
				array_push( $tr_history, false );
				array_push( $tr_attributes, '' );
				array_push( $has_opened_tr, false );
			} elseif ( count( $td_history ) == 0 ) {
				# Don't do any of the following
				$out .= $outLine . "\n";
				continue;
			} elseif ( substr( $line, 0, 2 ) === '|}' ) {
				# We are ending a table
				$line = '</table>' . substr( $line, 2 );
				$last_tag = array_pop( $last_tag_history );

				if ( !array_pop( $has_opened_tr ) ) {
					$line = "<tr><td></td></tr>{$line}";
				}

				if ( array_pop( $tr_history ) ) {
					$line = "</tr>{$line}";
				}

				if ( array_pop( $td_history ) ) {
					$line = "</{$last_tag}>{$line}";
				}
				array_pop( $tr_attributes );
				$outLine = $line . str_repeat( '</dd></dl>', $indent_level );
			} elseif ( substr( $line, 0, 2 ) === '|-' ) {
				# Now we have a table row
				$line = preg_replace( '#^\|-+#', '', $line );

				# Whats after the tag is now only attributes
				$attributes = $this->mStripState->unstripBoth( $line );
				$attributes = Sanitizer::fixTagAttributes( $attributes, 'tr' );
				array_pop( $tr_attributes );
				array_push( $tr_attributes, $attributes );

				$line = '';
				$last_tag = array_pop( $last_tag_history );
				array_pop( $has_opened_tr );
				array_push( $has_opened_tr, true );

				if ( array_pop( $tr_history ) ) {
					$line = '</tr>';
				}

				if ( array_pop( $td_history ) ) {
					$line = "</{$last_tag}>{$line}";
				}

				$outLine = $line;
				array_push( $tr_history, false );
				array_push( $td_history, false );
				array_push( $last_tag_history, '' );
			} elseif ( $first_character === '|' || $first_character === '!' || substr( $line, 0, 2 ) === '|+' ) {
				# This might be cell elements, td, th or captions
				if ( substr( $line, 0, 2 ) === '|+' ) {
					$first_character = '+';
					$line = substr( $line, 1 );
				}

				$line = substr( $line, 1 );

				if ( $first_character === '!' ) {
					$line = str_replace( '!!', '||', $line );
				}

				# Split up multiple cells on the same line.
				# FIXME : This can result in improper nesting of tags processed
				# by earlier parser steps, but should avoid splitting up eg
				# attribute values containing literal "||".
				$cells = StringUtils::explodeMarkup( '||', $line );

				$outLine = '';

				# Loop through each table cell
				foreach ( $cells as $cell ) {
					$previous = '';
					if ( $first_character !== '+' ) {
						$tr_after = array_pop( $tr_attributes );
						if ( !array_pop( $tr_history ) ) {
							$previous = "<tr{$tr_after}>\n";
						}
						array_push( $tr_history, true );
						array_push( $tr_attributes, '' );
						array_pop( $has_opened_tr );
						array_push( $has_opened_tr, true );
					}

					$last_tag = array_pop( $last_tag_history );

					if ( array_pop( $td_history ) ) {
						$previous = "</{$last_tag}>\n{$previous}";
					}

					if ( $first_character === '|' ) {
						$last_tag = 'td';
					} elseif ( $first_character === '!' ) {
						$last_tag = 'th';
					} elseif ( $first_character === '+' ) {
						$last_tag = 'caption';
					} else {
						$last_tag = '';
					}

					array_push( $last_tag_history, $last_tag );

					# A cell could contain both parameters and data
					$cell_data = explode( '|', $cell, 2 );

					# Bug 553: Note that a '|' inside an invalid link should not
					# be mistaken as delimiting cell parameters
					if ( strpos( $cell_data[0], '[[' ) !== false ) {
						$cell = "{$previous}<{$last_tag}>{$cell}";
					} elseif ( count( $cell_data ) == 1 ) {
						$cell = "{$previous}<{$last_tag}>{$cell_data[0]}";
					} else {
						$attributes = $this->mStripState->unstripBoth( $cell_data[0] );
						$attributes = Sanitizer::fixTagAttributes( $attributes, $last_tag );
						$cell = "{$previous}<{$last_tag}{$attributes}>{$cell_data[1]}";
					}

					$outLine .= $cell;
					array_push( $td_history, true );
				}
			}
			$out .= $outLine . "\n";
		}

		# Closing open td, tr && table
		while ( count( $td_history ) > 0 ) {
			if ( array_pop( $td_history ) ) {
				$out .= "</td>\n";
			}
			if ( array_pop( $tr_history ) ) {
				$out .= "</tr>\n";
			}
			if ( !array_pop( $has_opened_tr ) ) {
				$out .= "<tr><td></td></tr>\n";
			}

			$out .= "</table>\n";
		}

		# Remove trailing line-ending (b/c)
		if ( substr( $out, -1 ) === "\n" ) {
			$out = substr( $out, 0, -1 );
		}

		# special case: don't return empty table
		if ( $out === "<table>\n<tr><td></td></tr>\n</table>" ) {
			$out = '';
		}

		wfProfileOut( __METHOD__ );

		return $out;
	}

	/**
	 * Helper function for parse() that transforms wiki markup into
	 * HTML. Only called for $mOutputType == self::OT_HTML.
	 *
	 * @private
	 *
	 * @param $text string
	 * @param $isMain bool
	 * @param $frame bool
	 *
	 * @return string
	 */
	function internalParse( $text, $isMain = true, $frame = false ) {
		wfProfileIn( __METHOD__ );

		$origText = $text;

		# Hook to suspend the parser in this state
		if ( !wfRunHooks( 'ParserBeforeInternalParse', array( &$this, &$text, &$this->mStripState ) ) ) {
			wfProfileOut( __METHOD__ );
			return $text;
		}

		# if $frame is provided, then use $frame for replacing any variables
		if ( $frame ) {
			# use frame depth to infer how include/noinclude tags should be handled
			# depth=0 means this is the top-level document; otherwise it's an included document
			if ( !$frame->depth ) {
				$flag = 0;
			} else {
				$flag = Parser::PTD_FOR_INCLUSION;
			}
			$dom = $this->preprocessToDom( $text, $flag );
			$text = $frame->expand( $dom );
		} else {
			# if $frame is not provided, then use old-style replaceVariables
			$text = $this->replaceVariables( $text );
		}

		wfRunHooks( 'InternalParseBeforeSanitize', array( &$this, &$text, &$this->mStripState ) );
		$text = Sanitizer::removeHTMLtags( $text, array( &$this, 'attributeStripCallback' ), false, array_keys( $this->mTransparentTagHooks ) );
		wfRunHooks( 'InternalParseBeforeLinks', array( &$this, &$text, &$this->mStripState ) );

		# Tables need to come after variable replacement for things to work
		# properly; putting them before other transformations should keep
		# exciting things like link expansions from showing up in surprising
		# places.
		$text = $this->doTableStuff( $text );

		$text = preg_replace( '/(^|\n)-----*/', '\\1<hr />', $text );

		$text = $this->doDoubleUnderscore( $text );

		$text = $this->doHeadings( $text );
		$text = $this->replaceInternalLinks( $text );
		$text = $this->doAllQuotes( $text );
		$text = $this->replaceExternalLinks( $text );

		# replaceInternalLinks may sometimes leave behind
		# absolute URLs, which have to be masked to hide them from replaceExternalLinks
		$text = str_replace( $this->mUniqPrefix . 'NOPARSE', '', $text );

		$text = $this->doMagicLinks( $text );
		$text = $this->formatHeadings( $text, $origText, $isMain );

		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Replace special strings like "ISBN xxx" and "RFC xxx" with
	 * magic external links.
	 *
	 * DML
	 * @private
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	function doMagicLinks( $text ) {
		wfProfileIn( __METHOD__ );
		$prots = wfUrlProtocolsWithoutProtRel();
		$urlChar = self::EXT_LINK_URL_CLASS;
		$text = preg_replace_callback(
			'!(?:                           # Start cases
				(<a[ \t\r\n>].*?</a>) |     # m[1]: Skip link text
				(<.*?>) |                   # m[2]: Skip stuff inside HTML elements' . "
				(\\b(?i:$prots)$urlChar+) |  # m[3]: Free external links" . '
				(?:RFC|PMID)\s+([0-9]+) |   # m[4]: RFC or PMID, capture number
				ISBN\s+(\b                  # m[5]: ISBN, capture number
					(?: 97[89] [\ \-]? )?   # optional 13-digit ISBN prefix
					(?: [0-9]  [\ \-]? ){9} # 9 digits with opt. delimiters
					[0-9Xx]                 # check digit
					\b)
			)!xu', array( &$this, 'magicLinkCallback' ), $text );
		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * @throws MWException
	 * @param $m array
	 * @return HTML|string
	 */
	function magicLinkCallback( $m ) {
		if ( isset( $m[1] ) && $m[1] !== '' ) {
			# Skip anchor
			return $m[0];
		} elseif ( isset( $m[2] ) && $m[2] !== '' ) {
			# Skip HTML element
			return $m[0];
		} elseif ( isset( $m[3] ) && $m[3] !== '' ) {
			# Free external link
			return $this->makeFreeExternalLink( $m[0] );
		} elseif ( isset( $m[4] ) && $m[4] !== '' ) {
			# RFC or PMID
			if ( substr( $m[0], 0, 3 ) === 'RFC' ) {
				$keyword = 'RFC';
				$urlmsg = 'rfcurl';
				$CssClass = 'mw-magiclink-rfc';
				$id = $m[4];
			} elseif ( substr( $m[0], 0, 4 ) === 'PMID' ) {
				$keyword = 'PMID';
				$urlmsg = 'pubmedurl';
				$CssClass = 'mw-magiclink-pmid';
				$id = $m[4];
			} else {
				throw new MWException( __METHOD__ . ': unrecognised match type "' .
					substr( $m[0], 0, 20 ) . '"' );
			}
			$url = wfMessage( $urlmsg, $id )->inContentLanguage()->text();
			return Linker::makeExternalLink( $url, "{$keyword} {$id}", true, $CssClass );
		} elseif ( isset( $m[5] ) && $m[5] !== '' ) {
			# ISBN
			$isbn = $m[5];
			$num = strtr( $isbn, array(
				'-' => '',
				' ' => '',
				'x' => 'X',
			));
			$titleObj = SpecialPage::getTitleFor( 'Booksources', $num );
			return '<a href="' .
				htmlspecialchars( $titleObj->getLocalURL() ) .
				"\" class=\"internal mw-magiclink-isbn\">ISBN $isbn</a>";
		} else {
			return $m[0];
		}
	}

	/**
	 * Make a free external link, given a user-supplied URL
	 *
	 * @param $url string
	 *
	 * @return string HTML
	 * @private
	 */
	function makeFreeExternalLink( $url ) {
		wfProfileIn( __METHOD__ );

		$trail = '';

		# The characters '<' and '>' (which were escaped by
		# removeHTMLtags()) should not be included in
		# URLs, per RFC 2396.
		$m2 = array();
		if ( preg_match( '/&(lt|gt);/', $url, $m2, PREG_OFFSET_CAPTURE ) ) {
			$trail = substr( $url, $m2[0][1] ) . $trail;
			$url = substr( $url, 0, $m2[0][1] );
		}

		# Move trailing punctuation to $trail
		$sep = ',;\.:!?';
		# If there is no left bracket, then consider right brackets fair game too
		if ( strpos( $url, '(' ) === false ) {
			$sep .= ')';
		}

		$numSepChars = strspn( strrev( $url ), $sep );
		if ( $numSepChars ) {
			$trail = substr( $url, -$numSepChars ) . $trail;
			$url = substr( $url, 0, -$numSepChars );
		}

		$url = Sanitizer::cleanUrl( $url );

		# Is this an external image?
		$text = $this->maybeMakeExternalImage( $url );
		if ( $text === false ) {
			# Not an image, make a link
			$text = Linker::makeExternalLink( $url,
				$this->getConverterLanguage()->markNoConversion( $url, true ),
				true, 'free',
				$this->getExternalLinkAttribs( $url ) );
			# Register it in the output object...
			# Replace unnecessary URL escape codes with their equivalent characters
			$pasteurized = self::replaceUnusualEscapes( $url );
			$this->mOutput->addExternalLink( $pasteurized );
		}
		wfProfileOut( __METHOD__ );
		return $text . $trail;
	}

	/**
	 * Parse headers and return html
	 *
	 * @private
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	function doHeadings( $text ) {
		wfProfileIn( __METHOD__ );
		for ( $i = 6; $i >= 1; --$i ) {
			$h = str_repeat( '=', $i );
			$text = preg_replace( "/^$h(.+)$h\\s*$/m", "<h$i>\\1</h$i>", $text );
		}
		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Replace single quotes with HTML markup
	 * @private
	 *
	 * @param $text string
	 *
	 * @return string the altered text
	 */
	function doAllQuotes( $text ) {
		wfProfileIn( __METHOD__ );
		$outtext = '';
		$lines = StringUtils::explode( "\n", $text );
		foreach ( $lines as $line ) {
			$outtext .= $this->doQuotes( $line ) . "\n";
		}
		$outtext = substr( $outtext, 0, -1 );
		wfProfileOut( __METHOD__ );
		return $outtext;
	}

	/**
	 * Helper function for doAllQuotes()
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	public function doQuotes( $text ) {
		$arr = preg_split( "/(''+)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$countarr = count( $arr );
		if ( $countarr == 1 ) {
			return $text;
		}

		// First, do some preliminary work. This may shift some apostrophes from
		// being mark-up to being text. It also counts the number of occurrences
		// of bold and italics mark-ups.
		$numbold = 0;
		$numitalics = 0;
		for ( $i = 1; $i < $countarr; $i += 2 ) {
			$thislen = strlen( $arr[$i] );
			// If there are ever four apostrophes, assume the first is supposed to
			// be text, and the remaining three constitute mark-up for bold text.
			// (bug 13227: ''''foo'''' turns into ' ''' foo ' ''')
			if ( $thislen == 4 ) {
				$arr[$i - 1] .= "'";
				$arr[$i] = "'''";
				$thislen = 3;
			} elseif ( $thislen > 5 ) {
				// If there are more than 5 apostrophes in a row, assume they're all
				// text except for the last 5.
				// (bug 13227: ''''''foo'''''' turns into ' ''''' foo ' ''''')
				$arr[$i - 1] .= str_repeat( "'", $thislen - 5 );
				$arr[$i] = "'''''";
				$thislen = 5;
			}
			// Count the number of occurrences of bold and italics mark-ups.
			if ( $thislen == 2 ) {
				$numitalics++;
			} elseif ( $thislen == 3 ) {
				$numbold++;
			} elseif ( $thislen == 5 ) {
				$numitalics++;
				$numbold++;
			}
		}

		// If there is an odd number of both bold and italics, it is likely
		// that one of the bold ones was meant to be an apostrophe followed
		// by italics. Which one we cannot know for certain, but it is more
		// likely to be one that has a single-letter word before it.
		if ( ( $numbold % 2 == 1 ) && ( $numitalics % 2 == 1 ) ) {
			$firstsingleletterword = -1;
			$firstmultiletterword = -1;
			$firstspace = -1;
			for ( $i = 1; $i < $countarr; $i += 2 ) {
				if ( strlen( $arr[$i] ) == 3 ) {
					$x1 = substr( $arr[$i - 1], -1 );
					$x2 = substr( $arr[$i - 1], -2, 1 );
					if ( $x1 === ' ' ) {
						if ( $firstspace == -1 ) {
							$firstspace = $i;
						}
					} elseif ( $x2 === ' ' ) {
						if ( $firstsingleletterword == -1 ) {
							$firstsingleletterword = $i;
							// if $firstsingleletterword is set, we don't
							// look at the other options, so we can bail early.
							break;
						}
					} else {
						if ( $firstmultiletterword == -1 ) {
							$firstmultiletterword = $i;
						}
					}
				}
			}

			// If there is a single-letter word, use it!
			if ( $firstsingleletterword > -1 ) {
				$arr[$firstsingleletterword] = "''";
				$arr[$firstsingleletterword - 1] .= "'";
			} elseif ( $firstmultiletterword > -1 ) {
				// If not, but there's a multi-letter word, use that one.
				$arr[$firstmultiletterword] = "''";
				$arr[$firstmultiletterword - 1] .= "'";
			} elseif ( $firstspace > -1 ) {
				// ... otherwise use the first one that has neither.
				// (notice that it is possible for all three to be -1 if, for example,
				// there is only one pentuple-apostrophe in the line)
				$arr[$firstspace] = "''";
				$arr[$firstspace - 1] .= "'";
			}
		}

		// Now let's actually convert our apostrophic mush to HTML!
		$output = '';
		$buffer = '';
		$state = '';
		$i = 0;
		foreach ( $arr as $r ) {
			if ( ( $i % 2 ) == 0 ) {
				if ( $state === 'both' ) {
					$buffer .= $r;
				} else {
					$output .= $r;
				}
			} else {
				$thislen = strlen( $r );
				if ( $thislen == 2 ) {
					if ( $state === 'i' ) {
						$output .= '</i>';
						$state = '';
					} elseif ( $state === 'bi' ) {
						$output .= '</i>';
						$state = 'b';
					} elseif ( $state === 'ib' ) {
						$output .= '</b></i><b>';
						$state = 'b';
					} elseif ( $state === 'both' ) {
						$output .= '<b><i>' . $buffer . '</i>';
						$state = 'b';
					} else { // $state can be 'b' or ''
						$output .= '<i>';
						$state .= 'i';
					}
				} elseif ( $thislen == 3 ) {
					if ( $state === 'b' ) {
						$output .= '</b>';
						$state = '';
					} elseif ( $state === 'bi' ) {
						$output .= '</i></b><i>';
						$state = 'i';
					} elseif ( $state === 'ib' ) {
						$output .= '</b>';
						$state = 'i';
					} elseif ( $state === 'both' ) {
						$output .= '<i><b>' . $buffer . '</b>';
						$state = 'i';
					} else { // $state can be 'i' or ''
						$output .= '<b>';
						$state .= 'b';
					}
				} elseif ( $thislen == 5 ) {
					if ( $state === 'b' ) {
						$output .= '</b><i>';
						$state = 'i';
					} elseif ( $state === 'i' ) {
						$output .= '</i><b>';
						$state = 'b';
					} elseif ( $state === 'bi' ) {
						$output .= '</i></b>';
						$state = '';
					} elseif ( $state === 'ib' ) {
						$output .= '</b></i>';
						$state = '';
					} elseif ( $state === 'both' ) {
						$output .= '<i><b>' . $buffer . '</b></i>';
						$state = '';
					} else { // ($state == '')
						$buffer = '';
						$state = 'both';
					}
				}
			}
			$i++;
		}
		// Now close all remaining tags.  Notice that the order is important.
		if ( $state === 'b' || $state === 'ib' ) {
			$output .= '</b>';
		}
		if ( $state === 'i' || $state === 'bi' || $state === 'ib' ) {
			$output .= '</i>';
		}
		if ( $state === 'bi' ) {
			$output .= '</b>';
		}
		// There might be lonely ''''', so make sure we have a buffer
		if ( $state === 'both' && $buffer ) {
			$output .= '<b><i>' . $buffer . '</i></b>';
		}
		return $output;
	}

	/**
	 * Replace external links (REL)
	 *
	 * Note: this is all very hackish and the order of execution matters a lot.
	 * Make sure to run tests/parserTests.php if you change this code.
	 *
	 * @private
	 *
	 * @param $text string
	 *
	 * @throws MWException
	 * @return string
	 */
	function replaceExternalLinks( $text ) {
		wfProfileIn( __METHOD__ );

		$bits = preg_split( $this->mExtLinkBracketedRegex, $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( $bits === false ) {
			wfProfileOut( __METHOD__ );
			throw new MWException( "PCRE needs to be compiled with --enable-unicode-properties in order for MediaWiki to function" );
		}
		$s = array_shift( $bits );

		$i = 0;
		while ( $i < count( $bits ) ) {
			$url = $bits[$i++];
			$i++; // protocol
			$text = $bits[$i++];
			$trail = $bits[$i++];

			# The characters '<' and '>' (which were escaped by
			# removeHTMLtags()) should not be included in
			# URLs, per RFC 2396.
			$m2 = array();
			if ( preg_match( '/&(lt|gt);/', $url, $m2, PREG_OFFSET_CAPTURE ) ) {
				$text = substr( $url, $m2[0][1] ) . ' ' . $text;
				$url = substr( $url, 0, $m2[0][1] );
			}

			# If the link text is an image URL, replace it with an <img> tag
			# This happened by accident in the original parser, but some people used it extensively
			$img = $this->maybeMakeExternalImage( $text );
			if ( $img !== false ) {
				$text = $img;
			}

			$dtrail = '';

			# Set linktype for CSS - if URL==text, link is essentially free
			$linktype = ( $text === $url ) ? 'free' : 'text';

			# No link text, e.g. [http://domain.tld/some.link]
			if ( $text == '' ) {
				# Autonumber
				$langObj = $this->getTargetLanguage();
				$text = '[' . $langObj->formatNum( ++$this->mAutonumber ) . ']';
				$linktype = 'autonumber';
			} else {
				# Have link text, e.g. [http://domain.tld/some.link text]s
				# Check for trail
				list( $dtrail, $trail ) = Linker::splitTrail( $trail );
			}

			$text = $this->getConverterLanguage()->markNoConversion( $text );

			$url = Sanitizer::cleanUrl( $url );

			# Use the encoded URL
			# This means that users can paste URLs directly into the text
			# Funny characters like ö aren't valid in URLs anyway
			# This was changed in August 2004
			$s .= Linker::makeExternalLink( $url, $text, false, $linktype,
				$this->getExternalLinkAttribs( $url ) ) . $dtrail . $trail;

			# Register link in the output object.
			# Replace unnecessary URL escape codes with the referenced character
			# This prevents spammers from hiding links from the filters
			$pasteurized = self::replaceUnusualEscapes( $url );
			$this->mOutput->addExternalLink( $pasteurized );
		}

		wfProfileOut( __METHOD__ );
		return $s;
	}
	/**
	 * Get the rel attribute for a particular external link.
	 *
	 * @since 1.21
	 * @param string|bool $url optional URL, to extract the domain from for rel =>
	 *   nofollow if appropriate
	 * @param $title Title optional Title, for wgNoFollowNsExceptions lookups
	 * @return string|null rel attribute for $url
	 */
	public static function getExternalLinkRel( $url = false, $title = null ) {
		global $wgNoFollowLinks, $wgNoFollowNsExceptions, $wgNoFollowDomainExceptions;
		$ns = $title ? $title->getNamespace() : false;
		if ( $wgNoFollowLinks && !in_array( $ns, $wgNoFollowNsExceptions )
			&& !wfMatchesDomainList( $url, $wgNoFollowDomainExceptions )
		) {
			return 'nofollow';
		}
		return null;
	}
	/**
	 * Get an associative array of additional HTML attributes appropriate for a
	 * particular external link.  This currently may include rel => nofollow
	 * (depending on configuration, namespace, and the URL's domain) and/or a
	 * target attribute (depending on configuration).
	 *
	 * @param string|bool $url optional URL, to extract the domain from for rel =>
	 *   nofollow if appropriate
	 * @return Array associative array of HTML attributes
	 */
	function getExternalLinkAttribs( $url = false ) {
		$attribs = array();
		$attribs['rel'] = self::getExternalLinkRel( $url, $this->mTitle );

		if ( $this->mOptions->getExternalLinkTarget() ) {
			$attribs['target'] = $this->mOptions->getExternalLinkTarget();
		}
		return $attribs;
	}

	/**
	 * Replace unusual URL escape codes with their equivalent characters
	 *
	 * @param $url String
	 * @return String
	 *
	 * @todo  This can merge genuinely required bits in the path or query string,
	 *        breaking legit URLs. A proper fix would treat the various parts of
	 *        the URL differently; as a workaround, just use the output for
	 *        statistical records, not for actual linking/output.
	 */
	static function replaceUnusualEscapes( $url ) {
		return preg_replace_callback( '/%[0-9A-Fa-f]{2}/',
			array( __CLASS__, 'replaceUnusualEscapesCallback' ), $url );
	}

	/**
	 * Callback function used in replaceUnusualEscapes().
	 * Replaces unusual URL escape codes with their equivalent character
	 *
	 * @param $matches array
	 *
	 * @return string
	 */
	private static function replaceUnusualEscapesCallback( $matches ) {
		$char = urldecode( $matches[0] );
		$ord = ord( $char );
		# Is it an unsafe or HTTP reserved character according to RFC 1738?
		if ( $ord > 32 && $ord < 127 && strpos( '<>"#{}|\^~[]`;/?', $char ) === false ) {
			# No, shouldn't be escaped
			return $char;
		} else {
			# Yes, leave it escaped
			return $matches[0];
		}
	}

	/**
	 * make an image if it's allowed, either through the global
	 * option, through the exception, or through the on-wiki whitelist
	 * @private
	 *
	 * $param $url string
	 *
	 * @return string
	 */
	function maybeMakeExternalImage( $url ) {
		$imagesfrom = $this->mOptions->getAllowExternalImagesFrom();
		$imagesexception = !empty( $imagesfrom );
		$text = false;
		# $imagesfrom could be either a single string or an array of strings, parse out the latter
		if ( $imagesexception && is_array( $imagesfrom ) ) {
			$imagematch = false;
			foreach ( $imagesfrom as $match ) {
				if ( strpos( $url, $match ) === 0 ) {
					$imagematch = true;
					break;
				}
			}
		} elseif ( $imagesexception ) {
			$imagematch = ( strpos( $url, $imagesfrom ) === 0 );
		} else {
			$imagematch = false;
		}
		if ( $this->mOptions->getAllowExternalImages()
			|| ( $imagesexception && $imagematch ) ) {
			if ( preg_match( self::EXT_IMAGE_REGEX, $url ) ) {
				# Image found
				$text = Linker::makeExternalImage( $url );
			}
		}
		if ( !$text && $this->mOptions->getEnableImageWhitelist()
			&& preg_match( self::EXT_IMAGE_REGEX, $url ) ) {
			$whitelist = explode( "\n", wfMessage( 'external_image_whitelist' )->inContentLanguage()->text() );
			foreach ( $whitelist as $entry ) {
				# Sanitize the regex fragment, make it case-insensitive, ignore blank entries/comments
				if ( strpos( $entry, '#' ) === 0 || $entry === '' ) {
					continue;
				}
				if ( preg_match( '/' . str_replace( '/', '\\/', $entry ) . '/i', $url ) ) {
					# Image matches a whitelist entry
					$text = Linker::makeExternalImage( $url );
					break;
				}
			}
		}
		return $text;
	}

	/**
	 * Process [[ ]] wikilinks
	 *
	 * @param $s string
	 *
	 * @return String: processed text
	 *
	 * @private
	 */
	function replaceInternalLinks( $s ) {
		$this->mLinkHolders->merge( $this->replaceInternalLinks2( $s ) );
		return $s;
	}

	/**
	 * Process [[ ]] wikilinks (RIL)
	 * @param $s
	 * @throws MWException
	 * @return LinkHolderArray
	 *
	 * @private
	 */
	function replaceInternalLinks2( &$s ) {
		wfProfileIn( __METHOD__ );

		wfProfileIn( __METHOD__ . '-setup' );
		static $tc = false, $e1, $e1_img;
		# the % is needed to support urlencoded titles as well
		if ( !$tc ) {
			$tc = Title::legalChars() . '#%';
			# Match a link having the form [[namespace:link|alternate]]trail
			$e1 = "/^([{$tc}]+)(?:\\|(.+?))?]](.*)\$/sD";
			# Match cases where there is no "]]", which might still be images
			$e1_img = "/^([{$tc}]+)\\|(.*)\$/sD";
		}

		$holders = new LinkHolderArray( $this );

		# split the entire text string on occurrences of [[
		$a = StringUtils::explode( '[[', ' ' . $s );
		# get the first element (all text up to first [[), and remove the space we added
		$s = $a->current();
		$a->next();
		$line = $a->current(); # Workaround for broken ArrayIterator::next() that returns "void"
		$s = substr( $s, 1 );

		$useLinkPrefixExtension = $this->getTargetLanguage()->linkPrefixExtension();
		$e2 = null;
		if ( $useLinkPrefixExtension ) {
			# Match the end of a line for a word that's not followed by whitespace,
			# e.g. in the case of 'The Arab al[[Razi]]', 'al' will be matched
			global $wgContLang;
			$charset = $wgContLang->linkPrefixCharset();
			$e2 = "/^((?>.*[^$charset]|))(.+)$/sDu";
		}

		if ( is_null( $this->mTitle ) ) {
			wfProfileOut( __METHOD__ . '-setup' );
			wfProfileOut( __METHOD__ );
			throw new MWException( __METHOD__ . ": \$this->mTitle is null\n" );
		}
		$nottalk = !$this->mTitle->isTalkPage();

		if ( $useLinkPrefixExtension ) {
			$m = array();
			if ( preg_match( $e2, $s, $m ) ) {
				$first_prefix = $m[2];
			} else {
				$first_prefix = false;
			}
		} else {
			$prefix = '';
		}

		$useSubpages = $this->areSubpagesAllowed();
		wfProfileOut( __METHOD__ . '-setup' );

		# Loop for each link
		for ( ; $line !== false && $line !== null; $a->next(), $line = $a->current() ) {
			# Check for excessive memory usage
			if ( $holders->isBig() ) {
				# Too big
				# Do the existence check, replace the link holders and clear the array
				$holders->replace( $s );
				$holders->clear();
			}

			if ( $useLinkPrefixExtension ) {
				wfProfileIn( __METHOD__ . '-prefixhandling' );
				if ( preg_match( $e2, $s, $m ) ) {
					$prefix = $m[2];
					$s = $m[1];
				} else {
					$prefix = '';
				}
				# first link
				if ( $first_prefix ) {
					$prefix = $first_prefix;
					$first_prefix = false;
				}
				wfProfileOut( __METHOD__ . '-prefixhandling' );
			}

			$might_be_img = false;

			wfProfileIn( __METHOD__ . "-e1" );
			if ( preg_match( $e1, $line, $m ) ) { # page with normal text or alt
				$text = $m[2];
				# If we get a ] at the beginning of $m[3] that means we have a link that's something like:
				# [[Image:Foo.jpg|[http://example.com desc]]] <- having three ] in a row fucks up,
				# the real problem is with the $e1 regex
				# See bug 1300.
				#
				# Still some problems for cases where the ] is meant to be outside punctuation,
				# and no image is in sight. See bug 2095.
				#
				if ( $text !== ''
					&& substr( $m[3], 0, 1 ) === ']'
					&& strpos( $text, '[' ) !== false
				) {
					$text .= ']'; # so that replaceExternalLinks($text) works later
					$m[3] = substr( $m[3], 1 );
				}
				# fix up urlencoded title texts
				if ( strpos( $m[1], '%' ) !== false ) {
					# Should anchors '#' also be rejected?
					$m[1] = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), rawurldecode( $m[1] ) );
				}
				$trail = $m[3];
			} elseif ( preg_match( $e1_img, $line, $m ) ) { # Invalid, but might be an image with a link in its caption
				$might_be_img = true;
				$text = $m[2];
				if ( strpos( $m[1], '%' ) !== false ) {
					$m[1] = rawurldecode( $m[1] );
				}
				$trail = "";
			} else { # Invalid form; output directly
				$s .= $prefix . '[[' . $line;
				wfProfileOut( __METHOD__ . "-e1" );
				continue;
			}
			wfProfileOut( __METHOD__ . "-e1" );
			wfProfileIn( __METHOD__ . "-misc" );

			# Don't allow internal links to pages containing
			# PROTO: where PROTO is a valid URL protocol; these
			# should be external links.
			if ( preg_match( '/^(?i:' . $this->mUrlProtocols . ')/', $m[1] ) ) {
				$s .= $prefix . '[[' . $line;
				wfProfileOut( __METHOD__ . "-misc" );
				continue;
			}

			# Make subpage if necessary
			if ( $useSubpages ) {
				$link = $this->maybeDoSubpageLink( $m[1], $text );
			} else {
				$link = $m[1];
			}

			$noforce = ( substr( $m[1], 0, 1 ) !== ':' );
			if ( !$noforce ) {
				# Strip off leading ':'
				$link = substr( $link, 1 );
			}

			wfProfileOut( __METHOD__ . "-misc" );
			wfProfileIn( __METHOD__ . "-title" );
			$nt = Title::newFromText( $this->mStripState->unstripNoWiki( $link ) );
			if ( $nt === null ) {
				$s .= $prefix . '[[' . $line;
				wfProfileOut( __METHOD__ . "-title" );
				continue;
			}

			$ns = $nt->getNamespace();
			$iw = $nt->getInterwiki();
			wfProfileOut( __METHOD__ . "-title" );

			if ( $might_be_img ) { # if this is actually an invalid link
				wfProfileIn( __METHOD__ . "-might_be_img" );
				if ( $ns == NS_FILE && $noforce ) { # but might be an image
					$found = false;
					while ( true ) {
						# look at the next 'line' to see if we can close it there
						$a->next();
						$next_line = $a->current();
						if ( $next_line === false || $next_line === null ) {
							break;
						}
						$m = explode( ']]', $next_line, 3 );
						if ( count( $m ) == 3 ) {
							# the first ]] closes the inner link, the second the image
							$found = true;
							$text .= "[[{$m[0]}]]{$m[1]}";
							$trail = $m[2];
							break;
						} elseif ( count( $m ) == 2 ) {
							# if there's exactly one ]] that's fine, we'll keep looking
							$text .= "[[{$m[0]}]]{$m[1]}";
						} else {
							# if $next_line is invalid too, we need look no further
							$text .= '[[' . $next_line;
							break;
						}
					}
					if ( !$found ) {
						# we couldn't find the end of this imageLink, so output it raw
						# but don't ignore what might be perfectly normal links in the text we've examined
						$holders->merge( $this->replaceInternalLinks2( $text ) );
						$s .= "{$prefix}[[$link|$text";
						# note: no $trail, because without an end, there *is* no trail
						wfProfileOut( __METHOD__ . "-might_be_img" );
						continue;
					}
				} else { # it's not an image, so output it raw
					$s .= "{$prefix}[[$link|$text";
					# note: no $trail, because without an end, there *is* no trail
					wfProfileOut( __METHOD__ . "-might_be_img" );
					continue;
				}
				wfProfileOut( __METHOD__ . "-might_be_img" );
			}

			$wasblank = ( $text == '' );
			if ( $wasblank ) {
				$text = $link;
			} else {
				# Bug 4598 madness. Handle the quotes only if they come from the alternate part
				# [[Lista d''e paise d''o munno]] -> <a href="...">Lista d''e paise d''o munno</a>
				# [[Criticism of Harry Potter|Criticism of ''Harry Potter'']]
				#    -> <a href="Criticism of Harry Potter">Criticism of <i>Harry Potter</i></a>
				$text = $this->doQuotes( $text );
			}

			# Link not escaped by : , create the various objects
			if ( $noforce ) {
				# Interwikis
				wfProfileIn( __METHOD__ . "-interwiki" );
				if ( $iw && $this->mOptions->getInterwikiMagic() && $nottalk && Language::fetchLanguageName( $iw, null, 'mw' ) ) {
					// XXX: the above check prevents links to sites with identifiers that are not language codes

					# Bug 24502: filter duplicates
					if ( !isset( $this->mLangLinkLanguages[$iw] ) ) {
						$this->mLangLinkLanguages[$iw] = true;
						$this->mOutput->addLanguageLink( $nt->getFullText() );
					}

					$s = rtrim( $s . $prefix );
					$s .= trim( $trail, "\n" ) == '' ? '': $prefix . $trail;
					wfProfileOut( __METHOD__ . "-interwiki" );
					continue;
				}
				wfProfileOut( __METHOD__ . "-interwiki" );

				if ( $ns == NS_FILE ) {
					wfProfileIn( __METHOD__ . "-image" );
					if ( !wfIsBadImage( $nt->getDBkey(), $this->mTitle ) ) {
						if ( $wasblank ) {
							# if no parameters were passed, $text
							# becomes something like "File:Foo.png",
							# which we don't want to pass on to the
							# image generator
							$text = '';
						} else {
							# recursively parse links inside the image caption
							# actually, this will parse them in any other parameters, too,
							# but it might be hard to fix that, and it doesn't matter ATM
							$text = $this->replaceExternalLinks( $text );
							$holders->merge( $this->replaceInternalLinks2( $text ) );
						}
						# cloak any absolute URLs inside the image markup, so replaceExternalLinks() won't touch them
						$s .= $prefix . $this->armorLinks(
							$this->makeImage( $nt, $text, $holders ) ) . $trail;
					} else {
						$s .= $prefix . $trail;
					}
					wfProfileOut( __METHOD__ . "-image" );
					continue;
				}

				if ( $ns == NS_CATEGORY ) {
					wfProfileIn( __METHOD__ . "-category" );
					$s = rtrim( $s . "\n" ); # bug 87

					if ( $wasblank ) {
						$sortkey = $this->getDefaultSort();
					} else {
						$sortkey = $text;
					}
					$sortkey = Sanitizer::decodeCharReferences( $sortkey );
					$sortkey = str_replace( "\n", '', $sortkey );
					$sortkey = $this->getConverterLanguage()->convertCategoryKey( $sortkey );
					$this->mOutput->addCategory( $nt->getDBkey(), $sortkey );

					/**
					 * Strip the whitespace Category links produce, see bug 87
					 */
					$s .= trim( $prefix . $trail, "\n" ) == '' ? '' : $prefix . $trail;

					wfProfileOut( __METHOD__ . "-category" );
					continue;
				}
			}

			# Self-link checking. For some languages, variants of the title are checked in
			# LinkHolderArray::doVariants() to allow batching the existence checks necessary
			# for linking to a different variant.
			if ( $ns != NS_SPECIAL && $nt->equals( $this->mTitle ) && !$nt->hasFragment() ) {
				$s .= $prefix . Linker::makeSelfLinkObj( $nt, $text, '', $trail );
				continue;
			}

			# NS_MEDIA is a pseudo-namespace for linking directly to a file
			# @todo FIXME: Should do batch file existence checks, see comment below
			if ( $ns == NS_MEDIA ) {
				wfProfileIn( __METHOD__ . "-media" );
				# Give extensions a chance to select the file revision for us
				$options = array();
				$descQuery = false;
				wfRunHooks( 'BeforeParserFetchFileAndTitle',
					array( $this, $nt, &$options, &$descQuery ) );
				# Fetch and register the file (file title may be different via hooks)
				list( $file, $nt ) = $this->fetchFileAndTitle( $nt, $options );
				# Cloak with NOPARSE to avoid replacement in replaceExternalLinks
				$s .= $prefix . $this->armorLinks(
					Linker::makeMediaLinkFile( $nt, $file, $text ) ) . $trail;
				wfProfileOut( __METHOD__ . "-media" );
				continue;
			}

			if ($ns == NS_DOCUMENT || $ns == NS_QUIZ || $ns == NS_WIDGET) {
				wfRunHooks( 'BeforeParserFetchFileAndTitle2', array( &$this, &$nt, &$ret, $ns ) );
				$s .= $prefix . $this->armorLinks( $ret ) . $trail;
				wfProfileOut( __METHOD__ . "-wikihow_namespace" );
				continue;
			}

			wfProfileIn( __METHOD__ . "-always_known" );
			# Some titles, such as valid special pages or files in foreign repos, should
			# be shown as bluelinks even though they're not included in the page table
			#
			# @todo FIXME: isAlwaysKnown() can be expensive for file links; we should really do
			# batch file existence checks for NS_FILE and NS_MEDIA
			if ( $iw == '' && $nt->isAlwaysKnown() ) {
				$this->mOutput->addLink( $nt );
				$s .= $this->makeKnownLinkHolder( $nt, $text, array(), $trail, $prefix );
			} else {
				# Links will be added to the output link list after checking
				$s .= $holders->makeHolder( $nt, $text, array(), $trail, $prefix );
			}
			wfProfileOut( __METHOD__ . "-always_known" );
		}
		wfProfileOut( __METHOD__ );
		return $holders;
	}

	/**
	 * Render a forced-blue link inline; protect against double expansion of
	 * URLs if we're in a mode that prepends full URL prefixes to internal links.
	 * Since this little disaster has to split off the trail text to avoid
	 * breaking URLs in the following text without breaking trails on the
	 * wiki links, it's been made into a horrible function.
	 *
	 * @param $nt Title
	 * @param $text String
	 * @param array $query or String
	 * @param $trail String
	 * @param $prefix String
	 * @return String: HTML-wikitext mix oh yuck
	 */
	function makeKnownLinkHolder( $nt, $text = '', $query = array(), $trail = '', $prefix = '' ) {
		list( $inside, $trail ) = Linker::splitTrail( $trail );

		if ( is_string( $query ) ) {
			$query = wfCgiToArray( $query );
		}
		if ( $text == '' ) {
			$text = htmlspecialchars( $nt->getPrefixedText() );
		}

		$link = Linker::linkKnown( $nt, "$prefix$text$inside", array(), $query );

		return $this->armorLinks( $link ) . $trail;
	}

	/**
	 * Insert a NOPARSE hacky thing into any inline links in a chunk that's
	 * going to go through further parsing steps before inline URL expansion.
	 *
	 * Not needed quite as much as it used to be since free links are a bit
	 * more sensible these days. But bracketed links are still an issue.
	 *
	 * @param string $text more-or-less HTML
	 * @return String: less-or-more HTML with NOPARSE bits
	 */
	function armorLinks( $text ) {
		return preg_replace( '/\b((?i)' . $this->mUrlProtocols . ')/',
			"{$this->mUniqPrefix}NOPARSE$1", $text );
	}

	/**
	 * Return true if subpage links should be expanded on this page.
	 * @return Boolean
	 */
	function areSubpagesAllowed() {
		# Some namespaces don't allow subpages
		return MWNamespace::hasSubpages( $this->mTitle->getNamespace() );
	}

	/**
	 * Handle link to subpage if necessary
	 *
	 * @param string $target the source of the link
	 * @param &$text String: the link text, modified as necessary
	 * @return string the full name of the link
	 * @private
	 */
	function maybeDoSubpageLink( $target, &$text ) {
		return Linker::normalizeSubpageLink( $this->mTitle, $target, $text );
	}

	/**#@+
	 * Used by doBlockLevels()
	 * @private
	 *
	 * @return string
	 */
	function closeParagraph() {
		$result = '';
		if ( $this->mLastSection != '' ) {
			$result = '</' . $this->mLastSection . ">\n";
		}
		$this->mInPre = false;
		$this->mLastSection = '';
		return $result;
	}

	/**
	 * getCommon() returns the length of the longest common substring
	 * of both arguments, starting at the beginning of both.
	 * @private
	 *
	 * @param $st1 string
	 * @param $st2 string
	 *
	 * @return int
	 */
	function getCommon( $st1, $st2 ) {
		$fl = strlen( $st1 );
		$shorter = strlen( $st2 );
		if ( $fl < $shorter ) {
			$shorter = $fl;
		}

		for ( $i = 0; $i < $shorter; ++$i ) {
			if ( $st1[$i] != $st2[$i] ) {
				break;
			}
		}
		return $i;
	}

	/**
	 * These next three functions open, continue, and close the list
	 * element appropriate to the prefix character passed into them.
	 * @private
	 *
	 * @param $char string
	 *
	 * @return string
	 */
	function openList( $char ) {
		$result = $this->closeParagraph();

		if ( '*' === $char ) {
			$result .= "<ul>\n<li>";
		} elseif ( '#' === $char ) {
			$result .= "<ol>\n<li>";
		} elseif ( ':' === $char ) {
			$result .= "<dl>\n<dd>";
		} elseif ( ';' === $char ) {
			$result .= "<dl>\n<dt>";
			$this->mDTopen = true;
		} else {
			$result = '<!-- ERR 1 -->';
		}

		return $result;
	}

	/**
	 * TODO: document
	 * @param $char String
	 * @private
	 *
	 * @return string
	 */
	function nextItem( $char ) {
		if ( '*' === $char || '#' === $char ) {
			return "</li>\n<li>";
		} elseif ( ':' === $char || ';' === $char ) {
			$close = "</dd>\n";
			if ( $this->mDTopen ) {
				$close = "</dt>\n";
			}
			if ( ';' === $char ) {
				$this->mDTopen = true;
				return $close . '<dt>';
			} else {
				$this->mDTopen = false;
				return $close . '<dd>';
			}
		}
		return '<!-- ERR 2 -->';
	}

	/**
	 * TODO: document
	 * @param $char String
	 * @private
	 *
	 * @return string
	 */
	function closeList( $char ) {
		if ( '*' === $char ) {
			$text = "</li>\n</ul>";
		} elseif ( '#' === $char ) {
			$text = "</li>\n</ol>";
		} elseif ( ':' === $char ) {
			if ( $this->mDTopen ) {
				$this->mDTopen = false;
				$text = "</dt>\n</dl>";
			} else {
				$text = "</dd>\n</dl>";
			}
		} else {
			return '<!-- ERR 3 -->';
		}
		return $text . "\n";
	}
	/**#@-*/

	/**
	 * Make lists from lines starting with ':', '*', '#', etc. (DBL)
	 *
	 * @param $text String
	 * @param $linestart Boolean: whether or not this is at the start of a line.
	 * @private
	 * @return string the lists rendered as HTML
	 */
	function doBlockLevels( $text, $linestart ) {
		wfProfileIn( __METHOD__ );

		# Parsing through the text line by line.  The main thing
		# happening here is handling of block-level elements p, pre,
		# and making lists from lines starting with * # : etc.
		#
		$textLines = StringUtils::explode( "\n", $text );

		$lastPrefix = $output = '';
		$this->mDTopen = $inBlockElem = false;
		$prefixLength = 0;
		$paragraphStack = false;
		$inBlockquote = false;

		foreach ( $textLines as $oLine ) {
			# Fix up $linestart
			if ( !$linestart ) {
				$output .= $oLine;
				$linestart = true;
				continue;
			}
			# * = ul
			# # = ol
			# ; = dt
			# : = dd

			$lastPrefixLength = strlen( $lastPrefix );
			$preCloseMatch = preg_match( '/<\\/pre/i', $oLine );
			$preOpenMatch = preg_match( '/<pre/i', $oLine );
			# If not in a <pre> element, scan for and figure out what prefixes are there.
			if ( !$this->mInPre ) {
				# Multiple prefixes may abut each other for nested lists.
				$prefixLength = strspn( $oLine, '*#:;' );
				$prefix = substr( $oLine, 0, $prefixLength );

				# eh?
				# ; and : are both from definition-lists, so they're equivalent
				#  for the purposes of determining whether or not we need to open/close
				#  elements.
				$prefix2 = str_replace( ';', ':', $prefix );
				$t = substr( $oLine, $prefixLength );
				$this->mInPre = (bool)$preOpenMatch;
			} else {
				# Don't interpret any other prefixes in preformatted text
				$prefixLength = 0;
				$prefix = $prefix2 = '';
				$t = $oLine;
			}

			# List generation
			if ( $prefixLength && $lastPrefix === $prefix2 ) {
				# Same as the last item, so no need to deal with nesting or opening stuff
				$output .= $this->nextItem( substr( $prefix, -1 ) );
				$paragraphStack = false;

				if ( substr( $prefix, -1 ) === ';' ) {
					# The one nasty exception: definition lists work like this:
					# ; title : definition text
					# So we check for : in the remainder text to split up the
					# title and definition, without b0rking links.
					$term = $t2 = '';
					if ( $this->findColonNoLinks( $t, $term, $t2 ) !== false ) {
						$t = $t2;
						$output .= $term . $this->nextItem( ':' );
					}
				}
			} elseif ( $prefixLength || $lastPrefixLength ) {
				# We need to open or close prefixes, or both.

				# Either open or close a level...
				$commonPrefixLength = $this->getCommon( $prefix, $lastPrefix );
				$paragraphStack = false;

				# Close all the prefixes which aren't shared.
				while ( $commonPrefixLength < $lastPrefixLength ) {
					$output .= $this->closeList( $lastPrefix[$lastPrefixLength - 1] );
					--$lastPrefixLength;
				}

				# Continue the current prefix if appropriate.
				if ( $prefixLength <= $commonPrefixLength && $commonPrefixLength > 0 ) {
					$output .= $this->nextItem( $prefix[$commonPrefixLength - 1] );
				}

				# Open prefixes where appropriate.
				while ( $prefixLength > $commonPrefixLength ) {
					$char = substr( $prefix, $commonPrefixLength, 1 );
					$output .= $this->openList( $char );

					if ( ';' === $char ) {
						# @todo FIXME: This is dupe of code above
						if ( $this->findColonNoLinks( $t, $term, $t2 ) !== false ) {
							$t = $t2;
							$output .= $term . $this->nextItem( ':' );
						}
					}
					++$commonPrefixLength;
				}
				$lastPrefix = $prefix2;
			}

			# If we have no prefixes, go to paragraph mode.
			if ( 0 == $prefixLength ) {
				wfProfileIn( __METHOD__ . "-paragraph" );
				# No prefix (not in list)--go to paragraph mode
				# XXX: use a stack for nestable elements like span, table and div
				$openmatch = preg_match( '/(?:<table|<h1|<h2|<h3|<h4|<h5|<h6|<pre|<tr|<p|<ul|<ol|<dl|<li|<\\/tr|<\\/td|<\\/th)/iS', $t );
				$closematch = preg_match(
					'/(?:<\\/table|<\\/h1|<\\/h2|<\\/h3|<\\/h4|<\\/h5|<\\/h6|' .
					'<td|<th|<\\/?blockquote|<\\/?div|<hr|<\\/pre|<\\/p|<\\/mw:|' . $this->mUniqPrefix . '-pre|<\\/li|<\\/ul|<\\/ol|<\\/dl|<\\/?center)/iS', $t );
				if ( $openmatch or $closematch ) {
					$paragraphStack = false;
					# TODO bug 5718: paragraph closed
					$output .= $this->closeParagraph();
					if ( $preOpenMatch and !$preCloseMatch ) {
						$this->mInPre = true;
					}
					$bqOffset = 0;
					while ( preg_match( '/<(\\/?)blockquote[\s>]/i', $t, $bqMatch, PREG_OFFSET_CAPTURE, $bqOffset ) ) {
						$inBlockquote = !$bqMatch[1][0]; // is this a close tag?
						$bqOffset = $bqMatch[0][1] + strlen( $bqMatch[0][0] );
					}
					$inBlockElem = !$closematch;
				} elseif ( !$inBlockElem && !$this->mInPre ) {
					if ( ' ' == substr( $t, 0, 1 ) and ( $this->mLastSection === 'pre' || trim( $t ) != '' ) and !$inBlockquote ) {
						# pre
						if ( $this->mLastSection !== 'pre' ) {
							$paragraphStack = false;
							$output .= $this->closeParagraph() . '<pre>';
							$this->mLastSection = 'pre';
						}
						$t = substr( $t, 1 );
					} else {
						# paragraph
						if ( trim( $t ) === '' ) {
							if ( $paragraphStack ) {
								$output .= $paragraphStack . '<br />';
								$paragraphStack = false;
								$this->mLastSection = 'p';
							} else {
								if ( $this->mLastSection !== 'p' ) {
									$output .= $this->closeParagraph();
									$this->mLastSection = '';
									$paragraphStack = '<p>';
								} else {
									$paragraphStack = '</p><p>';
								}
							}
						} else {
							if ( $paragraphStack ) {
								$output .= $paragraphStack;
								$paragraphStack = false;
								$this->mLastSection = 'p';
							} elseif ( $this->mLastSection !== 'p' ) {
								$output .= $this->closeParagraph() . '<p>';
								$this->mLastSection = 'p';
							}
						}
					}
				}
				wfProfileOut( __METHOD__ . "-paragraph" );
			}
			# somewhere above we forget to get out of pre block (bug 785)
			if ( $preCloseMatch && $this->mInPre ) {
				$this->mInPre = false;
			}
			if ( $paragraphStack === false ) {
				$output .= $t . "\n";
			}
		}
		while ( $prefixLength ) {
			$output .= $this->closeList( $prefix2[$prefixLength - 1] );
			--$prefixLength;
		}
		if ( $this->mLastSection != '' ) {
			$output .= '</' . $this->mLastSection . '>';
			$this->mLastSection = '';
		}

		wfProfileOut( __METHOD__ );
		return $output;
	}

	/**
	 * Split up a string on ':', ignoring any occurrences inside tags
	 * to prevent illegal overlapping.
	 *
	 * @param string $str the string to split
	 * @param &$before String set to everything before the ':'
	 * @param &$after String set to everything after the ':'
	 * @throws MWException
	 * @return String the position of the ':', or false if none found
	 */
	function findColonNoLinks( $str, &$before, &$after ) {
		wfProfileIn( __METHOD__ );

		$pos = strpos( $str, ':' );
		if ( $pos === false ) {
			# Nothing to find!
			wfProfileOut( __METHOD__ );
			return false;
		}

		$lt = strpos( $str, '<' );
		if ( $lt === false || $lt > $pos ) {
			# Easy; no tag nesting to worry about
			$before = substr( $str, 0, $pos );
			$after = substr( $str, $pos + 1 );
			wfProfileOut( __METHOD__ );
			return $pos;
		}

		# Ugly state machine to walk through avoiding tags.
		$state = self::COLON_STATE_TEXT;
		$stack = 0;
		$len = strlen( $str );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $str[$i];

			switch ( $state ) {
			# (Using the number is a performance hack for common cases)
			case 0: # self::COLON_STATE_TEXT:
				switch ( $c ) {
				case "<":
					# Could be either a <start> tag or an </end> tag
					$state = self::COLON_STATE_TAGSTART;
					break;
				case ":":
					if ( $stack == 0 ) {
						# We found it!
						$before = substr( $str, 0, $i );
						$after = substr( $str, $i + 1 );
						wfProfileOut( __METHOD__ );
						return $i;
					}
					# Embedded in a tag; don't break it.
					break;
				default:
					# Skip ahead looking for something interesting
					$colon = strpos( $str, ':', $i );
					if ( $colon === false ) {
						# Nothing else interesting
						wfProfileOut( __METHOD__ );
						return false;
					}
					$lt = strpos( $str, '<', $i );
					if ( $stack === 0 ) {
						if ( $lt === false || $colon < $lt ) {
							# We found it!
							$before = substr( $str, 0, $colon );
							$after = substr( $str, $colon + 1 );
							wfProfileOut( __METHOD__ );
							return $i;
						}
					}
					if ( $lt === false ) {
						# Nothing else interesting to find; abort!
						# We're nested, but there's no close tags left. Abort!
						break 2;
					}
					# Skip ahead to next tag start
					$i = $lt;
					$state = self::COLON_STATE_TAGSTART;
				}
				break;
			case 1: # self::COLON_STATE_TAG:
				# In a <tag>
				switch ( $c ) {
				case ">":
					$stack++;
					$state = self::COLON_STATE_TEXT;
					break;
				case "/":
					# Slash may be followed by >?
					$state = self::COLON_STATE_TAGSLASH;
					break;
				default:
					# ignore
				}
				break;
			case 2: # self::COLON_STATE_TAGSTART:
				switch ( $c ) {
				case "/":
					$state = self::COLON_STATE_CLOSETAG;
					break;
				case "!":
					$state = self::COLON_STATE_COMMENT;
					break;
				case ">":
					# Illegal early close? This shouldn't happen D:
					$state = self::COLON_STATE_TEXT;
					break;
				default:
					$state = self::COLON_STATE_TAG;
				}
				break;
			case 3: # self::COLON_STATE_CLOSETAG:
				# In a </tag>
				if ( $c === ">" ) {
					$stack--;
					if ( $stack < 0 ) {
						wfDebug( __METHOD__ . ": Invalid input; too many close tags\n" );
						wfProfileOut( __METHOD__ );
						return false;
					}
					$state = self::COLON_STATE_TEXT;
				}
				break;
			case self::COLON_STATE_TAGSLASH:
				if ( $c === ">" ) {
					# Yes, a self-closed tag <blah/>
					$state = self::COLON_STATE_TEXT;
				} else {
					# Probably we're jumping the gun, and this is an attribute
					$state = self::COLON_STATE_TAG;
				}
				break;
			case 5: # self::COLON_STATE_COMMENT:
				if ( $c === "-" ) {
					$state = self::COLON_STATE_COMMENTDASH;
				}
				break;
			case self::COLON_STATE_COMMENTDASH:
				if ( $c === "-" ) {
					$state = self::COLON_STATE_COMMENTDASHDASH;
				} else {
					$state = self::COLON_STATE_COMMENT;
				}
				break;
			case self::COLON_STATE_COMMENTDASHDASH:
				if ( $c === ">" ) {
					$state = self::COLON_STATE_TEXT;
				} else {
					$state = self::COLON_STATE_COMMENT;
				}
				break;
			default:
				wfProfileOut( __METHOD__ );
				throw new MWException( "State machine error in " . __METHOD__ );
			}
		}
		if ( $stack > 0 ) {
			wfDebug( __METHOD__ . ": Invalid input; not enough close tags (stack $stack, state $state)\n" );
			wfProfileOut( __METHOD__ );
			return false;
		}
		wfProfileOut( __METHOD__ );
		return false;
	}

	/**
	 * Return value of a magic variable (like PAGENAME)
	 *
	 * @private
	 *
	 * @param $index integer
	 * @param bool|\PPFrame $frame
	 *
	 * @throws MWException
	 * @return string
	 */
	function getVariableValue( $index, $frame = false ) {
		global $wgContLang, $wgSitename, $wgServer;
		global $wgArticlePath, $wgScriptPath, $wgStylePath;

		if ( is_null( $this->mTitle ) ) {
			// If no title set, bad things are going to happen
			// later. Title should always be set since this
			// should only be called in the middle of a parse
			// operation (but the unit-tests do funky stuff)
			throw new MWException( __METHOD__ . ' Should only be '
				. ' called while parsing (no title set)' );
		}

		/**
		 * Some of these require message or data lookups and can be
		 * expensive to check many times.
		 */
		if ( wfRunHooks( 'ParserGetVariableValueVarCache', array( &$this, &$this->mVarCache ) ) ) {
			if ( isset( $this->mVarCache[$index] ) ) {
				return $this->mVarCache[$index];
			}
		}

		$ts = wfTimestamp( TS_UNIX, $this->mOptions->getTimestamp() );
		wfRunHooks( 'ParserGetVariableValueTs', array( &$this, &$ts ) );

		$pageLang = $this->getFunctionLang();

		switch ( $index ) {
			case 'currentmonth':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'm' ) );
				break;
			case 'currentmonth1':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'n' ) );
				break;
			case 'currentmonthname':
				$value = $pageLang->getMonthName( MWTimestamp::getInstance( $ts )->format( 'n' ) );
				break;
			case 'currentmonthnamegen':
				$value = $pageLang->getMonthNameGen( MWTimestamp::getInstance( $ts )->format( 'n' ) );
				break;
			case 'currentmonthabbrev':
				$value = $pageLang->getMonthAbbreviation( MWTimestamp::getInstance( $ts )->format( 'n' ) );
				break;
			case 'currentday':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'j' ) );
				break;
			case 'currentday2':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'd' ) );
				break;
			case 'localmonth':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'm' ) );
				break;
			case 'localmonth1':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'n' ) );
				break;
			case 'localmonthname':
				$value = $pageLang->getMonthName( MWTimestamp::getLocalInstance( $ts )->format( 'n' ) );
				break;
			case 'localmonthnamegen':
				$value = $pageLang->getMonthNameGen( MWTimestamp::getLocalInstance( $ts )->format( 'n' ) );
				break;
			case 'localmonthabbrev':
				$value = $pageLang->getMonthAbbreviation( MWTimestamp::getLocalInstance( $ts )->format( 'n' ) );
				break;
			case 'localday':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'j' ) );
				break;
			case 'localday2':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'd' ) );
				break;
			case 'pagename':
				$value = wfEscapeWikiText( $this->mTitle->getText() );
				break;
			case 'pagenamee':
				$value = wfEscapeWikiText( $this->mTitle->getPartialURL() );
				break;
			case 'fullpagename':
				$value = wfEscapeWikiText( $this->mTitle->getPrefixedText() );
				break;
			case 'fullpagenamee':
				$value = wfEscapeWikiText( $this->mTitle->getPrefixedURL() );
				break;
			case 'subpagename':
				$value = wfEscapeWikiText( $this->mTitle->getSubpageText() );
				break;
			case 'subpagenamee':
				$value = wfEscapeWikiText( $this->mTitle->getSubpageUrlForm() );
				break;
			case 'rootpagename':
				$value = wfEscapeWikiText( $this->mTitle->getRootText() );
				break;
			case 'rootpagenamee':
				$value = wfEscapeWikiText( wfUrlEncode( str_replace( ' ', '_', $this->mTitle->getRootText() ) ) );
				break;
			case 'basepagename':
				$value = wfEscapeWikiText( $this->mTitle->getBaseText() );
				break;
			case 'basepagenamee':
				$value = wfEscapeWikiText( wfUrlEncode( str_replace( ' ', '-', $this->mTitle->getBaseText() ) ) );
				break;
			case 'talkpagename':
				if ( $this->mTitle->canTalk() ) {
					$talkPage = $this->mTitle->getTalkPage();
					$value = wfEscapeWikiText( $talkPage->getPrefixedText() );
				} else {
					$value = '';
				}
				break;
			case 'talkpagenamee':
				if ( $this->mTitle->canTalk() ) {
					$talkPage = $this->mTitle->getTalkPage();
					$value = wfEscapeWikiText( $talkPage->getPrefixedURL() );
				} else {
					$value = '';
				}
				break;
			case 'subjectpagename':
				$subjPage = $this->mTitle->getSubjectPage();
				$value = wfEscapeWikiText( $subjPage->getPrefixedText() );
				break;
			case 'subjectpagenamee':
				$subjPage = $this->mTitle->getSubjectPage();
				$value = wfEscapeWikiText( $subjPage->getPrefixedURL() );
				break;
			case 'pageid': // requested in bug 23427
				$pageid = $this->getTitle()->getArticleID();
				if ( $pageid == 0 ) {
					# 0 means the page doesn't exist in the database,
					# which means the user is previewing a new page.
					# The vary-revision flag must be set, because the magic word
					# will have a different value once the page is saved.
					$this->mOutput->setFlag( 'vary-revision' );
					wfDebug( __METHOD__ . ": {{PAGEID}} used in a new page, setting vary-revision...\n" );
				}
				$value = $pageid ? $pageid : null;
				break;
			case 'revisionid':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONID}} used, setting vary-revision...\n" );
				$value = $this->mRevisionId;
				break;
			case 'revisionday':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONDAY}} used, setting vary-revision...\n" );
				$value = intval( substr( $this->getRevisionTimestamp(), 6, 2 ) );
				break;
			case 'revisionday2':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONDAY2}} used, setting vary-revision...\n" );
				$value = substr( $this->getRevisionTimestamp(), 6, 2 );
				break;
			case 'revisionmonth':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONMONTH}} used, setting vary-revision...\n" );
				$value = substr( $this->getRevisionTimestamp(), 4, 2 );
				break;
			case 'revisionmonth1':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONMONTH1}} used, setting vary-revision...\n" );
				$value = intval( substr( $this->getRevisionTimestamp(), 4, 2 ) );
				break;
			case 'revisionyear':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONYEAR}} used, setting vary-revision...\n" );
				$value = substr( $this->getRevisionTimestamp(), 0, 4 );
				break;
			case 'revisiontimestamp':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONTIMESTAMP}} used, setting vary-revision...\n" );
				$value = $this->getRevisionTimestamp();
				break;
			case 'revisionuser':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONUSER}} used, setting vary-revision...\n" );
				$value = $this->getRevisionUser();
				break;
			case 'revisionsize':
				# Let the edit saving system know we should parse the page
				# *after* a revision ID has been assigned. This is for null edits.
				$this->mOutput->setFlag( 'vary-revision' );
				wfDebug( __METHOD__ . ": {{REVISIONSIZE}} used, setting vary-revision...\n" );
				$value = $this->getRevisionSize();
				break;
			case 'namespace':
				$value = str_replace( '-', ' ', $wgContLang->getNsText( $this->mTitle->getNamespace() ) );
				break;
			case 'namespacee':
				$value = wfUrlencode( $wgContLang->getNsText( $this->mTitle->getNamespace() ) );
				break;
			case 'namespacenumber':
				$value = $this->mTitle->getNamespace();
				break;
			case 'talkspace':
				$value = $this->mTitle->canTalk() ? str_replace( '-', ' ', $this->mTitle->getTalkNsText() ) : '';
				break;
			case 'talkspacee':
				$value = $this->mTitle->canTalk() ? wfUrlencode( $this->mTitle->getTalkNsText() ) : '';
				break;
			case 'subjectspace':
				$value = str_replace( '_', ' ', $this->mTitle->getSubjectNsText() );
				break;
			case 'subjectspacee':
				$value = ( wfUrlencode( $this->mTitle->getSubjectNsText() ) );
				break;
			case 'currentdayname':
				$value = $pageLang->getWeekdayName( (int)MWTimestamp::getInstance( $ts )->format( 'w' ) + 1 );
				break;
			case 'currentyear':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'Y' ), true );
				break;
			case 'currenttime':
				$value = $pageLang->time( wfTimestamp( TS_MW, $ts ), false, false );
				break;
			case 'currenthour':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'H' ), true );
				break;
			case 'currentweek':
				# @bug 4594 PHP5 has it zero padded, PHP4 does not, cast to
				# int to remove the padding
				$value = $pageLang->formatNum( (int)MWTimestamp::getInstance( $ts )->format( 'W' ) );
				break;
			case 'currentdow':
				$value = $pageLang->formatNum( MWTimestamp::getInstance( $ts )->format( 'w' ) );
				break;
			case 'localdayname':
				$value = $pageLang->getWeekdayName( (int)MWTimestamp::getLocalInstance( $ts )->format( 'w' ) + 1 );
				break;
			case 'localyear':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'Y' ), true );
				break;
			case 'localtime':
				$value = $pageLang->time( MWTimestamp::getLocalInstance( $ts )->format( 'YmdHis' ), false, false );
				break;
			case 'localhour':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'H' ), true );
				break;
			case 'localweek':
				# @bug 4594 PHP5 has it zero padded, PHP4 does not, cast to
				# int to remove the padding
				$value = $pageLang->formatNum( (int)MWTimestamp::getLocalInstance( $ts )->format( 'W' ) );
				break;
			case 'localdow':
				$value = $pageLang->formatNum( MWTimestamp::getLocalInstance( $ts )->format( 'w' ) );
				break;
			case 'numberofarticles':
				$value = $pageLang->formatNum( SiteStats::articles() );
				break;
			case 'numberoffiles':
				$value = $pageLang->formatNum( SiteStats::images() );
				break;
			case 'numberofusers':
				$value = $pageLang->formatNum( SiteStats::users() );
				break;
			case 'numberofactiveusers':
				$value = $pageLang->formatNum( SiteStats::activeUsers() );
				break;
			case 'numberofpages':
				$value = $pageLang->formatNum( SiteStats::pages() );
				break;
			case 'numberofadmins':
				$value = $pageLang->formatNum( SiteStats::numberingroup( 'sysop' ) );
				break;
			case 'numberofedits':
				$value = $pageLang->formatNum( SiteStats::edits() );
				break;
			case 'numberofviews':
				global $wgDisableCounters;
				$value = !$wgDisableCounters ? $pageLang->formatNum( SiteStats::views() ) : '';
				break;
			case 'currenttimestamp':
				$value = wfTimestamp( TS_MW, $ts );
				break;
			case 'localtimestamp':
				$value = MWTimestamp::getLocalInstance( $ts )->format( 'YmdHis' );
				break;
			case 'currentversion':
				$value = SpecialVersion::getVersion();
				break;
			case 'articlepath':
				return $wgArticlePath;
			case 'sitename':
				return $wgSitename;
			case 'server':
				return $wgServer;
			case 'servername':
				$serverParts = wfParseUrl( $wgServer );
				return $serverParts && isset( $serverParts['host'] ) ? $serverParts['host'] : $wgServer;
			case 'scriptpath':
				return $wgScriptPath;
			case 'stylepath':
				return $wgStylePath;
			case 'directionmark':
				return $pageLang->getDirMark();
			case 'contentlanguage':
				global $wgLanguageCode;
				return $wgLanguageCode;
			case 'cascadingsources':
				$value = CoreParserFunctions::cascadingsources( $this );
				break;
			default:
				$ret = null;
				wfRunHooks( 'ParserGetVariableValueSwitch', array( &$this, &$this->mVarCache, &$index, &$ret, &$frame ) );
				return $ret;
		}

		if ( $index ) {
			$this->mVarCache[$index] = $value;
		}

		return $value;
	}

	/**
	 * initialise the magic variables (like CURRENTMONTHNAME) and substitution modifiers
	 *
	 * @private
	 */
	function initialiseVariables() {
		wfProfileIn( __METHOD__ );
		$variableIDs = MagicWord::getVariableIDs();
		$substIDs = MagicWord::getSubstIDs();

		$this->mVariables = new MagicWordArray( $variableIDs );
		$this->mSubstWords = new MagicWordArray( $substIDs );
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Preprocess some wikitext and return the document tree.
	 * This is the ghost of replace_variables().
	 *
	 * @param string $text The text to parse
	 * @param $flags Integer: bitwise combination of:
	 *          self::PTD_FOR_INCLUSION    Handle "<noinclude>" and "<includeonly>" as if the text is being
	 *                                     included. Default is to assume a direct page view.
	 *
	 * The generated DOM tree must depend only on the input text and the flags.
	 * The DOM tree must be the same in OT_HTML and OT_WIKI mode, to avoid a regression of bug 4899.
	 *
	 * Any flag added to the $flags parameter here, or any other parameter liable to cause a
	 * change in the DOM tree for a given text, must be passed through the section identifier
	 * in the section edit link and thus back to extractSections().
	 *
	 * The output of this function is currently only cached in process memory, but a persistent
	 * cache may be implemented at a later date which takes further advantage of these strict
	 * dependency requirements.
	 *
	 * @return PPNode
	 */
	function preprocessToDom( $text, $flags = 0 ) {
		$dom = $this->getPreprocessor()->preprocessToObj( $text, $flags );
		return $dom;
	}

	/**
	 * Return a three-element array: leading whitespace, string contents, trailing whitespace
	 *
	 * @param $s string
	 *
	 * @return array
	 */
	public static function splitWhitespace( $s ) {
		$ltrimmed = ltrim( $s );
		$w1 = substr( $s, 0, strlen( $s ) - strlen( $ltrimmed ) );
		$trimmed = rtrim( $ltrimmed );
		$diff = strlen( $ltrimmed ) - strlen( $trimmed );
		if ( $diff > 0 ) {
			$w2 = substr( $ltrimmed, -$diff );
		} else {
			$w2 = '';
		}
		return array( $w1, $trimmed, $w2 );
	}

	/**
	 * Replace magic variables, templates, and template arguments
	 * with the appropriate text. Templates are substituted recursively,
	 * taking care to avoid infinite loops.
	 *
	 * Note that the substitution depends on value of $mOutputType:
	 *  self::OT_WIKI: only {{subst:}} templates
	 *  self::OT_PREPROCESS: templates but not extension tags
	 *  self::OT_HTML: all templates and extension tags
	 *
	 * @param string $text the text to transform
	 * @param $frame PPFrame Object describing the arguments passed to the template.
	 *        Arguments may also be provided as an associative array, as was the usual case before MW1.12.
	 *        Providing arguments this way may be useful for extensions wishing to perform variable replacement explicitly.
	 * @param $argsOnly Boolean only do argument (triple-brace) expansion, not double-brace expansion
	 * @private
	 *
	 * @return string
	 */
	function replaceVariables( $text, $frame = false, $argsOnly = false ) {
		# Is there any text? Also, Prevent too big inclusions!
		if ( strlen( $text ) < 1 || strlen( $text ) > $this->mOptions->getMaxIncludeSize() ) {
			return $text;
		}
		wfProfileIn( __METHOD__ );

		if ( $frame === false ) {
			$frame = $this->getPreprocessor()->newFrame();
		} elseif ( !( $frame instanceof PPFrame ) ) {
			wfDebug( __METHOD__ . " called using plain parameters instead of a PPFrame instance. Creating custom frame.\n" );
			$frame = $this->getPreprocessor()->newCustomFrame( $frame );
		}

		$dom = $this->preprocessToDom( $text );
		$flags = $argsOnly ? PPFrame::NO_TEMPLATES : 0;
		$text = $frame->expand( $dom, $flags );

		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Clean up argument array - refactored in 1.9 so parserfunctions can use it, too.
	 *
	 * @param $args array
	 *
	 * @return array
	 */
	static function createAssocArgs( $args ) {
		$assocArgs = array();
		$index = 1;
		foreach ( $args as $arg ) {
			$eqpos = strpos( $arg, '=' );
			if ( $eqpos === false ) {
				$assocArgs[$index++] = $arg;
			} else {
				$name = trim( substr( $arg, 0, $eqpos ) );
				$value = trim( substr( $arg, $eqpos + 1 ) );
				if ( $value === false ) {
					$value = '';
				}
				if ( $name !== false ) {
					$assocArgs[$name] = $value;
				}
			}
		}

		return $assocArgs;
	}

	/**
	 * Warn the user when a parser limitation is reached
	 * Will warn at most once the user per limitation type
	 *
	 * @param string $limitationType should be one of:
	 *   'expensive-parserfunction' (corresponding messages:
	 *       'expensive-parserfunction-warning',
	 *       'expensive-parserfunction-category')
	 *   'post-expand-template-argument' (corresponding messages:
	 *       'post-expand-template-argument-warning',
	 *       'post-expand-template-argument-category')
	 *   'post-expand-template-inclusion' (corresponding messages:
	 *       'post-expand-template-inclusion-warning',
	 *       'post-expand-template-inclusion-category')
	 *   'node-count-exceeded' (corresponding messages:
	 *       'node-count-exceeded-warning',
	 *       'node-count-exceeded-category')
	 *   'expansion-depth-exceeded' (corresponding messages:
	 *       'expansion-depth-exceeded-warning',
	 *       'expansion-depth-exceeded-category')
	 * @param int|null $current Current value
	 * @param int|null $max Maximum allowed, when an explicit limit has been
	 *	 exceeded, provide the values (optional)
	 */
	function limitationWarn( $limitationType, $current = '', $max = '' ) {
		# does no harm if $current and $max are present but are unnecessary for the message
		$warning = wfMessage( "$limitationType-warning" )->numParams( $current, $max )
			->inLanguage( $this->mOptions->getUserLangObj() )->text();
		$this->mOutput->addWarning( $warning );
		$this->addTrackingCategory( "$limitationType-category" );
	}

	/**
	 * Return the text of a template, after recursively
	 * replacing any variables or templates within the template.
	 *
	 * @param array $piece the parts of the template
	 *  $piece['title']: the title, i.e. the part before the |
	 *  $piece['parts']: the parameter array
	 *  $piece['lineStart']: whether the brace was at the start of a line
	 * @param $frame PPFrame The current frame, contains template arguments
	 * @throws MWException
	 * @return String: the text of the template
	 * @private
	 */
	function braceSubstitution( $piece, $frame ) {
		wfProfileIn( __METHOD__ );
		wfProfileIn( __METHOD__ . '-setup' );

		# Flags
		$found = false;             # $text has been filled
		$nowiki = false;            # wiki markup in $text should be escaped
		$isHTML = false;            # $text is HTML, armour it against wikitext transformation
		$forceRawInterwiki = false; # Force interwiki transclusion to be done in raw mode not rendered
		$isChildObj = false;        # $text is a DOM node needing expansion in a child frame
		$isLocalObj = false;        # $text is a DOM node needing expansion in the current frame

		# Title object, where $text came from
		$title = false;

		# $part1 is the bit before the first |, and must contain only title characters.
		# Various prefixes will be stripped from it later.
		$titleWithSpaces = $frame->expand( $piece['title'] );
		$part1 = trim( $titleWithSpaces );
		$titleText = false;

		# Original title text preserved for various purposes
		$originalTitle = $part1;

		# $args is a list of argument nodes, starting from index 0, not including $part1
		# @todo FIXME: If piece['parts'] is null then the call to getLength() below won't work b/c this $args isn't an object
		$args = ( null == $piece['parts'] ) ? array() : $piece['parts'];
		wfProfileOut( __METHOD__ . '-setup' );

		$titleProfileIn = null; // profile templates

		# SUBST
		wfProfileIn( __METHOD__ . '-modifiers' );
		if ( !$found ) {

			$substMatch = $this->mSubstWords->matchStartAndRemove( $part1 );

			# Possibilities for substMatch: "subst", "safesubst" or FALSE
			# Decide whether to expand template or keep wikitext as-is.
			if ( $this->ot['wiki'] ) {
				if ( $substMatch === false ) {
					$literal = true;  # literal when in PST with no prefix
				} else {
					$literal = false; # expand when in PST with subst: or safesubst:
				}
			} else {
				if ( $substMatch == 'subst' ) {
					$literal = true;  # literal when not in PST with plain subst:
				} else {
					$literal = false; # expand when not in PST with safesubst: or no prefix
				}
			}
			if ( $literal ) {
				$text = $frame->virtualBracketedImplode( '{{', '|', '}}', $titleWithSpaces, $args );
				$isLocalObj = true;
				$found = true;
			}
		}

		# Variables
		if ( !$found && $args->getLength() == 0 ) {
			$id = $this->mVariables->matchStartToEnd( $part1 );
			if ( $id !== false ) {
				$text = $this->getVariableValue( $id, $frame );
				if ( MagicWord::getCacheTTL( $id ) > -1 ) {
					$this->mOutput->updateCacheExpiry( MagicWord::getCacheTTL( $id ) );
				}
				$found = true;
			}
		}

		# MSG, MSGNW and RAW
		if ( !$found ) {
			# Check for MSGNW:
			$mwMsgnw = MagicWord::get( 'msgnw' );
			if ( $mwMsgnw->matchStartAndRemove( $part1 ) ) {
				$nowiki = true;
			} else {
				# Remove obsolete MSG:
				$mwMsg = MagicWord::get( 'msg' );
				$mwMsg->matchStartAndRemove( $part1 );
			}

			# Check for RAW:
			$mwRaw = MagicWord::get( 'raw' );
			if ( $mwRaw->matchStartAndRemove( $part1 ) ) {
				$forceRawInterwiki = true;
			}
		}
		wfProfileOut( __METHOD__ . '-modifiers' );

		# Parser functions
		if ( !$found ) {
			wfProfileIn( __METHOD__ . '-pfunc' );

			$colonPos = strpos( $part1, ':' );
			if ( $colonPos !== false ) {
				$func = substr( $part1, 0, $colonPos );
				$funcArgs = array( trim( substr( $part1, $colonPos + 1 ) ) );
				for ( $i = 0; $i < $args->getLength(); $i++ ) {
					$funcArgs[] = $args->item( $i );
				}
				try {
					$result = $this->callParserFunction( $frame, $func, $funcArgs );
				} catch ( Exception $ex ) {
					wfProfileOut( __METHOD__ . '-pfunc' );
					wfProfileOut( __METHOD__ );
					throw $ex;
				}

				# The interface for parser functions allows for extracting
				# flags into the local scope. Extract any forwarded flags
				# here.
				extract( $result );
			}
			wfProfileOut( __METHOD__ . '-pfunc' );
		}

		# Finish mangling title and then check for loops.
		# Set $title to a Title object and $titleText to the PDBK
		if ( !$found ) {
			$ns = NS_TEMPLATE;
			# Split the title into page and subpage
			$subpage = '';
			$relative = $this->maybeDoSubpageLink( $part1, $subpage );
			if ( $part1 !== $relative ) {
				$part1 = $relative;
				$ns = $this->mTitle->getNamespace();
			}
			$title = Title::newFromText( $part1, $ns );
			if ( $title ) {
				$titleText = $title->getPrefixedText();
				# Check for language variants if the template is not found
				if ( $this->getConverterLanguage()->hasVariants() && $title->getArticleID() == 0 ) {
					$this->getConverterLanguage()->findVariantLink( $part1, $title, true );
				}
				# Do recursion depth check
				$limit = $this->mOptions->getMaxTemplateDepth();
				if ( $frame->depth >= $limit ) {
					$found = true;
					$text = '<span class="error">'
						. wfMessage( 'parser-template-recursion-depth-warning' )
							->numParams( $limit )->inContentLanguage()->text()
						. '</span>';
				}
			}
		}

		# Load from database
		if ( !$found && $title ) {
			if ( !Profiler::instance()->isPersistent() ) {
				# Too many unique items can kill profiling DBs/collectors
				$titleProfileIn = __METHOD__ . "-title-" . $title->getPrefixedDBkey();
				wfProfileIn( $titleProfileIn ); // template in
			}
			wfProfileIn( __METHOD__ . '-loadtpl' );
			if ( !$title->isExternal() ) {
				if ( $title->isSpecialPage()
					&& $this->mOptions->getAllowSpecialInclusion()
					&& $this->ot['html']
				) {
					// Pass the template arguments as URL parameters.
					// "uselang" will have no effect since the Language object
					// is forced to the one defined in ParserOptions.
					$pageArgs = array();
					for ( $i = 0; $i < $args->getLength(); $i++ ) {
						$bits = $args->item( $i )->splitArg();
						if ( strval( $bits['index'] ) === '' ) {
							$name = trim( $frame->expand( $bits['name'], PPFrame::STRIP_COMMENTS ) );
							$value = trim( $frame->expand( $bits['value'] ) );
							$pageArgs[$name] = $value;
						}
					}

					// Create a new context to execute the special page
					$context = new RequestContext;
					$context->setTitle( $title );
					$context->setRequest( new FauxRequest( $pageArgs ) );
					$context->setUser( $this->getUser() );
					$context->setLanguage( $this->mOptions->getUserLangObj() );
					$ret = SpecialPageFactory::capturePath( $title, $context );
					if ( $ret ) {
						$text = $context->getOutput()->getHTML();
						$this->mOutput->addOutputPageMetadata( $context->getOutput() );
						$found = true;
						$isHTML = true;
						$this->disableCache();
					}
				} elseif ( MWNamespace::isNonincludable( $title->getNamespace() ) ) {
					$found = false; # access denied
					wfDebug( __METHOD__ . ": template inclusion denied for " . $title->getPrefixedDBkey() );
				} else {
					list( $text, $title ) = $this->getTemplateDom( $title );
					if ( $text !== false ) {
						$found = true;
						$isChildObj = true;
					}
				}

				# If the title is valid but undisplayable, make a link to it
				if ( !$found && ( $this->ot['html'] || $this->ot['pre'] ) ) {
					$text = "[[:$titleText]]";
					$found = true;
				}
			} elseif ( $title->isTrans() ) {
				# Interwiki transclusion
				if ( $this->ot['html'] && !$forceRawInterwiki ) {
					$text = $this->interwikiTransclude( $title, 'render' );
					$isHTML = true;
				} else {
					$text = $this->interwikiTransclude( $title, 'raw' );
					# Preprocess it like a template
					$text = $this->preprocessToDom( $text, self::PTD_FOR_INCLUSION );
					$isChildObj = true;
				}
				$found = true;
			}

			# Do infinite loop check
			# This has to be done after redirect resolution to avoid infinite loops via redirects
			if ( !$frame->loopCheck( $title ) ) {
				$found = true;
				$text = '<span class="error">'
					. wfMessage( 'parser-template-loop-warning', $titleText )->inContentLanguage()->text()
					. '</span>';
				wfDebug( __METHOD__ . ": template loop broken at '$titleText'\n" );
			}
			wfProfileOut( __METHOD__ . '-loadtpl' );
		}

		# If we haven't found text to substitute by now, we're done
		# Recover the source wikitext and return it
		if ( !$found ) {
			$text = $frame->virtualBracketedImplode( '{{', '|', '}}', $titleWithSpaces, $args );
			if ( $titleProfileIn ) {
				wfProfileOut( $titleProfileIn ); // template out
			}
			wfProfileOut( __METHOD__ );
			return array( 'object' => $text );
		}

		# Expand DOM-style return values in a child frame
		if ( $isChildObj ) {
			# Clean up argument array
			$newFrame = $frame->newChild( $args, $title );

			if ( $nowiki ) {
				$text = $newFrame->expand( $text, PPFrame::RECOVER_ORIG );
			} elseif ( $titleText !== false && $newFrame->isEmpty() ) {
				# Expansion is eligible for the empty-frame cache
				if ( isset( $this->mTplExpandCache[$titleText] ) ) {
					$text = $this->mTplExpandCache[$titleText];
				} else {
					$text = $newFrame->expand( $text );
					$this->mTplExpandCache[$titleText] = $text;
				}
			} else {
				# Uncached expansion
				$text = $newFrame->expand( $text );
			}
		}
		if ( $isLocalObj && $nowiki ) {
			$text = $frame->expand( $text, PPFrame::RECOVER_ORIG );
			$isLocalObj = false;
		}

		if ( $titleProfileIn ) {
			wfProfileOut( $titleProfileIn ); // template out
		}

		# Replace raw HTML by a placeholder
		if ( $isHTML ) {
			$text = $this->insertStripItem( $text );
		} elseif ( $nowiki && ( $this->ot['html'] || $this->ot['pre'] ) ) {
			# Escape nowiki-style return values
			$text = wfEscapeWikiText( $text );
		} elseif ( is_string( $text )
			&& !$piece['lineStart']
			&& preg_match( '/^(?:{\\||:|;|#|\*)/', $text )
		) {
			# Bug 529: if the template begins with a table or block-level
			# element, it should be treated as beginning a new line.
			# This behavior is somewhat controversial.
			$text = "\n" . $text;
		}

		if ( is_string( $text ) && !$this->incrementIncludeSize( 'post-expand', strlen( $text ) ) ) {
			# Error, oversize inclusion
			if ( $titleText !== false ) {
				# Make a working, properly escaped link if possible (bug 23588)
				$text = "[[:$titleText]]";
			} else {
				# This will probably not be a working link, but at least it may
				# provide some hint of where the problem is
				preg_replace( '/^:/', '', $originalTitle );
				$text = "[[:$originalTitle]]";
			}
			$text .= $this->insertStripItem( '<!-- WARNING: template omitted, post-expand include size too large -->' );
			$this->limitationWarn( 'post-expand-template-inclusion' );
		}

		if ( $isLocalObj ) {
			$ret = array( 'object' => $text );
		} else {
			$ret = array( 'text' => $text );
		}

		wfProfileOut( __METHOD__ );
		return $ret;
	}

	/**
	 * Call a parser function and return an array with text and flags.
	 *
	 * The returned array will always contain a boolean 'found', indicating
	 * whether the parser function was found or not. It may also contain the
	 * following:
	 *  text: string|object, resulting wikitext or PP DOM object
	 *  isHTML: bool, $text is HTML, armour it against wikitext transformation
	 *  isChildObj: bool, $text is a DOM node needing expansion in a child frame
	 *  isLocalObj: bool, $text is a DOM node needing expansion in the current frame
	 *  nowiki: bool, wiki markup in $text should be escaped
	 *
	 * @since 1.21
	 * @param $frame PPFrame The current frame, contains template arguments
	 * @param $function string Function name
	 * @param $args array Arguments to the function
	 * @return array
	 */
	public function callParserFunction( $frame, $function, array $args = array() ) {
		global $wgContLang;

		wfProfileIn( __METHOD__ );

		# Case sensitive functions
		if ( isset( $this->mFunctionSynonyms[1][$function] ) ) {
			$function = $this->mFunctionSynonyms[1][$function];
		} else {
			# Case insensitive functions
			$function = $wgContLang->lc( $function );
			if ( isset( $this->mFunctionSynonyms[0][$function] ) ) {
				$function = $this->mFunctionSynonyms[0][$function];
			} else {
				wfProfileOut( __METHOD__ );
				return array( 'found' => false );
			}
		}

		wfProfileIn( __METHOD__ . '-pfunc-' . $function );
		list( $callback, $flags ) = $this->mFunctionHooks[$function];

		# Workaround for PHP bug 35229 and similar
		if ( !is_callable( $callback ) ) {
			wfProfileOut( __METHOD__ . '-pfunc-' . $function );
			wfProfileOut( __METHOD__ );
			throw new MWException( "Tag hook for $function is not callable\n" );
		}

		$allArgs = array( &$this );
		if ( $flags & SFH_OBJECT_ARGS ) {
			# Convert arguments to PPNodes and collect for appending to $allArgs
			$funcArgs = array();
			foreach ( $args as $k => $v ) {
				if ( $v instanceof PPNode || $k === 0 ) {
					$funcArgs[] = $v;
				} else {
					$funcArgs[] = $this->mPreprocessor->newPartNodeArray( array( $k => $v ) )->item( 0 );
				}
			}

			# Add a frame parameter, and pass the arguments as an array
			$allArgs[] = $frame;
			$allArgs[] = $funcArgs;
		} else {
			# Convert arguments to plain text and append to $allArgs
			foreach ( $args as $k => $v ) {
				if ( $v instanceof PPNode ) {
					$allArgs[] = trim( $frame->expand( $v ) );
				} elseif ( is_int( $k ) && $k >= 0 ) {
					$allArgs[] = trim( $v );
				} else {
					$allArgs[] = trim( "$k=$v" );
				}
			}
		}

		$result = call_user_func_array( $callback, $allArgs );

		# The interface for function hooks allows them to return a wikitext
		# string or an array containing the string and any flags. This mungs
		# things around to match what this method should return.
		if ( !is_array( $result ) ) {
			$result = array(
				'found' => true,
				'text' => $result,
			);
		} else {
			if ( isset( $result[0] ) && !isset( $result['text'] ) ) {
				$result['text'] = $result[0];
			}
			unset( $result[0] );
			$result += array(
				'found' => true,
			);
		}

		$noparse = true;
		$preprocessFlags = 0;
		if ( isset( $result['noparse'] ) ) {
			$noparse = $result['noparse'];
		}
		if ( isset( $result['preprocessFlags'] ) ) {
			$preprocessFlags = $result['preprocessFlags'];
		}

		if ( !$noparse ) {
			$result['text'] = $this->preprocessToDom( $result['text'], $preprocessFlags );
			$result['isChildObj'] = true;
		}
		wfProfileOut( __METHOD__ . '-pfunc-' . $function );
		wfProfileOut( __METHOD__ );

		return $result;
	}

	/**
	 * Get the semi-parsed DOM representation of a template with a given title,
	 * and its redirect destination title. Cached.
	 *
	 * @param $title Title
	 *
	 * @return array
	 */
	function getTemplateDom( $title ) {
		$cacheTitle = $title;
		$titleText = $title->getPrefixedDBkey();

		if ( isset( $this->mTplRedirCache[$titleText] ) ) {
			list( $ns, $dbk ) = $this->mTplRedirCache[$titleText];
			$title = Title::makeTitle( $ns, $dbk );
			$titleText = $title->getPrefixedDBkey();
		}
		if ( isset( $this->mTplDomCache[$titleText] ) ) {
			return array( $this->mTplDomCache[$titleText], $title );
		}

		# Cache miss, go to the database
		list( $text, $title ) = $this->fetchTemplateAndTitle( $title );

		if ( $text === false ) {
			$this->mTplDomCache[$titleText] = false;
			return array( false, $title );
		}

		$dom = $this->preprocessToDom( $text, self::PTD_FOR_INCLUSION );
		$this->mTplDomCache[$titleText] = $dom;

		if ( !$title->equals( $cacheTitle ) ) {
			$this->mTplRedirCache[$cacheTitle->getPrefixedDBkey()] =
				array( $title->getNamespace(), $cdb = $title->getDBkey() );
		}

		return array( $dom, $title );
	}

	/**
	 * Fetch the unparsed text of a template and register a reference to it.
	 * @param Title $title
	 * @return Array ( string or false, Title )
	 */
	function fetchTemplateAndTitle( $title ) {
		$templateCb = $this->mOptions->getTemplateCallback(); # Defaults to Parser::statelessFetchTemplate()
		$stuff = call_user_func( $templateCb, $title, $this );
		$text = $stuff['text'];
		$finalTitle = isset( $stuff['finalTitle'] ) ? $stuff['finalTitle'] : $title;
		if ( isset( $stuff['deps'] ) ) {
			foreach ( $stuff['deps'] as $dep ) {
				$this->mOutput->addTemplate( $dep['title'], $dep['page_id'], $dep['rev_id'] );
				if ( $dep['title']->equals( $this->getTitle() ) ) {
					// If we transclude ourselves, the final result
					// will change based on the new version of the page
					$this->mOutput->setFlag( 'vary-revision' );
				}
			}
		}
		return array( $text, $finalTitle );
	}

	/**
	 * Fetch the unparsed text of a template and register a reference to it.
	 * @param Title $title
	 * @return mixed string or false
	 */
	function fetchTemplate( $title ) {
		$rv = $this->fetchTemplateAndTitle( $title );
		return $rv[0];
	}

	/**
	 * Static function to get a template
	 * Can be overridden via ParserOptions::setTemplateCallback().
	 *
	 * @param $title  Title
	 * @param $parser Parser
	 *
	 * @return array
	 */
	static function statelessFetchTemplate( $title, $parser = false ) {
		$text = $skip = false;
		$finalTitle = $title;
		$deps = array();

		# Loop to fetch the article, with up to 1 redirect
		for ( $i = 0; $i < 2 && is_object( $title ); $i++ ) {
			# Give extensions a chance to select the revision instead
			$id = false; # Assume current
			wfRunHooks( 'BeforeParserFetchTemplateAndtitle',
				array( $parser, $title, &$skip, &$id ) );

			if ( $skip ) {
				$text = false;
				$deps[] = array(
					'title' => $title,
					'page_id' => $title->getArticleID(),
					'rev_id' => null
				);
				break;
			}
			# Get the revision
			$rev = $id
				? Revision::newFromId( $id )
				: Revision::newFromTitle( $title, false, Revision::READ_NORMAL );
			$rev_id = $rev ? $rev->getId() : 0;
			# If there is no current revision, there is no page
			if ( $id === false && !$rev ) {
				$linkCache = LinkCache::singleton();
				$linkCache->addBadLinkObj( $title );
			}

			$deps[] = array(
				'title' => $title,
				'page_id' => $title->getArticleID(),
				'rev_id' => $rev_id );
			if ( $rev && !$title->equals( $rev->getTitle() ) ) {
				# We fetched a rev from a different title; register it too...
				$deps[] = array(
					'title' => $rev->getTitle(),
					'page_id' => $rev->getPage(),
					'rev_id' => $rev_id );
			}

			if ( $rev ) {
				$content = $rev->getContent();
				$text = $content ? $content->getWikitextForTransclusion() : null;

				if ( $text === false || $text === null ) {
					$text = false;
					break;
				}
			} elseif ( $title->getNamespace() == NS_MEDIAWIKI ) {
				global $wgContLang;
				$message = wfMessage( $wgContLang->lcfirst( $title->getText() ) )->inContentLanguage();
				if ( !$message->exists() ) {
					$text = false;
					break;
				}
				$content = $message->content();
				$text = $message->plain();
			} else {
				break;
			}
			if ( !$content ) {
				break;
			}
			# Redirect?
			$finalTitle = $title;
			$title = $content->getRedirectTarget();
		}
		return array(
			'text' => $text,
			'finalTitle' => $finalTitle,
			'deps' => $deps );
	}

	/**
	 * Fetch a file and its title and register a reference to it.
	 * If 'broken' is a key in $options then the file will appear as a broken thumbnail.
	 * @param Title $title
	 * @param array $options Array of options to RepoGroup::findFile
	 * @return File|bool
	 */
	function fetchFile( $title, $options = array() ) {
		$res = $this->fetchFileAndTitle( $title, $options );
		return $res[0];
	}

	/**
	 * Fetch a file and its title and register a reference to it.
	 * If 'broken' is a key in $options then the file will appear as a broken thumbnail.
	 * @param Title $title
	 * @param array $options Array of options to RepoGroup::findFile
	 * @return Array ( File or false, Title of file )
	 */
	function fetchFileAndTitle( $title, $options = array() ) {
		$file = $this->fetchFileNoRegister( $title, $options );

		$time = $file ? $file->getTimestamp() : false;
		$sha1 = $file ? $file->getSha1() : false;
		# Register the file as a dependency...
		$this->mOutput->addImage( $title->getDBkey(), $time, $sha1 );
		if ( $file && !$title->equals( $file->getTitle() ) ) {
			# Update fetched file title
			$title = $file->getTitle();
			$this->mOutput->addImage( $title->getDBkey(), $time, $sha1 );
		}
		return array( $file, $title );
	}

	/**
	 * Helper function for fetchFileAndTitle.
	 *
	 * Also useful if you need to fetch a file but not use it yet,
	 * for example to get the file's handler.
	 *
	 * @param Title $title
	 * @param array $options Array of options to RepoGroup::findFile
	 * @return File or false
	 */
	protected function fetchFileNoRegister( $title, $options = array() ) {
		if ( isset( $options['broken'] ) ) {
			$file = false; // broken thumbnail forced by hook
		} elseif ( isset( $options['sha1'] ) ) { // get by (sha1,timestamp)
			$file = RepoGroup::singleton()->findFileFromKey( $options['sha1'], $options );
		} else { // get by (name,timestamp)
			$file = wfFindFile( $title, $options );
		}
		return $file;
	}

	/**
	 * Transclude an interwiki link.
	 *
	 * @param $title Title
	 * @param $action
	 *
	 * @return string
	 */
	function interwikiTransclude( $title, $action ) {
		global $wgEnableScaryTranscluding;

		if ( !$wgEnableScaryTranscluding ) {
			return wfMessage( 'scarytranscludedisabled' )->inContentLanguage()->text();
		}

		$url = $title->getFullURL( array( 'action' => $action ) );

		if ( strlen( $url ) > 255 ) {
			return wfMessage( 'scarytranscludetoolong' )->inContentLanguage()->text();
		}
		return $this->fetchScaryTemplateMaybeFromCache( $url );
	}

	/**
	 * @param $url string
	 * @return Mixed|String
	 */
	function fetchScaryTemplateMaybeFromCache( $url ) {
		global $wgTranscludeCacheExpiry;
		$dbr = wfGetDB( DB_SLAVE );
		$tsCond = $dbr->timestamp( time() - $wgTranscludeCacheExpiry );
		$obj = $dbr->selectRow( 'transcache', array( 'tc_time', 'tc_contents' ),
				array( 'tc_url' => $url, "tc_time >= " . $dbr->addQuotes( $tsCond ) ) );
		if ( $obj ) {
			return $obj->tc_contents;
		}

		$req = MWHttpRequest::factory( $url );
		$status = $req->execute(); // Status object
		if ( $status->isOK() ) {
			$text = $req->getContent();
		} elseif ( $req->getStatus() != 200 ) { // Though we failed to fetch the content, this status is useless.
			return wfMessage( 'scarytranscludefailed-httpstatus', $url, $req->getStatus() /* HTTP status */ )->inContentLanguage()->text();
		} else {
			return wfMessage( 'scarytranscludefailed', $url )->inContentLanguage()->text();
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->replace( 'transcache', array( 'tc_url' ), array(
			'tc_url' => $url,
			'tc_time' => $dbw->timestamp( time() ),
			'tc_contents' => $text
		) );
		return $text;
	}

	/**
	 * Triple brace replacement -- used for template arguments
	 * @private
	 *
	 * @param $piece array
	 * @param $frame PPFrame
	 *
	 * @return array
	 */
	function argSubstitution( $piece, $frame ) {
		wfProfileIn( __METHOD__ );

		$error = false;
		$parts = $piece['parts'];
		$nameWithSpaces = $frame->expand( $piece['title'] );
		$argName = trim( $nameWithSpaces );
		$object = false;
		$text = $frame->getArgument( $argName );
		if ( $text === false && $parts->getLength() > 0
			&& ( $this->ot['html']
				|| $this->ot['pre']
				|| ( $this->ot['wiki'] && $frame->isTemplate() )
			)
		) {
			# No match in frame, use the supplied default
			$object = $parts->item( 0 )->getChildren();
		}
		if ( !$this->incrementIncludeSize( 'arg', strlen( $text ) ) ) {
			$error = '<!-- WARNING: argument omitted, expansion size too large -->';
			$this->limitationWarn( 'post-expand-template-argument' );
		}

		if ( $text === false && $object === false ) {
			# No match anywhere
			$object = $frame->virtualBracketedImplode( '{{{', '|', '}}}', $nameWithSpaces, $parts );
		}
		if ( $error !== false ) {
			$text .= $error;
		}
		if ( $object !== false ) {
			$ret = array( 'object' => $object );
		} else {
			$ret = array( 'text' => $text );
		}

		wfProfileOut( __METHOD__ );
		return $ret;
	}

	/**
	 * Return the text to be used for a given extension tag.
	 * This is the ghost of strip().
	 *
	 * @param array $params Associative array of parameters:
	 *     name       PPNode for the tag name
	 *     attr       PPNode for unparsed text where tag attributes are thought to be
	 *     attributes Optional associative array of parsed attributes
	 *     inner      Contents of extension element
	 *     noClose    Original text did not have a close tag
	 * @param $frame PPFrame
	 *
	 * @throws MWException
	 * @return string
	 */
	function extensionSubstitution( $params, $frame ) {
		$name = $frame->expand( $params['name'] );
		$attrText = !isset( $params['attr'] ) ? null : $frame->expand( $params['attr'] );
		$content = !isset( $params['inner'] ) ? null : $frame->expand( $params['inner'] );
		$marker = "{$this->mUniqPrefix}-$name-" . sprintf( '%08X', $this->mMarkerIndex++ ) . self::MARKER_SUFFIX;

		$isFunctionTag = isset( $this->mFunctionTagHooks[strtolower( $name )] ) &&
			( $this->ot['html'] || $this->ot['pre'] );
		if ( $isFunctionTag ) {
			$markerType = 'none';
		} else {
			$markerType = 'general';
		}
		if ( $this->ot['html'] || $isFunctionTag ) {
			$name = strtolower( $name );
			$attributes = Sanitizer::decodeTagAttributes( $attrText );
			if ( isset( $params['attributes'] ) ) {
				$attributes = $attributes + $params['attributes'];
			}

			if ( isset( $this->mTagHooks[$name] ) ) {
				# Workaround for PHP bug 35229 and similar
				if ( !is_callable( $this->mTagHooks[$name] ) ) {
					throw new MWException( "Tag hook for $name is not callable\n" );
				}
				$output = call_user_func_array( $this->mTagHooks[$name],
					array( $content, $attributes, $this, $frame ) );
			} elseif ( isset( $this->mFunctionTagHooks[$name] ) ) {
				list( $callback, ) = $this->mFunctionTagHooks[$name];
				if ( !is_callable( $callback ) ) {
					throw new MWException( "Tag hook for $name is not callable\n" );
				}

				$output = call_user_func_array( $callback, array( &$this, $frame, $content, $attributes ) );
			} else {
				$output = '<span class="error">Invalid tag extension name: ' .
					htmlspecialchars( $name ) . '</span>';
			}

			if ( is_array( $output ) ) {
				# Extract flags to local scope (to override $markerType)
				$flags = $output;
				$output = $flags[0];
				unset( $flags[0] );
				extract( $flags );
			}
		} else {
			if ( is_null( $attrText ) ) {
				$attrText = '';
			}
			if ( isset( $params['attributes'] ) ) {
				foreach ( $params['attributes'] as $attrName => $attrValue ) {
					$attrText .= ' ' . htmlspecialchars( $attrName ) . '="' .
						htmlspecialchars( $attrValue ) . '"';
				}
			}
			if ( $content === null ) {
				$output = "<$name$attrText/>";
			} else {
				$close = is_null( $params['close'] ) ? '' : $frame->expand( $params['close'] );
				$output = "<$name$attrText>$content$close";
			}
		}

		if ( $markerType === 'none' ) {
			return $output;
		} elseif ( $markerType === 'nowiki' ) {
			$this->mStripState->addNoWiki( $marker, $output );
		} elseif ( $markerType === 'general' ) {
			$this->mStripState->addGeneral( $marker, $output );
		} else {
			throw new MWException( __METHOD__ . ': invalid marker type' );
		}
		return $marker;
	}

	/**
	 * Increment an include size counter
	 *
	 * @param string $type the type of expansion
	 * @param $size Integer: the size of the text
	 * @return Boolean: false if this inclusion would take it over the maximum, true otherwise
	 */
	function incrementIncludeSize( $type, $size ) {
		if ( $this->mIncludeSizes[$type] + $size > $this->mOptions->getMaxIncludeSize() ) {
			return false;
		} else {
			$this->mIncludeSizes[$type] += $size;
			return true;
		}
	}

	/**
	 * Increment the expensive function count
	 *
	 * @return Boolean: false if the limit has been exceeded
	 */
	function incrementExpensiveFunctionCount() {
		$this->mExpensiveFunctionCount++;
		return $this->mExpensiveFunctionCount <= $this->mOptions->getExpensiveParserFunctionLimit();
	}

	/**
	 * Strip double-underscore items like __NOGALLERY__ and __NOTOC__
	 * Fills $this->mDoubleUnderscores, returns the modified text
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	function doDoubleUnderscore( $text ) {
		wfProfileIn( __METHOD__ );

		# The position of __TOC__ needs to be recorded
		$mw = MagicWord::get( 'toc' );
		if ( $mw->match( $text ) ) {
			$this->mShowToc = true;
			$this->mForceTocPosition = true;

			# Set a placeholder. At the end we'll fill it in with the TOC.
			$text = $mw->replace( '<!--MWTOC-->', $text, 1 );

			# Only keep the first one.
			$text = $mw->replace( '', $text );
		}

		# Now match and remove the rest of them
		$mwa = MagicWord::getDoubleUnderscoreArray();
		$this->mDoubleUnderscores = $mwa->matchAndRemove( $text );

		if ( isset( $this->mDoubleUnderscores['nogallery'] ) ) {
			$this->mOutput->mNoGallery = true;
		}
		if ( isset( $this->mDoubleUnderscores['notoc'] ) && !$this->mForceTocPosition ) {
			$this->mShowToc = false;
		}
		if ( isset( $this->mDoubleUnderscores['hiddencat'] ) && $this->mTitle->getNamespace() == NS_CATEGORY ) {
			$this->addTrackingCategory( 'hidden-category-category' );
		}
		# (bug 8068) Allow control over whether robots index a page.
		#
		# @todo FIXME: Bug 14899: __INDEX__ always overrides __NOINDEX__ here!  This
		# is not desirable, the last one on the page should win.
		if ( isset( $this->mDoubleUnderscores['noindex'] ) && $this->mTitle->canUseNoindex() ) {
			$this->mOutput->setIndexPolicy( 'noindex' );
			$this->addTrackingCategory( 'noindex-category' );
		}
		if ( isset( $this->mDoubleUnderscores['index'] ) && $this->mTitle->canUseNoindex() ) {
			$this->mOutput->setIndexPolicy( 'index' );
			$this->addTrackingCategory( 'index-category' );
		}

		# Cache all double underscores in the database
		foreach ( $this->mDoubleUnderscores as $key => $val ) {
			$this->mOutput->setProperty( $key, '' );
		}

		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Add a tracking category, getting the title from a system message,
	 * or print a debug message if the title is invalid.
	 *
	 * @param string $msg message key
	 * @return Boolean: whether the addition was successful
	 */
	public function addTrackingCategory( $msg ) {
		if ( $this->mTitle->getNamespace() === NS_SPECIAL ) {
			wfDebug( __METHOD__ . ": Not adding tracking category $msg to special page!\n" );
			return false;
		}
		// Important to parse with correct title (bug 31469)
		$cat = wfMessage( $msg )
			->title( $this->getTitle() )
			->inContentLanguage()
			->text();

		# Allow tracking categories to be disabled by setting them to "-"
		if ( $cat === '-' ) {
			return false;
		}

		$containerCategory = Title::makeTitleSafe( NS_CATEGORY, $cat );
		if ( $containerCategory ) {
			$this->mOutput->addCategory( $containerCategory->getDBkey(), $this->getDefaultSort() );
			return true;
		} else {
			wfDebug( __METHOD__ . ": [[MediaWiki:$msg]] is not a valid title!\n" );
			return false;
		}
	}

	/**
	 * This function accomplishes several tasks:
	 * 1) Auto-number headings if that option is enabled
	 * 2) Add an [edit] link to sections for users who have enabled the option and can edit the page
	 * 3) Add a Table of contents on the top for users who have enabled the option
	 * 4) Auto-anchor headings
	 *
	 * It loops through all headlines, collects the necessary data, then splits up the
	 * string and re-inserts the newly formatted headlines.
	 *
	 * @param $text String
	 * @param string $origText original, untouched wikitext
	 * @param $isMain Boolean
	 * @return mixed|string
	 * @private
	 */
	function formatHeadings( $text, $origText, $isMain = true ) {
		global $wgMaxTocLevel, $wgExperimentalHtmlIds;

		# Inhibit editsection links if requested in the page
		if ( isset( $this->mDoubleUnderscores['noeditsection'] ) ) {
			$maybeShowEditLink = $showEditLink = false;
		} else {
			$maybeShowEditLink = true; /* Actual presence will depend on ParserOptions option */
			$showEditLink = $this->mOptions->getEditSection();
		}
		if ( $showEditLink ) {
			$this->mOutput->setEditSectionTokens( true );
		}

		# Get all headlines for numbering them and adding funky stuff like [edit]
		# links - this is for later, but we need the number of headlines right now
		$matches = array();
		$numMatches = preg_match_all( '/<H(?P<level>[1-6])(?P<attrib>.*?' . '>)\s*(?P<header>[\s\S]*?)\s*<\/H[1-6] *>/i', $text, $matches );

		# if there are fewer than 4 headlines in the article, do not show TOC
		# unless it's been explicitly enabled.
		$enoughToc = $this->mShowToc &&
			( ( $numMatches >= 4 ) || $this->mForceTocPosition );

		# Allow user to stipulate that a page should have a "new section"
		# link added via __NEWSECTIONLINK__
		if ( isset( $this->mDoubleUnderscores['newsectionlink'] ) ) {
			$this->mOutput->setNewSection( true );
		}

		# Allow user to remove the "new section"
		# link via __NONEWSECTIONLINK__
		if ( isset( $this->mDoubleUnderscores['nonewsectionlink'] ) ) {
			$this->mOutput->hideNewSection( true );
		}

		# if the string __FORCETOC__ (not case-sensitive) occurs in the HTML,
		# override above conditions and always show TOC above first header
		if ( isset( $this->mDoubleUnderscores['forcetoc'] ) ) {
			$this->mShowToc = true;
			$enoughToc = true;
		}

		# headline counter
		$headlineCount = 0;
		$numVisible = 0;

		# Ugh .. the TOC should have neat indentation levels which can be
		# passed to the skin functions. These are determined here
		$toc = '';
		$full = '';
		$head = array();
		$sublevelCount = array();
		$levelCount = array();
		$level = 0;
		$prevlevel = 0;
		$toclevel = 0;
		$prevtoclevel = 0;
		$markerRegex = "{$this->mUniqPrefix}-h-(\d+)-" . self::MARKER_SUFFIX;
		$baseTitleText = $this->mTitle->getPrefixedDBkey();
		$oldType = $this->mOutputType;
		$this->setOutputType( self::OT_WIKI );
		$frame = $this->getPreprocessor()->newFrame();
		$root = $this->preprocessToDom( $origText );
		$node = $root->getFirstChild();
		$byteOffset = 0;
		$tocraw = array();
		$refers = array();

		foreach ( $matches[3] as $headline ) {
			$isTemplate = false;
			$titleText = false;
			$sectionIndex = false;
			$numbering = '';
			$markerMatches = array();
			if ( preg_match( "/^$markerRegex/", $headline, $markerMatches ) ) {
				$serial = $markerMatches[1];
				list( $titleText, $sectionIndex ) = $this->mHeadings[$serial];
				$isTemplate = ( $titleText != $baseTitleText );
				$headline = preg_replace( "/^$markerRegex\\s*/", "", $headline );
			}

			if ( $toclevel ) {
				$prevlevel = $level;
			}
			$level = $matches[1][$headlineCount];

			if ( $level > $prevlevel ) {
				# Increase TOC level
				$toclevel++;
				$sublevelCount[$toclevel] = 0;
				if ( $toclevel < $wgMaxTocLevel ) {
					$prevtoclevel = $toclevel;
					$toc .= Linker::tocIndent();
					$numVisible++;
				}
			} elseif ( $level < $prevlevel && $toclevel > 1 ) {
				# Decrease TOC level, find level to jump to

				for ( $i = $toclevel; $i > 0; $i-- ) {
					if ( $levelCount[$i] == $level ) {
						# Found last matching level
						$toclevel = $i;
						break;
					} elseif ( $levelCount[$i] < $level ) {
						# Found first matching level below current level
						$toclevel = $i + 1;
						break;
					}
				}
				if ( $i == 0 ) {
					$toclevel = 1;
				}
				if ( $toclevel < $wgMaxTocLevel ) {
					if ( $prevtoclevel < $wgMaxTocLevel ) {
						# Unindent only if the previous toc level was shown :p
						$toc .= Linker::tocUnindent( $prevtoclevel - $toclevel );
						$prevtoclevel = $toclevel;
					} else {
						$toc .= Linker::tocLineEnd();
					}
				}
			} else {
				# No change in level, end TOC line
				if ( $toclevel < $wgMaxTocLevel ) {
					$toc .= Linker::tocLineEnd();
				}
			}

			$levelCount[$toclevel] = $level;

			# count number of headlines for each level
			$sublevelCount[$toclevel]++;
			$dot = 0;
			for ( $i = 1; $i <= $toclevel; $i++ ) {
				if ( !empty( $sublevelCount[$i] ) ) {
					if ( $dot ) {
						$numbering .= '.';
					}
					$numbering .= $this->getTargetLanguage()->formatNum( $sublevelCount[$i] );
					$dot = 1;
				}
			}

			# The safe header is a version of the header text safe to use for links

			# Remove link placeholders by the link text.
			#     <!--LINK number-->
			# turns into
			#     link text with suffix
			# Do this before unstrip since link text can contain strip markers
			$safeHeadline = $this->replaceLinkHoldersText( $headline );

			# Avoid insertion of weird stuff like <math> by expanding the relevant sections
			$safeHeadline = $this->mStripState->unstripBoth( $safeHeadline );

			# Strip out HTML (first regex removes any tag not allowed)
			# Allowed tags are:
			# * <sup> and <sub> (bug 8393)
			# * <i> (bug 26375)
			# * <b> (r105284)
			# * <span dir="rtl"> and <span dir="ltr"> (bug 35167)
			#
			# We strip any parameter from accepted tags (second regex), except dir="rtl|ltr" from <span>,
			# to allow setting directionality in toc items.
			$tocline = preg_replace(
				array( '#<(?!/?(span|sup|sub|i|b)(?: [^>]*)?>).*?' . '>#', '#<(/?(?:span(?: dir="(?:rtl|ltr)")?|sup|sub|i|b))(?: .*?)?' . '>#' ),
				array( '', '<$1>' ),
				$safeHeadline
			);
			$tocline = trim( $tocline );

			# For the anchor, strip out HTML-y stuff period
			$safeHeadline = preg_replace( '/<.*?' . '>/', '', $safeHeadline );
			$safeHeadline = Sanitizer::normalizeSectionNameWhitespace( $safeHeadline );

			# Save headline for section edit hint before it's escaped
			$headlineHint = $safeHeadline;

			if ( $wgExperimentalHtmlIds ) {
				# For reverse compatibility, provide an id that's
				# HTML4-compatible, like we used to.
				#
				# It may be worth noting, academically, that it's possible for
				# the legacy anchor to conflict with a non-legacy headline
				# anchor on the page.  In this case likely the "correct" thing
				# would be to either drop the legacy anchors or make sure
				# they're numbered first.  However, this would require people
				# to type in section names like "abc_.D7.93.D7.90.D7.A4"
				# manually, so let's not bother worrying about it.
				$legacyHeadline = Sanitizer::escapeId( $safeHeadline,
					array( 'noninitial', 'legacy' ) );
				$safeHeadline = Sanitizer::escapeId( $safeHeadline );

				if ( $legacyHeadline == $safeHeadline ) {
					# No reason to have both (in fact, we can't)
					$legacyHeadline = false;
				}
			} else {
				$legacyHeadline = false;
				$safeHeadline = Sanitizer::escapeId( $safeHeadline,
					'noninitial' );
			}

			# HTML names must be case-insensitively unique (bug 10721).
			# This does not apply to Unicode characters per
			# http://dev.w3.org/html5/spec/infrastructure.html#case-sensitivity-and-string-comparison
			# @todo FIXME: We may be changing them depending on the current locale.
			$arrayKey = strtolower( $safeHeadline );
			if ( $legacyHeadline === false ) {
				$legacyArrayKey = false;
			} else {
				$legacyArrayKey = strtolower( $legacyHeadline );
			}

			# count how many in assoc. array so we can track dupes in anchors
			if ( isset( $refers[$arrayKey] ) ) {
				$refers[$arrayKey]++;
			} else {
				$refers[$arrayKey] = 1;
			}
			if ( isset( $refers[$legacyArrayKey] ) ) {
				$refers[$legacyArrayKey]++;
			} else {
				$refers[$legacyArrayKey] = 1;
			}

			# Don't number the heading if it is the only one (looks silly)
			if ( count( $matches[3] ) > 1 && $this->mOptions->getNumberHeadings() ) {
				# the two are different if the line contains a link
				$headline = Html::element( 'span', array( 'class' => 'mw-headline-number' ), $numbering ) . ' ' . $headline;
			}

			# Create the anchor for linking from the TOC to the section
			$anchor = $safeHeadline;
			$legacyAnchor = $legacyHeadline;
			if ( $refers[$arrayKey] > 1 ) {
				$anchor .= '_' . $refers[$arrayKey];
			}
			if ( $legacyHeadline !== false && $refers[$legacyArrayKey] > 1 ) {
				$legacyAnchor .= '_' . $refers[$legacyArrayKey];
			}
			if ( $enoughToc && ( !isset( $wgMaxTocLevel ) || $toclevel < $wgMaxTocLevel ) ) {
				$toc .= Linker::tocLine( $anchor, $tocline,
					$numbering, $toclevel, ( $isTemplate ? false : $sectionIndex ) );
			}

			# Add the section to the section tree
			# Find the DOM node for this header
			$noOffset = ( $isTemplate || $sectionIndex === false );
			while ( $node && !$noOffset ) {
				if ( $node->getName() === 'h' ) {
					$bits = $node->splitHeading();
					if ( $bits['i'] == $sectionIndex ) {
						break;
					}
				}
				$byteOffset += mb_strlen( $this->mStripState->unstripBoth(
					$frame->expand( $node, PPFrame::RECOVER_ORIG ) ) );
				$node = $node->getNextSibling();
			}
			$tocraw[] = array(
				'toclevel' => $toclevel,
				'level' => $level,
				'line' => $tocline,
				'number' => $numbering,
				'index' => ( $isTemplate ? 'T-' : '' ) . $sectionIndex,
				'fromtitle' => $titleText,
				'byteoffset' => ( $noOffset ? null : $byteOffset ),
				'anchor' => $anchor,
			);

			# give headline the correct <h#> tag
			if ( $maybeShowEditLink && $sectionIndex !== false ) {
				// Output edit section links as markers with styles that can be customized by skins
				if ( $isTemplate ) {
					# Put a T flag in the section identifier, to indicate to extractSections()
					# that sections inside <includeonly> should be counted.
					$editlinkArgs = array( $titleText, "T-$sectionIndex"/*, null */ );
				} else {
					$editlinkArgs = array( $this->mTitle->getPrefixedText(), $sectionIndex, $headlineHint );
				}
				// We use a bit of pesudo-xml for editsection markers. The language converter is run later on
				// Using a UNIQ style marker leads to the converter screwing up the tokens when it converts stuff
				// And trying to insert strip tags fails too. At this point all real inputted tags have already been escaped
				// so we don't have to worry about a user trying to input one of these markers directly.
				// We use a page and section attribute to stop the language converter from converting these important bits
				// of data, but put the headline hint inside a content block because the language converter is supposed to
				// be able to convert that piece of data.
				$editlink = '<mw:editsection page="' . htmlspecialchars( $editlinkArgs[0] );
				$editlink .= '" section="' . htmlspecialchars( $editlinkArgs[1] ) . '"';
				if ( isset( $editlinkArgs[2] ) ) {
					$editlink .= '>' . $editlinkArgs[2] . '</mw:editsection>';
				} else {
					$editlink .= '/>';
				}
			} else {
				$editlink = '';
			}
			$head[$headlineCount] = Linker::makeHeadline( $level,
				$matches['attrib'][$headlineCount], $anchor, $headline,
				$editlink, $legacyAnchor );

			$headlineCount++;
		}

		$this->setOutputType( $oldType );

		# Never ever show TOC if no headers
		if ( $numVisible < 1 ) {
			$enoughToc = false;
		}

		if ( $enoughToc ) {
			if ( $prevtoclevel > 0 && $prevtoclevel < $wgMaxTocLevel ) {
				$toc .= Linker::tocUnindent( $prevtoclevel - 1 );
			}
			$toc = Linker::tocList( $toc, $this->mOptions->getUserLangObj() );
			$this->mOutput->setTOCHTML( $toc );
			$toc = self::TOC_START . $toc . self::TOC_END;
		}

		if ( $isMain ) {
			$this->mOutput->setSections( $tocraw );
		}

		# split up and insert constructed headlines
		$blocks = preg_split( '/<H[1-6].*?' . '>[\s\S]*?<\/H[1-6]>/i', $text );
		$i = 0;

		// build an array of document sections
		$sections = array();
		foreach ( $blocks as $block ) {
			// $head is zero-based, sections aren't.
			if ( empty( $head[$i - 1] ) ) {
				$sections[$i] = $block;
			} else {
				$sections[$i] = $head[$i - 1] . $block;
			}

			/**
			 * Send a hook, one per section.
			 * The idea here is to be able to make section-level DIVs, but to do so in a
			 * lower-impact, more correct way than r50769
			 *
			 * $this : caller
			 * $section : the section number
			 * &$sectionContent : ref to the content of the section
			 * $showEditLinks : boolean describing whether this section has an edit link
			 */
			wfRunHooks( 'ParserSectionCreate', array( $this, $i, &$sections[$i], $showEditLink ) );

			$i++;
		}

		if ( $enoughToc && $isMain && !$this->mForceTocPosition ) {
			// append the TOC at the beginning
			// Top anchor now in skin
			$sections[0] = $sections[0] . $toc . "\n";
		}

		$full .= join( '', $sections );

		if ( $this->mForceTocPosition ) {
			return str_replace( '<!--MWTOC-->', $toc, $full );
		} else {
			return $full;
		}
	}

	/**
	 * Transform wiki markup when saving a page by doing "\r\n" -> "\n"
	 * conversion, substitting signatures, {{subst:}} templates, etc.
	 *
	 * @param string $text the text to transform
	 * @param $title Title: the Title object for the current article
	 * @param $user User: the User object describing the current user
	 * @param $options ParserOptions: parsing options
	 * @param $clearState Boolean: whether to clear the parser state first
	 * @return String: the altered wiki markup
	 */
	public function preSaveTransform( $text, Title $title, User $user, ParserOptions $options, $clearState = true ) {
		$this->startParse( $title, $options, self::OT_WIKI, $clearState );
		$this->setUser( $user );

		$pairs = array(
			"\r\n" => "\n",
		);
		$text = str_replace( array_keys( $pairs ), array_values( $pairs ), $text );
		if ( $options->getPreSaveTransform() ) {
			$text = $this->pstPass2( $text, $user );
		}
		$text = $this->mStripState->unstripBoth( $text );

		$this->setUser( null ); #Reset

		return $text;
	}

	/**
	 * Pre-save transform helper function
	 *
	 * @param $text string
	 * @param $user User
	 *
	 * @return string
	 */
	private function pstPass2( $text, $user ) {
		global $wgContLang;

		# Note: This is the timestamp saved as hardcoded wikitext to
		# the database, we use $wgContLang here in order to give
		# everyone the same signature and use the default one rather
		# than the one selected in each user's preferences.
		# (see also bug 12815)
		$ts = $this->mOptions->getTimestamp();
		$timestamp = MWTimestamp::getLocalInstance( $ts );
		$ts = $timestamp->format( 'YmdHis' );
		$tzMsg = $timestamp->format( 'T' );  # might vary on DST changeover!

		# Allow translation of timezones through wiki. format() can return
		# whatever crap the system uses, localised or not, so we cannot
		# ship premade translations.
		$key = 'timezone-' . strtolower( trim( $tzMsg ) );
		$msg = wfMessage( $key )->inContentLanguage();
		if ( $msg->exists() ) {
			$tzMsg = $msg->text();
		}

		$d = $wgContLang->timeanddate( $ts, false, false ) . " ($tzMsg)";

		# Variable replacement
		# Because mOutputType is OT_WIKI, this will only process {{subst:xxx}} type tags
		$text = $this->replaceVariables( $text );

		# This works almost by chance, as the replaceVariables are done before the getUserSig(),
		# which may corrupt this parser instance via its wfMessage()->text() call-

		# Signatures
		$sigText = $this->getUserSig( $user );
		$text = strtr( $text, array(
			'~~~~~' => $d,
			'~~~~' => "$sigText $d",
			'~~~' => $sigText
		) );

		# Context links ("pipe tricks"): [[|name]] and [[name (context)|]]
		$tc = '[' . Title::legalChars() . ']';
		$nc = '[ _0-9A-Za-z\x80-\xff-]'; # Namespaces can use non-ascii!

		$p1 = "/\[\[(:?$nc+:|:|)($tc+?)( ?\\($tc+\\))\\|]]/";                  	# [[ns:page (context)|]]
		$p4 = "/\[\[(:?$nc+:|:|)($tc+?)( ?（$tc+）)\\|]]/";                    	# [[ns:page（context）|]] (double-width brackets, added in r40257)
		$p3 = "/\[\[(:?$nc+:|:|)($tc+?)( ?\\($tc+\\)|)((?:, |，)$tc+|)\\|]]/"; 	# [[ns:page (context), context|]] (using either single or double-width comma)
		$p2 = "/\[\[\\|($tc+)]]/";                                             	# [[|page]] (reverse pipe trick: add context from page title)

		# try $p1 first, to turn "[[A, B (C)|]]" into "[[A, B (C)|A, B]]"
		$text = preg_replace( $p1, '[[\\1\\2\\3|\\2]]', $text );
		$text = preg_replace( $p4, '[[\\1\\2\\3|\\2]]', $text );
		$text = preg_replace( $p3, '[[\\1\\2\\3\\4|\\2]]', $text );

		$t = $this->mTitle->getText();
		$m = array();
		if ( preg_match( "/^($nc+:|)$tc+?( \\($tc+\\))$/", $t, $m ) ) {
			$text = preg_replace( $p2, "[[$m[1]\\1$m[2]|\\1]]", $text );
		} elseif ( preg_match( "/^($nc+:|)$tc+?(, $tc+|)$/", $t, $m ) && "$m[1]$m[2]" != '' ) {
			$text = preg_replace( $p2, "[[$m[1]\\1$m[2]|\\1]]", $text );
		} else {
			# if there's no context, don't bother duplicating the title
			$text = preg_replace( $p2, '[[\\1]]', $text );
		}

		# Trim trailing whitespace
		$text = rtrim( $text );

		return $text;
	}

	/**
	 * Fetch the user's signature text, if any, and normalize to
	 * validated, ready-to-insert wikitext.
	 * If you have pre-fetched the nickname or the fancySig option, you can
	 * specify them here to save a database query.
	 * Do not reuse this parser instance after calling getUserSig(),
	 * as it may have changed if it's the $wgParser.
	 *
	 * @param $user User
	 * @param string|bool $nickname nickname to use or false to use user's default nickname
	 * @param $fancySig Boolean|null whether the nicknname is the complete signature
	 *                  or null to use default value
	 * @return string
	 */
	function getUserSig( &$user, $nickname = false, $fancySig = null ) {
		global $wgMaxSigChars;

		$username = $user->getName();

		# If not given, retrieve from the user object.
		if ( $nickname === false ) {
			$nickname = $user->getOption( 'nickname' );
		}

		if ( is_null( $fancySig ) ) {
			$fancySig = $user->getBoolOption( 'fancysig' );
		}

		$nickname = $nickname == null ? $username : $nickname;

		if ( mb_strlen( $nickname ) > $wgMaxSigChars ) {
			$nickname = $username;
			wfDebug( __METHOD__ . ": $username has overlong signature.\n" );
		} elseif ( $fancySig !== false ) {
			# Sig. might contain markup; validate this
			if ( $this->validateSig( $nickname ) !== false ) {
				# Validated; clean up (if needed) and return it
				return $this->cleanSig( $nickname, true );
			} else {
				# Failed to validate; fall back to the default
				$nickname = $username;
				wfDebug( __METHOD__ . ": $username has bad XML tags in signature.\n" );
			}
		}

		# Make sure nickname doesnt get a sig in a sig
		$nickname = self::cleanSigInSig( $nickname );

		# If we're still here, make it a link to the user page
		$userText = wfEscapeWikiText( $username );
		$nickText = wfEscapeWikiText( $nickname );
		$msgName = $user->isAnon() ? 'signature-anon' : 'signature';

		return wfMessage( $msgName, $userText, $nickText )->inContentLanguage()->title( $this->getTitle() )->text();
	}

	/**
	 * Check that the user's signature contains no bad XML
	 *
	 * @param $text String
	 * @return mixed An expanded string, or false if invalid.
	 */
	function validateSig( $text ) {
		return Xml::isWellFormedXmlFragment( $text ) ? $text : false;
	}

	/**
	 * Clean up signature text
	 *
	 * 1) Strip ~~~, ~~~~ and ~~~~~ out of signatures @see cleanSigInSig
	 * 2) Substitute all transclusions
	 *
	 * @param $text String
	 * @param bool $parsing Whether we're cleaning (preferences save) or parsing
	 * @return String: signature text
	 */
	public function cleanSig( $text, $parsing = false ) {
		if ( !$parsing ) {
			global $wgTitle;
			$this->startParse( $wgTitle, new ParserOptions, self::OT_PREPROCESS, true );
		}

		# Option to disable this feature
		if ( !$this->mOptions->getCleanSignatures() ) {
			return $text;
		}

		# @todo FIXME: Regex doesn't respect extension tags or nowiki
		#  => Move this logic to braceSubstitution()
		$substWord = MagicWord::get( 'subst' );
		$substRegex = '/\{\{(?!(?:' . $substWord->getBaseRegex() . '))/x' . $substWord->getRegexCase();
		$substText = '{{' . $substWord->getSynonym( 0 );

		$text = preg_replace( $substRegex, $substText, $text );
		$text = self::cleanSigInSig( $text );
		$dom = $this->preprocessToDom( $text );
		$frame = $this->getPreprocessor()->newFrame();
		$text = $frame->expand( $dom );

		if ( !$parsing ) {
			$text = $this->mStripState->unstripBoth( $text );
		}

		return $text;
	}

	/**
	 * Strip ~~~, ~~~~ and ~~~~~ out of signatures
	 *
	 * @param $text String
	 * @return String: signature text with /~{3,5}/ removed
	 */
	public static function cleanSigInSig( $text ) {
		$text = preg_replace( '/~{3,5}/', '', $text );
		return $text;
	}

	/**
	 * Set up some variables which are usually set up in parse()
	 * so that an external function can call some class members with confidence
	 *
	 * @param $title Title|null
	 * @param $options ParserOptions
	 * @param $outputType
	 * @param $clearState bool
	 */
	public function startExternalParse( Title $title = null, ParserOptions $options, $outputType, $clearState = true ) {
		$this->startParse( $title, $options, $outputType, $clearState );
	}

	/**
	 * @param $title Title|null
	 * @param $options ParserOptions
	 * @param $outputType
	 * @param $clearState bool
	 */
	private function startParse( Title $title = null, ParserOptions $options, $outputType, $clearState = true ) {
		$this->setTitle( $title );
		$this->mOptions = $options;
		$this->setOutputType( $outputType );
		if ( $clearState ) {
			$this->clearState();
		}
	}

	/**
	 * Wrapper for preprocess()
	 *
	 * @param string $text the text to preprocess
	 * @param $options ParserOptions: options
	 * @param $title Title object or null to use $wgTitle
	 * @return String
	 */
	public function transformMsg( $text, $options, $title = null ) {
		static $executing = false;

		# Guard against infinite recursion
		if ( $executing ) {
			return $text;
		}
		$executing = true;

		wfProfileIn( __METHOD__ );
		if ( !$title ) {
			global $wgTitle;
			$title = $wgTitle;
		}

		$text = $this->preprocess( $text, $title, $options );

		$executing = false;
		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * Create an HTML-style tag, e.g. "<yourtag>special text</yourtag>"
	 * The callback should have the following form:
	 *    function myParserHook( $text, $params, $parser, $frame ) { ... }
	 *
	 * Transform and return $text. Use $parser for any required context, e.g. use
	 * $parser->getTitle() and $parser->getOptions() not $wgTitle or $wgOut->mParserOptions
	 *
	 * Hooks may return extended information by returning an array, of which the
	 * first numbered element (index 0) must be the return string, and all other
	 * entries are extracted into local variables within an internal function
	 * in the Parser class.
	 *
	 * This interface (introduced r61913) appears to be undocumented, but
	 * 'markerName' is used by some core tag hooks to override which strip
	 * array their results are placed in. **Use great caution if attempting
	 * this interface, as it is not documented and injudicious use could smash
	 * private variables.**
	 *
	 * @param $tag Mixed: the tag to use, e.g. 'hook' for "<hook>"
	 * @param $callback Mixed: the callback function (and object) to use for the tag
	 * @throws MWException
	 * @return Mixed|null The old value of the mTagHooks array associated with the hook
	 */
	public function setHook( $tag, $callback ) {
		$tag = strtolower( $tag );
		if ( preg_match( '/[<>\r\n]/', $tag, $m ) ) {
			throw new MWException( "Invalid character {$m[0]} in setHook('$tag', ...) call" );
		}
		$oldVal = isset( $this->mTagHooks[$tag] ) ? $this->mTagHooks[$tag] : null;
		$this->mTagHooks[$tag] = $callback;
		if ( !in_array( $tag, $this->mStripList ) ) {
			$this->mStripList[] = $tag;
		}

		return $oldVal;
	}

	/**
	 * As setHook(), but letting the contents be parsed.
	 *
	 * Transparent tag hooks are like regular XML-style tag hooks, except they
	 * operate late in the transformation sequence, on HTML instead of wikitext.
	 *
	 * This is probably obsoleted by things dealing with parser frames?
	 * The only extension currently using it is geoserver.
	 *
	 * @since 1.10
	 * @todo better document or deprecate this
	 *
	 * @param $tag Mixed: the tag to use, e.g. 'hook' for "<hook>"
	 * @param $callback Mixed: the callback function (and object) to use for the tag
	 * @throws MWException
	 * @return Mixed|null The old value of the mTagHooks array associated with the hook
	 */
	function setTransparentTagHook( $tag, $callback ) {
		$tag = strtolower( $tag );
		if ( preg_match( '/[<>\r\n]/', $tag, $m ) ) {
			throw new MWException( "Invalid character {$m[0]} in setTransparentHook('$tag', ...) call" );
		}
		$oldVal = isset( $this->mTransparentTagHooks[$tag] ) ? $this->mTransparentTagHooks[$tag] : null;
		$this->mTransparentTagHooks[$tag] = $callback;

		return $oldVal;
	}

	/**
	 * Remove all tag hooks
	 */
	function clearTagHooks() {
		$this->mTagHooks = array();
		$this->mFunctionTagHooks = array();
		$this->mStripList = $this->mDefaultStripList;
	}

	/**
	 * Create a function, e.g. {{sum:1|2|3}}
	 * The callback function should have the form:
	 *    function myParserFunction( &$parser, $arg1, $arg2, $arg3 ) { ... }
	 *
	 * Or with SFH_OBJECT_ARGS:
	 *    function myParserFunction( $parser, $frame, $args ) { ... }
	 *
	 * The callback may either return the text result of the function, or an array with the text
	 * in element 0, and a number of flags in the other elements. The names of the flags are
	 * specified in the keys. Valid flags are:
	 *   found                     The text returned is valid, stop processing the template. This
	 *                             is on by default.
	 *   nowiki                    Wiki markup in the return value should be escaped
	 *   isHTML                    The returned text is HTML, armour it against wikitext transformation
	 *
	 * @param string $id The magic word ID
	 * @param $callback Mixed: the callback function (and object) to use
	 * @param $flags Integer: a combination of the following flags:
	 *     SFH_NO_HASH   No leading hash, i.e. {{plural:...}} instead of {{#if:...}}
	 *
	 *     SFH_OBJECT_ARGS   Pass the template arguments as PPNode objects instead of text. This
	 *     allows for conditional expansion of the parse tree, allowing you to eliminate dead
	 *     branches and thus speed up parsing. It is also possible to analyse the parse tree of
	 *     the arguments, and to control the way they are expanded.
	 *
	 *     The $frame parameter is a PPFrame. This can be used to produce expanded text from the
	 *     arguments, for instance:
	 *         $text = isset( $args[0] ) ? $frame->expand( $args[0] ) : '';
	 *
	 *     For technical reasons, $args[0] is pre-expanded and will be a string. This may change in
	 *     future versions. Please call $frame->expand() on it anyway so that your code keeps
	 *     working if/when this is changed.
	 *
	 *     If you want whitespace to be trimmed from $args, you need to do it yourself, post-
	 *     expansion.
	 *
	 *     Please read the documentation in includes/parser/Preprocessor.php for more information
	 *     about the methods available in PPFrame and PPNode.
	 *
	 * @throws MWException
	 * @return string|callback The old callback function for this name, if any
	 */
	public function setFunctionHook( $id, $callback, $flags = 0 ) {
		global $wgContLang;

		$oldVal = isset( $this->mFunctionHooks[$id] ) ? $this->mFunctionHooks[$id][0] : null;
		$this->mFunctionHooks[$id] = array( $callback, $flags );

		# Add to function cache
		$mw = MagicWord::get( $id );
		if ( !$mw ) {
			throw new MWException( __METHOD__ . '() expecting a magic word identifier.' );
		}

		$synonyms = $mw->getSynonyms();
		$sensitive = intval( $mw->isCaseSensitive() );

		foreach ( $synonyms as $syn ) {
			# Case
			if ( !$sensitive ) {
				$syn = $wgContLang->lc( $syn );
			}
			# Add leading hash
			if ( !( $flags & SFH_NO_HASH ) ) {
				$syn = '#' . $syn;
			}
			# Remove trailing colon
			if ( substr( $syn, -1, 1 ) === ':' ) {
				$syn = substr( $syn, 0, -1 );
			}
			$this->mFunctionSynonyms[$sensitive][$syn] = $id;
		}
		return $oldVal;
	}

	/**
	 * Get all registered function hook identifiers
	 *
	 * @return Array
	 */
	function getFunctionHooks() {
		return array_keys( $this->mFunctionHooks );
	}

	/**
	 * Create a tag function, e.g. "<test>some stuff</test>".
	 * Unlike tag hooks, tag functions are parsed at preprocessor level.
	 * Unlike parser functions, their content is not preprocessed.
	 * @param $tag
	 * @param $callback
	 * @param $flags
	 * @throws MWException
	 * @return null
	 */
	function setFunctionTagHook( $tag, $callback, $flags ) {
		$tag = strtolower( $tag );
		if ( preg_match( '/[<>\r\n]/', $tag, $m ) ) {
			throw new MWException( "Invalid character {$m[0]} in setFunctionTagHook('$tag', ...) call" );
		}
		$old = isset( $this->mFunctionTagHooks[$tag] ) ?
			$this->mFunctionTagHooks[$tag] : null;
		$this->mFunctionTagHooks[$tag] = array( $callback, $flags );

		if ( !in_array( $tag, $this->mStripList ) ) {
			$this->mStripList[] = $tag;
		}

		return $old;
	}

	/**
	 * @todo FIXME: Update documentation. makeLinkObj() is deprecated.
	 * Replace "<!--LINK-->" link placeholders with actual links, in the buffer
	 * Placeholders created in Skin::makeLinkObj()
	 *
	 * @param $text string
	 * @param $options int
	 *
	 * @return array of link CSS classes, indexed by PDBK.
	 */
	function replaceLinkHolders( &$text, $options = 0 ) {
		return $this->mLinkHolders->replace( $text );
	}

	/**
	 * Replace "<!--LINK-->" link placeholders with plain text of links
	 * (not HTML-formatted).
	 *
	 * @param $text String
	 * @return String
	 */
	function replaceLinkHoldersText( $text ) {
		return $this->mLinkHolders->replaceText( $text );
	}

	/**
	 * Renders an image gallery from a text with one line per image.
	 * text labels may be given by using |-style alternative text. E.g.
	 *   Image:one.jpg|The number "1"
	 *   Image:tree.jpg|A tree
	 * given as text will return the HTML of a gallery with two images,
	 * labeled 'The number "1"' and
	 * 'A tree'.
	 *
	 * @param string $text
	 * @param array $params
	 * @return string HTML
	 */
	function renderImageGallery( $text, $params ) {
		wfProfileIn( __METHOD__ );

		$mode = false;
		if ( isset( $params['mode'] ) ) {
			$mode = $params['mode'];
		}

		try {
			$ig = ImageGalleryBase::factory( $mode );
		} catch ( MWException $e ) {
			// If invalid type set, fallback to default.
			$ig = ImageGalleryBase::factory( false );
		}

		$ig->setContextTitle( $this->mTitle );
		$ig->setShowBytes( false );
		$ig->setShowFilename( false );
		$ig->setParser( $this );
		$ig->setHideBadImages();
		$ig->setAttributes( Sanitizer::validateTagAttributes( $params, 'table' ) );

		if ( isset( $params['showfilename'] ) ) {
			$ig->setShowFilename( true );
		} else {
			$ig->setShowFilename( false );
		}
		if ( isset( $params['caption'] ) ) {
			$caption = $params['caption'];
			$caption = htmlspecialchars( $caption );
			$caption = $this->replaceInternalLinks( $caption );
			$ig->setCaptionHtml( $caption );
		}
		if ( isset( $params['perrow'] ) ) {
			$ig->setPerRow( $params['perrow'] );
		}
		if ( isset( $params['widths'] ) ) {
			$ig->setWidths( $params['widths'] );
		}
		if ( isset( $params['heights'] ) ) {
			$ig->setHeights( $params['heights'] );
		}
		$ig->setAdditionalOptions( $params );

		wfRunHooks( 'BeforeParserrenderImageGallery', array( &$this, &$ig ) );

		$lines = StringUtils::explode( "\n", $text );
		foreach ( $lines as $line ) {
			# match lines like these:
			# Image:someimage.jpg|This is some image
			$matches = array();
			preg_match( "/^([^|]+)(\\|(.*))?$/", $line, $matches );
			# Skip empty lines
			if ( count( $matches ) == 0 ) {
				continue;
			}

			if ( strpos( $matches[0], '%' ) !== false ) {
				$matches[1] = rawurldecode( $matches[1] );
			}
			$title = Title::newFromText( $matches[1], NS_FILE );
			if ( is_null( $title ) ) {
				# Bogus title. Ignore these so we don't bomb out later.
				continue;
			}

			# We need to get what handler the file uses, to figure out parameters.
			# Note, a hook can overide the file name, and chose an entirely different
			# file (which potentially could be of a different type and have different handler).
			$options = array();
			$descQuery = false;
			wfRunHooks( 'BeforeParserFetchFileAndTitle',
				array( $this, $title, &$options, &$descQuery ) );
			# Don't register it now, as ImageGallery does that later.
			$file = $this->fetchFileNoRegister( $title, $options );
			$handler = $file ? $file->getHandler() : false;

			wfProfileIn( __METHOD__ . '-getMagicWord' );
			$paramMap = array(
				'img_alt' => 'gallery-internal-alt',
				'img_link' => 'gallery-internal-link',
			);
			if ( $handler ) {
				$paramMap = $paramMap + $handler->getParamMap();
				// We don't want people to specify per-image widths.
				// Additionally the width parameter would need special casing anyhow.
				unset( $paramMap['img_width'] );
			}

			$mwArray = new MagicWordArray( array_keys( $paramMap ) );
			wfProfileOut( __METHOD__ . '-getMagicWord' );

			$label = '';
			$alt = '';
			$link = '';
			$handlerOptions = array();
			if ( isset( $matches[3] ) ) {
				// look for an |alt= definition while trying not to break existing
				// captions with multiple pipes (|) in it, until a more sensible grammar
				// is defined for images in galleries

				// FIXME: Doing recursiveTagParse at this stage, and the trim before
				// splitting on '|' is a bit odd, and different from makeImage.
				$matches[3] = $this->recursiveTagParse( trim( $matches[3] ) );
				$parameterMatches = StringUtils::explode( '|', $matches[3] );

				foreach ( $parameterMatches as $parameterMatch ) {
					list( $magicName, $match ) = $mwArray->matchVariableStartToEnd( $parameterMatch );
					if ( $magicName ) {
						$paramName = $paramMap[$magicName];

						switch ( $paramName ) {
						case 'gallery-internal-alt':
							$alt = $this->stripAltText( $match, false );
							break;
						case 'gallery-internal-link':
							$linkValue = strip_tags( $this->replaceLinkHoldersText( $match ) );
							$chars = self::EXT_LINK_URL_CLASS;
							$prots = $this->mUrlProtocols;
							//check to see if link matches an absolute url, if not then it must be a wiki link.
							if ( preg_match( "/^($prots)$chars+$/u", $linkValue ) ) {
								$link = $linkValue;
							} else {
								$localLinkTitle = Title::newFromText( $linkValue );
								if ( $localLinkTitle !== null ) {
									$link = $localLinkTitle->getLocalURL();
								}
							}
							break;
						default:
							// Must be a handler specific parameter.
							if ( $handler->validateParam( $paramName, $match ) ) {
								$handlerOptions[$paramName] = $match;
							} else {
								// Guess not. Append it to the caption.
								wfDebug( "$parameterMatch failed parameter validation" );
								$label .= '|' . $parameterMatch;
							}
						}

					} else {
						// concatenate all other pipes
						$label .= '|' . $parameterMatch;
					}
				}
				// remove the first pipe
				$label = substr( $label, 1 );
			}

			$ig->add( $title, $label, $alt, $link, $handlerOptions );
		}
		$html = $ig->toHTML();
		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * @param $handler
	 * @return array
	 */
	function getImageParams( $handler ) {
		if ( $handler ) {
			$handlerClass = get_class( $handler );
		} else {
			$handlerClass = '';
		}
		if ( !isset( $this->mImageParams[$handlerClass] ) ) {
			# Initialise static lists
			static $internalParamNames = array(
				'horizAlign' => array( 'left', 'right', 'center', 'none' ),
				'vertAlign' => array( 'baseline', 'sub', 'super', 'top', 'text-top', 'middle',
					'bottom', 'text-bottom' ),
				'frame' => array( 'thumbnail', 'manualthumb', 'framed', 'frameless',
					'upright', 'border', 'link', 'alt', 'class' ),
			);
			static $internalParamMap;
			if ( !$internalParamMap ) {
				$internalParamMap = array();
				foreach ( $internalParamNames as $type => $names ) {
					foreach ( $names as $name ) {
						$magicName = str_replace( '-', '_', "img_$name" );
						$internalParamMap[$magicName] = array( $type, $name );
					}
				}
			}

			# Add handler params
			$paramMap = $internalParamMap;
			if ( $handler ) {
				$handlerParamMap = $handler->getParamMap();
				foreach ( $handlerParamMap as $magic => $paramName ) {
					$paramMap[$magic] = array( 'handler', $paramName );
				}
			}
			$this->mImageParams[$handlerClass] = $paramMap;
			$this->mImageParamsMagicArray[$handlerClass] = new MagicWordArray( array_keys( $paramMap ) );
		}
		return array( $this->mImageParams[$handlerClass], $this->mImageParamsMagicArray[$handlerClass] );
	}

	/**
	 * Parse image options text and use it to make an image
	 *
	 * @param $title Title
	 * @param $options String
	 * @param $holders LinkHolderArray|bool
	 * @return string HTML
	 */
	function makeImage( $title, $options, $holders = false ) {
		# Check if the options text is of the form "options|alt text"
		# Options are:
		#  * thumbnail  make a thumbnail with enlarge-icon and caption, alignment depends on lang
		#  * left       no resizing, just left align. label is used for alt= only
		#  * right      same, but right aligned
		#  * none       same, but not aligned
		#  * ___px      scale to ___ pixels width, no aligning. e.g. use in taxobox
		#  * center     center the image
		#  * frame      Keep original image size, no magnify-button.
		#  * framed     Same as "frame"
		#  * frameless  like 'thumb' but without a frame. Keeps user preferences for width
		#  * upright    reduce width for upright images, rounded to full __0 px
		#  * border     draw a 1px border around the image
		#  * alt        Text for HTML alt attribute (defaults to empty)
		#  * class      Set a class for img node
		#  * link       Set the target of the image link. Can be external, interwiki, or local
		# vertical-align values (no % or length right now):
		#  * baseline
		#  * sub
		#  * super
		#  * top
		#  * text-top
		#  * middle
		#  * bottom
		#  * text-bottom

		$parts = StringUtils::explode( "|", $options );

		# Give extensions a chance to select the file revision for us
		$options = array();
		$descQuery = false;
		wfRunHooks( 'BeforeParserFetchFileAndTitle',
			array( $this, $title, &$options, &$descQuery ) );
		# Fetch and register the file (file title may be different via hooks)
		list( $file, $title ) = $this->fetchFileAndTitle( $title, $options );

		# Get parameter map
		$handler = $file ? $file->getHandler() : false;

		list( $paramMap, $mwArray ) = $this->getImageParams( $handler );

		if ( !$file ) {
			$this->addTrackingCategory( 'broken-file-category' );
		}

		# Process the input parameters
		$caption = '';
		$params = array( 'frame' => array(), 'handler' => array(),
			'horizAlign' => array(), 'vertAlign' => array() );
		foreach ( $parts as $part ) {
			$part = trim( $part );
			list( $magicName, $value ) = $mwArray->matchVariableStartToEnd( $part );
			$validated = false;
			if ( isset( $paramMap[$magicName] ) ) {
				list( $type, $paramName ) = $paramMap[$magicName];

				# Special case; width and height come in one variable together
				if ( $type === 'handler' && $paramName === 'width' ) {
					$parsedWidthParam = $this->parseWidthParam( $value );
					if ( isset( $parsedWidthParam['width'] ) ) {
						$width = $parsedWidthParam['width'];
						if ( $handler->validateParam( 'width', $width ) ) {
							$params[$type]['width'] = $width;
							$validated = true;
						}
					}
					if ( isset( $parsedWidthParam['height'] ) ) {
						$height = $parsedWidthParam['height'];
						if ( $handler->validateParam( 'height', $height ) ) {
							$params[$type]['height'] = $height;
							$validated = true;
						}
					}
					# else no validation -- bug 13436
				} else {
					if ( $type === 'handler' ) {
						# Validate handler parameter
						$validated = $handler->validateParam( $paramName, $value );
					} else {
						# Validate internal parameters
						switch ( $paramName ) {
						case 'manualthumb':
						case 'alt':
						case 'class':
							# @todo FIXME: Possibly check validity here for
							# manualthumb? downstream behavior seems odd with
							# missing manual thumbs.
							$validated = true;
							$value = $this->stripAltText( $value, $holders );
							break;
						case 'link':
							$chars = self::EXT_LINK_URL_CLASS;
							$prots = $this->mUrlProtocols;
							if ( $value === '' ) {
								$paramName = 'no-link';
								$value = true;
								$validated = true;
							} elseif ( preg_match( "/^(?i)$prots/", $value ) ) {
								if ( preg_match( "/^((?i)$prots)$chars+$/u", $value, $m ) ) {
									$paramName = 'link-url';
									$this->mOutput->addExternalLink( $value );
									if ( $this->mOptions->getExternalLinkTarget() ) {
										$params[$type]['link-target'] = $this->mOptions->getExternalLinkTarget();
									}
									$validated = true;
								}
							} else {
								$linkTitle = Title::newFromText( $value );
								if ( $linkTitle ) {
									$paramName = 'link-title';
									$value = $linkTitle;
									$this->mOutput->addLink( $linkTitle );
									$validated = true;
								}
							}
							break;
						default:
							# Most other things appear to be empty or numeric...
							$validated = ( $value === false || is_numeric( trim( $value ) ) );
						}
					}

					if ( $validated ) {
						$params[$type][$paramName] = $value;
					}
				}
			}
			if ( !$validated ) {
				$caption = $part;
			}
		}

		# Process alignment parameters
		if ( $params['horizAlign'] ) {
			$params['frame']['align'] = key( $params['horizAlign'] );
		}
		if ( $params['vertAlign'] ) {
			$params['frame']['valign'] = key( $params['vertAlign'] );
		}

		$params['frame']['caption'] = $caption;

		# Will the image be presented in a frame, with the caption below?
		$imageIsFramed = isset( $params['frame']['frame'] )
			|| isset( $params['frame']['framed'] )
			|| isset( $params['frame']['thumbnail'] )
			|| isset( $params['frame']['manualthumb'] );

		# In the old days, [[Image:Foo|text...]] would set alt text.  Later it
		# came to also set the caption, ordinary text after the image -- which
		# makes no sense, because that just repeats the text multiple times in
		# screen readers.  It *also* came to set the title attribute.
		#
		# Now that we have an alt attribute, we should not set the alt text to
		# equal the caption: that's worse than useless, it just repeats the
		# text.  This is the framed/thumbnail case.  If there's no caption, we
		# use the unnamed parameter for alt text as well, just for the time be-
		# ing, if the unnamed param is set and the alt param is not.
		#
		# For the future, we need to figure out if we want to tweak this more,
		# e.g., introducing a title= parameter for the title; ignoring the un-
		# named parameter entirely for images without a caption; adding an ex-
		# plicit caption= parameter and preserving the old magic unnamed para-
		# meter for BC; ...
		if ( $imageIsFramed ) { # Framed image
			if ( $caption === '' && !isset( $params['frame']['alt'] ) ) {
				# No caption or alt text, add the filename as the alt text so
				# that screen readers at least get some description of the image
				$params['frame']['alt'] = $title->getText();
			}
			# Do not set $params['frame']['title'] because tooltips don't make sense
			# for framed images
		} else { # Inline image
			if ( !isset( $params['frame']['alt'] ) ) {
				# No alt text, use the "caption" for the alt text
				if ( $caption !== '' ) {
					$params['frame']['alt'] = $this->stripAltText( $caption, $holders );
				} else {
					# No caption, fall back to using the filename for the
					# alt text
					$params['frame']['alt'] = $title->getText();
				}
			}
			# Use the "caption" for the tooltip text
			$params['frame']['title'] = $this->stripAltText( $caption, $holders );
		}

		wfRunHooks( 'ParserMakeImageParams', array( $title, $file, &$params, $this ) );

		# Linker does the rest
		$time = isset( $options['time'] ) ? $options['time'] : false;
		$ret = Linker::makeImageLink( $this, $title, $file, $params['frame'], $params['handler'],
			$time, $descQuery, $this->mOptions->getThumbSize() );

		# Give the handler a chance to modify the parser object
		if ( $handler ) {
			$handler->parserTransformHook( $this, $file );
		}

		return $ret;
	}

	/**
	 * @param $caption
	 * @param $holders LinkHolderArray
	 * @return mixed|String
	 */
	protected function stripAltText( $caption, $holders ) {
		# Strip bad stuff out of the title (tooltip).  We can't just use
		# replaceLinkHoldersText() here, because if this function is called
		# from replaceInternalLinks2(), mLinkHolders won't be up-to-date.
		if ( $holders ) {
			$tooltip = $holders->replaceText( $caption );
		} else {
			$tooltip = $this->replaceLinkHoldersText( $caption );
		}

		# make sure there are no placeholders in thumbnail attributes
		# that are later expanded to html- so expand them now and
		# remove the tags
		$tooltip = $this->mStripState->unstripBoth( $tooltip );
		$tooltip = Sanitizer::stripAllTags( $tooltip );

		return $tooltip;
	}

	/**
	 * Set a flag in the output object indicating that the content is dynamic and
	 * shouldn't be cached.
	 */
	function disableCache() {
		wfDebug( "Parser output marked as uncacheable.\n" );
		if ( !$this->mOutput ) {
			throw new MWException( __METHOD__ .
				" can only be called when actually parsing something" );
		}
		$this->mOutput->setCacheTime( -1 ); // old style, for compatibility
		$this->mOutput->updateCacheExpiry( 0 ); // new style, for consistency
	}

	/**
	 * Callback from the Sanitizer for expanding items found in HTML attribute
	 * values, so they can be safely tested and escaped.
	 *
	 * @param $text String
	 * @param $frame PPFrame
	 * @return String
	 */
	function attributeStripCallback( &$text, $frame = false ) {
		$text = $this->replaceVariables( $text, $frame );
		$text = $this->mStripState->unstripBoth( $text );
		return $text;
	}

	/**
	 * Accessor
	 *
	 * @return array
	 */
	function getTags() {
		return array_merge( array_keys( $this->mTransparentTagHooks ), array_keys( $this->mTagHooks ), array_keys( $this->mFunctionTagHooks ) );
	}

	/**
	 * Replace transparent tags in $text with the values given by the callbacks.
	 *
	 * Transparent tag hooks are like regular XML-style tag hooks, except they
	 * operate late in the transformation sequence, on HTML instead of wikitext.
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	function replaceTransparentTags( $text ) {
		$matches = array();
		$elements = array_keys( $this->mTransparentTagHooks );
		$text = self::extractTagsAndParams( $elements, $text, $matches, $this->mUniqPrefix );
		$replacements = array();

		foreach ( $matches as $marker => $data ) {
			list( $element, $content, $params, $tag ) = $data;
			$tagName = strtolower( $element );
			if ( isset( $this->mTransparentTagHooks[$tagName] ) ) {
				$output = call_user_func_array( $this->mTransparentTagHooks[$tagName], array( $content, $params, $this ) );
			} else {
				$output = $tag;
			}
			$replacements[$marker] = $output;
		}
		return strtr( $text, $replacements );
	}

	/**
	 * Break wikitext input into sections, and either pull or replace
	 * some particular section's text.
	 *
	 * External callers should use the getSection and replaceSection methods.
	 *
	 * @param string $text Page wikitext
	 * @param string $section a section identifier string of the form:
	 *   "<flag1> - <flag2> - ... - <section number>"
	 *
	 * Currently the only recognised flag is "T", which means the target section number
	 * was derived during a template inclusion parse, in other words this is a template
	 * section edit link. If no flags are given, it was an ordinary section edit link.
	 * This flag is required to avoid a section numbering mismatch when a section is
	 * enclosed by "<includeonly>" (bug 6563).
	 *
	 * The section number 0 pulls the text before the first heading; other numbers will
	 * pull the given section along with its lower-level subsections. If the section is
	 * not found, $mode=get will return $newtext, and $mode=replace will return $text.
	 *
	 * Section 0 is always considered to exist, even if it only contains the empty
	 * string. If $text is the empty string and section 0 is replaced, $newText is
	 * returned.
	 *
	 * @param string $mode one of "get" or "replace"
	 * @param string $newText replacement text for section data.
	 * @return String: for "get", the extracted section text.
	 *                 for "replace", the whole page with the section replaced.
	 */
	private function extractSections( $text, $section, $mode, $newText = '' ) {
		global $wgTitle; # not generally used but removes an ugly failure mode
		$this->startParse( $wgTitle, new ParserOptions, self::OT_PLAIN, true );
		$outText = '';
		$frame = $this->getPreprocessor()->newFrame();

		# Process section extraction flags
		$flags = 0;
		$sectionParts = explode( '-', $section );
		$sectionIndex = array_pop( $sectionParts );
		foreach ( $sectionParts as $part ) {
			if ( $part === 'T' ) {
				$flags |= self::PTD_FOR_INCLUSION;
			}
		}

		# Check for empty input
		if ( strval( $text ) === '' ) {
			# Only sections 0 and T-0 exist in an empty document
			if ( $sectionIndex == 0 ) {
				if ( $mode === 'get' ) {
					return '';
				} else {
					return $newText;
				}
			} else {
				if ( $mode === 'get' ) {
					return $newText;
				} else {
					return $text;
				}
			}
		}

		# Preprocess the text
		$root = $this->preprocessToDom( $text, $flags );

		# <h> nodes indicate section breaks
		# They can only occur at the top level, so we can find them by iterating the root's children
		$node = $root->getFirstChild();

		# Find the target section
		if ( $sectionIndex == 0 ) {
			# Section zero doesn't nest, level=big
			$targetLevel = 1000;
		} else {
			while ( $node ) {
				if ( $node->getName() === 'h' ) {
					$bits = $node->splitHeading();
					if ( $bits['i'] == $sectionIndex ) {
						$targetLevel = $bits['level'];
						break;
					}
				}
				if ( $mode === 'replace' ) {
					$outText .= $frame->expand( $node, PPFrame::RECOVER_ORIG );
				}
				$node = $node->getNextSibling();
			}
		}

		if ( !$node ) {
			# Not found
			if ( $mode === 'get' ) {
				return $newText;
			} else {
				return $text;
			}
		}

		# Find the end of the section, including nested sections
		do {
			if ( $node->getName() === 'h' ) {
				$bits = $node->splitHeading();
				$curLevel = $bits['level'];
				if ( $bits['i'] != $sectionIndex && $curLevel <= $targetLevel ) {
					break;
				}
			}
			if ( $mode === 'get' ) {
				$outText .= $frame->expand( $node, PPFrame::RECOVER_ORIG );
			}
			$node = $node->getNextSibling();
		} while ( $node );

		# Write out the remainder (in replace mode only)
		if ( $mode === 'replace' ) {
			# Output the replacement text
			# Add two newlines on -- trailing whitespace in $newText is conventionally
			# stripped by the editor, so we need both newlines to restore the paragraph gap
			# Only add trailing whitespace if there is newText
			if ( $newText != "" ) {
				$outText .= $newText . "\n\n";
			}

			while ( $node ) {
				$outText .= $frame->expand( $node, PPFrame::RECOVER_ORIG );
				$node = $node->getNextSibling();
			}
		}

		if ( is_string( $outText ) ) {
			# Re-insert stripped tags
			$outText = rtrim( $this->mStripState->unstripBoth( $outText ) );
		}

		return $outText;
	}

	/**
	 * This function returns the text of a section, specified by a number ($section).
	 * A section is text under a heading like == Heading == or \<h1\>Heading\</h1\>, or
	 * the first section before any such heading (section 0).
	 *
	 * If a section contains subsections, these are also returned.
	 *
	 * @param string $text text to look in
	 * @param string $section section identifier
	 * @param string $deftext default to return if section is not found
	 * @return string text of the requested section
	 */
	public function getSection( $text, $section, $deftext = '' ) {
		return $this->extractSections( $text, $section, "get", $deftext );
	}

	/**
	 * This function returns $oldtext after the content of the section
	 * specified by $section has been replaced with $text. If the target
	 * section does not exist, $oldtext is returned unchanged.
	 *
	 * @param string $oldtext former text of the article
	 * @param int $section section identifier
	 * @param string $text replacing text
	 * @return String: modified text
	 */
	public function replaceSection( $oldtext, $section, $text ) {
		return $this->extractSections( $oldtext, $section, "replace", $text );
	}

	/**
	 * Get the ID of the revision we are parsing
	 *
	 * @return Mixed: integer or null
	 */
	function getRevisionId() {
		return $this->mRevisionId;
	}

	/**
	 * Get the revision object for $this->mRevisionId
	 *
	 * @return Revision|null either a Revision object or null
	 * @since 1.23 (public since 1.23)
	 */
	public function getRevisionObject() {
		if ( !is_null( $this->mRevisionObject ) ) {
			return $this->mRevisionObject;
		}
		if ( is_null( $this->mRevisionId ) ) {
			return null;
		}

		$this->mRevisionObject = Revision::newFromId( $this->mRevisionId );
		return $this->mRevisionObject;
	}

	/**
	 * Get the timestamp associated with the current revision, adjusted for
	 * the default server-local timestamp
	 */
	function getRevisionTimestamp() {
		if ( is_null( $this->mRevisionTimestamp ) ) {
			wfProfileIn( __METHOD__ );

			global $wgContLang;

			$revObject = $this->getRevisionObject();
			$timestamp = $revObject ? $revObject->getTimestamp() : wfTimestampNow();

			# The cryptic '' timezone parameter tells to use the site-default
			# timezone offset instead of the user settings.
			#
			# Since this value will be saved into the parser cache, served
			# to other users, and potentially even used inside links and such,
			# it needs to be consistent for all visitors.
			$this->mRevisionTimestamp = $wgContLang->userAdjust( $timestamp, '' );

			wfProfileOut( __METHOD__ );
		}
		return $this->mRevisionTimestamp;
	}

	/**
	 * Get the name of the user that edited the last revision
	 *
	 * @return String: user name
	 */
	function getRevisionUser() {
		if ( is_null( $this->mRevisionUser ) ) {
			$revObject = $this->getRevisionObject();

			# if this template is subst: the revision id will be blank,
			# so just use the current user's name
			if ( $revObject ) {
				$this->mRevisionUser = $revObject->getUserText();
			} elseif ( $this->ot['wiki'] || $this->mOptions->getIsPreview() ) {
				$this->mRevisionUser = $this->getUser()->getName();
			}
		}
		return $this->mRevisionUser;
	}

	/**
	 * Get the size of the revision
	 *
	 * @return int|null revision size
	 */
	function getRevisionSize() {
		if ( is_null( $this->mRevisionSize ) ) {
			$revObject = $this->getRevisionObject();

			# if this variable is subst: the revision id will be blank,
			# so just use the parser input size, because the own substituation
			# will change the size.
			if ( $revObject ) {
				$this->mRevisionSize = $revObject->getSize();
			} elseif ( $this->ot['wiki'] || $this->mOptions->getIsPreview() ) {
				$this->mRevisionSize = $this->mInputSize;
			}
		}
		return $this->mRevisionSize;
	}

	/**
	 * Mutator for $mDefaultSort
	 *
	 * @param string $sort New value
	 */
	public function setDefaultSort( $sort ) {
		$this->mDefaultSort = $sort;
		$this->mOutput->setProperty( 'defaultsort', $sort );
	}

	/**
	 * Accessor for $mDefaultSort
	 * Will use the empty string if none is set.
	 *
	 * This value is treated as a prefix, so the
	 * empty string is equivalent to sorting by
	 * page name.
	 *
	 * @return string
	 */
	public function getDefaultSort() {
		if ( $this->mDefaultSort !== false ) {
			return $this->mDefaultSort;
		} else {
			return '';
		}
	}

	/**
	 * Accessor for $mDefaultSort
	 * Unlike getDefaultSort(), will return false if none is set
	 *
	 * @return string or false
	 */
	public function getCustomDefaultSort() {
		return $this->mDefaultSort;
	}

	/**
	 * Try to guess the section anchor name based on a wikitext fragment
	 * presumably extracted from a heading, for example "Header" from
	 * "== Header ==".
	 *
	 * @param $text string
	 *
	 * @return string
	 */
	public function guessSectionNameFromWikiText( $text ) {
		# Strip out wikitext links(they break the anchor)
		$text = $this->stripSectionName( $text );
		$text = Sanitizer::normalizeSectionNameWhitespace( $text );
		return '#' . Sanitizer::escapeId( $text, 'noninitial' );
	}

	/**
	 * Same as guessSectionNameFromWikiText(), but produces legacy anchors
	 * instead.  For use in redirects, since IE6 interprets Redirect: headers
	 * as something other than UTF-8 (apparently?), resulting in breakage.
	 *
	 * @param string $text The section name
	 * @return string An anchor
	 */
	public function guessLegacySectionNameFromWikiText( $text ) {
		# Strip out wikitext links(they break the anchor)
		$text = $this->stripSectionName( $text );
		$text = Sanitizer::normalizeSectionNameWhitespace( $text );
		return '#' . Sanitizer::escapeId( $text, array( 'noninitial', 'legacy' ) );
	}

	/**
	 * Strips a text string of wikitext for use in a section anchor
	 *
	 * Accepts a text string and then removes all wikitext from the
	 * string and leaves only the resultant text (i.e. the result of
	 * [[User:WikiSysop|Sysop]] would be "Sysop" and the result of
	 * [[User:WikiSysop]] would be "User:WikiSysop") - this is intended
	 * to create valid section anchors by mimicing the output of the
	 * parser when headings are parsed.
	 *
	 * @param string $text text string to be stripped of wikitext
	 * for use in a Section anchor
	 * @return string Filtered text string
	 */
	public function stripSectionName( $text ) {
		# Strip internal link markup
		$text = preg_replace( '/\[\[:?([^[|]+)\|([^[]+)\]\]/', '$2', $text );
		$text = preg_replace( '/\[\[:?([^[]+)\|?\]\]/', '$1', $text );

		# Strip external link markup
		# @todo FIXME: Not tolerant to blank link text
		# I.E. [http://www.mediawiki.org] will render as [1] or something depending
		# on how many empty links there are on the page - need to figure that out.
		$text = preg_replace( '/\[(?i:' . $this->mUrlProtocols . ')([^ ]+?) ([^[]+)\]/', '$2', $text );

		# Parse wikitext quotes (italics & bold)
		$text = $this->doQuotes( $text );

		# Strip HTML tags
		$text = StringUtils::delimiterReplace( '<', '>', '', $text );
		return $text;
	}

	/**
	 * strip/replaceVariables/unstrip for preprocessor regression testing
	 *
	 * @param $text string
	 * @param $title Title
	 * @param $options ParserOptions
	 * @param $outputType int
	 *
	 * @return string
	 */
	function testSrvus( $text, Title $title, ParserOptions $options, $outputType = self::OT_HTML ) {
		$this->startParse( $title, $options, $outputType, true );

		$text = $this->replaceVariables( $text );
		$text = $this->mStripState->unstripBoth( $text );
		$text = Sanitizer::removeHTMLtags( $text );
		return $text;
	}

	/**
	 * @param $text string
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	function testPst( $text, Title $title, ParserOptions $options ) {
		return $this->preSaveTransform( $text, $title, $options->getUser(), $options );
	}

	/**
	 * @param $text
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	function testPreprocess( $text, Title $title, ParserOptions $options ) {
		return $this->testSrvus( $text, $title, $options, self::OT_PREPROCESS );
	}

	/**
	 * Call a callback function on all regions of the given text that are not
	 * inside strip markers, and replace those regions with the return value
	 * of the callback. For example, with input:
	 *
	 *  aaa<MARKER>bbb
	 *
	 * This will call the callback function twice, with 'aaa' and 'bbb'. Those
	 * two strings will be replaced with the value returned by the callback in
	 * each case.
	 *
	 * @param $s string
	 * @param $callback
	 *
	 * @return string
	 */
	function markerSkipCallback( $s, $callback ) {
		$i = 0;
		$out = '';
		while ( $i < strlen( $s ) ) {
			$markerStart = strpos( $s, $this->mUniqPrefix, $i );
			if ( $markerStart === false ) {
				$out .= call_user_func( $callback, substr( $s, $i ) );
				break;
			} else {
				$out .= call_user_func( $callback, substr( $s, $i, $markerStart - $i ) );
				$markerEnd = strpos( $s, self::MARKER_SUFFIX, $markerStart );
				if ( $markerEnd === false ) {
					$out .= substr( $s, $markerStart );
					break;
				} else {
					$markerEnd += strlen( self::MARKER_SUFFIX );
					$out .= substr( $s, $markerStart, $markerEnd - $markerStart );
					$i = $markerEnd;
				}
			}
		}
		return $out;
	}

	/**
	 * Remove any strip markers found in the given text.
	 *
	 * @param $text Input string
	 * @return string
	 */
	function killMarkers( $text ) {
		return $this->mStripState->killMarkers( $text );
	}

	/**
	 * Save the parser state required to convert the given half-parsed text to
	 * HTML. "Half-parsed" in this context means the output of
	 * recursiveTagParse() or internalParse(). This output has strip markers
	 * from replaceVariables (extensionSubstitution() etc.), and link
	 * placeholders from replaceLinkHolders().
	 *
	 * Returns an array which can be serialized and stored persistently. This
	 * array can later be loaded into another parser instance with
	 * unserializeHalfParsedText(). The text can then be safely incorporated into
	 * the return value of a parser hook.
	 *
	 * @param $text string
	 *
	 * @return array
	 */
	function serializeHalfParsedText( $text ) {
		wfProfileIn( __METHOD__ );
		$data = array(
			'text' => $text,
			'version' => self::HALF_PARSED_VERSION,
			'stripState' => $this->mStripState->getSubState( $text ),
			'linkHolders' => $this->mLinkHolders->getSubArray( $text )
		);
		wfProfileOut( __METHOD__ );
		return $data;
	}

	/**
	 * Load the parser state given in the $data array, which is assumed to
	 * have been generated by serializeHalfParsedText(). The text contents is
	 * extracted from the array, and its markers are transformed into markers
	 * appropriate for the current Parser instance. This transformed text is
	 * returned, and can be safely included in the return value of a parser
	 * hook.
	 *
	 * If the $data array has been stored persistently, the caller should first
	 * check whether it is still valid, by calling isValidHalfParsedText().
	 *
	 * @param array $data Serialized data
	 * @throws MWException
	 * @return String
	 */
	function unserializeHalfParsedText( $data ) {
		if ( !isset( $data['version'] ) || $data['version'] != self::HALF_PARSED_VERSION ) {
			throw new MWException( __METHOD__ . ': invalid version' );
		}

		# First, extract the strip state.
		$texts = array( $data['text'] );
		$texts = $this->mStripState->merge( $data['stripState'], $texts );

		# Now renumber links
		$texts = $this->mLinkHolders->mergeForeign( $data['linkHolders'], $texts );

		# Should be good to go.
		return $texts[0];
	}

	/**
	 * Returns true if the given array, presumed to be generated by
	 * serializeHalfParsedText(), is compatible with the current version of the
	 * parser.
	 *
	 * @param $data Array
	 *
	 * @return bool
	 */
	function isValidHalfParsedText( $data ) {
		return isset( $data['version'] ) && $data['version'] == self::HALF_PARSED_VERSION;
	}

	/**
	 * Parsed a width param of imagelink like 300px or 200x300px
	 *
	 * @param $value String
	 *
	 * @return array
	 * @since 1.20
	 */
	public function parseWidthParam( $value ) {
		$parsedWidthParam = array();
		if ( $value === '' ) {
			return $parsedWidthParam;
		}
		$m = array();
		# (bug 13500) In both cases (width/height and width only),
		# permit trailing "px" for backward compatibility.
		if ( preg_match( '/^([0-9]*)x([0-9]*)\s*(?:px)?\s*$/', $value, $m ) ) {
			$width = intval( $m[1] );
			$height = intval( $m[2] );
			$parsedWidthParam['width'] = $width;
			$parsedWidthParam['height'] = $height;
		} elseif ( preg_match( '/^[0-9]*\s*(?:px)?\s*$/', $value ) ) {
			$width = intval( $value );
			$parsedWidthParam['width'] = $width;
		}
		return $parsedWidthParam;
	}
}