<?php

class SpecialUndeleteBatch extends SpecialPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'UndeleteBatch'/*class*/, 'undeletebatch'/*restriction*/ );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 * @throws UserBlockedError
	 * @return void
	 */
	public function execute( $par ) {
		# Check permissions
		$user = $this->getUser();
		if ( !$user->isAllowed( 'undeletebatch' ) ) {
			$this->displayRestrictionError();
			return;
		}

		# Show a message if the database is in read-only mode
		if ( wfReadOnly() ) {
			$this->getOutput()->readOnlyPage();
			return;
		}

		# If user is blocked, s/he doesn't need to access this page
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $this->getUser()->mBlock );
		}

		$this->getOutput()->setPageTitle( $this->msg( 'undeletebatch-title' ) );
		$cSF = new UndeleteBatchForm( $par, $this->getTitle(), $this->getContext() );

		$request = $this->getRequest();
		$action = $request->getVal( 'action' );
		if ( 'success' == $action ) {
			/* do something */
		} elseif ( $request->wasPosted() && 'submit' == $action &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
			$cSF->doSubmit();
		} else {
			$cSF->showForm();
		}
	}

	/**
	 * Adds a link to Special:UndeleteBatch within the page
	 * Special:AdminLinks, if the 'AdminLinks' extension is defined
	 */
	static function addToAdminLinks( &$admin_links_tree ) {
		$general_section = $admin_links_tree->getSection( wfMessage( 'adminlinks_general' )->text() );
		$extensions_row = $general_section->getRow( 'extensions' );
		if ( is_null( $extensions_row ) ) {
			$extensions_row = new ALRow( 'extensions' );
			$general_section->addRow( $extensions_row );
		}
		$extensions_row->addItem( ALItem::newFromSpecialPage( 'UndeleteBatch' ) );
		return true;
	}
}

/* the form for undeleting pages */
class UndeleteBatchForm {
	var $mUser, $mPage, $mFile, $mFileTemp;

	/**
	 * @var IContextSource|RequestContext
	 */
	protected $context;

	/**
	 * @var Title
	 */
	protected $title;

	/**
	 * @param $par
	 * @param $title
	 * @param $context IContextSource|RequestContext
	 */
	function __construct( $par, $title, $context ) {
		$this->context = $context;
		$request = $context->getRequest();

		$this->title = $title;
		$this->mMode = $request->getText( 'wpMode' );
		$this->mPage = $request->getText( 'wpPage' );
		$this->mReason = $request->getText( 'wpReason' );
		$this->mFile = $request->getFileName( 'wpFile' );
		$this->mFileTemp = $request->getFileTempName( 'wpFile' );
	}

	/**
	 * Show the form for undeleting pages
	 *
	 * @param $errorMessage mixed: error message or null if there's no error
	 */
	function showForm( $errorMessage = false ) {
		$out = $this->context->getOutput();

		if ( $errorMessage ) {
			$out->setSubtitle( $this->context->msg( 'formerror' ) );
			$out->wrapWikiMsg( "<p class='error'>$1</p>\n", $errorMessage );
		}

		$out->addWikiMsg( 'undeletebatch-help' );

		$tabindex = 1;

		$rows = array(

		array(
			Xml::label( $this->context->msg( 'undeletebatch-as' )->text(), 'wpMode' ),
			$this->userSelect( 'wpMode', ++$tabindex )->getHtml()
		),
		array(
			Xml::label( $this->context->msg( 'undeletebatch-page' )->text(), 'wpPage' ),
			$this->pagelistInput( 'wpPage', ++$tabindex )
		),
		array(
			$this->context->msg( 'undeletebatch-or' )->parse(),
			'&#160;'
		),
		array(
			Xml::label( $this->context->msg( 'undeletebatch-caption' )->text(), 'wpFile' ),
			$this->fileInput( 'wpFile', ++$tabindex )
		),
		array(
			'&#160;',
			$this->submitButton( 'wpundeletebatchSubmit', ++$tabindex )
		)

		);

		$form =

		Xml::openElement( 'form', array(
			'name' => 'undeletebatch',
			'enctype' => 'multipart/form-data',
			'method' => 'post',
			'action' => $this->title->getLocalUrl( array( 'action' => 'submit' ) ),
		) );

		$form .= '<table>';

		foreach( $rows as $row ) {
			list( $label, $input ) = $row;
			$form .= "<tr><td class='mw-label'>$label</td>";
			$form .= "<td class='mw-input'>$input</td></tr>";
		}

		$form .= '</table>';

		$form .= Html::Hidden( 'title', $this->title );
		$form .= Html::Hidden( 'wpEditToken', $this->context->getUser()->getEditToken() );
		$form .= '</form>';
		$out->addHTML( $form );
	}

	function userSelect( $name, $tabindex ) {
		$options = array(
			$this->context->msg( 'undeletebatch-select-script' )->text() => 'script',
			$this->context->msg( 'undeletebatch-select-yourself' )->text() => 'you'
		);

		$select = new XmlSelect( $name, $name );
		$select->setDefault( $this->mMode );
		$select->setAttribute( 'tabindex', $tabindex );
		$select->addOptions( $options );

		return $select;
	}

	function pagelistInput( $name, $tabindex ) {
		$params = array(
			'tabindex' => $tabindex,
			'name' => $name,
			'id' => $name,
			'cols' => 40,
			'rows' => 10
		);

		return Xml::element( 'textarea', $params, $this->mPage, false );
	}

	function fileInput( $name, $tabindex ) {
		$params = array(
			'type' => 'file',
			'tabindex' => $tabindex,
			'name' => $name,
			'id' => $name,
			'value' => $this->mFile
		);

		return Xml::element( 'input', $params );
	}

	function submitButton( $name, $tabindex ) {
		$params = array(
			'tabindex' => $tabindex,
			'name' => $name,
		);

		return Xml::submitButton( $this->context->msg( 'undeletebatch-delete' )->text(), $params );
	}

	/* wraps up multi undeletes */
	function undeleteBatch( $user = false, $line = '', $filename = null ) {
		global $wgUser;

		/* switch user if necessary */
		$OldUser = $wgUser;
		if ( 'script' == $this->mMode ) {
			$username = 'Delete page script';
			$wgUser = User::newFromName( $username );
			/* Create the user if necessary */
			if ( !$wgUser->getID() ) {
				$wgUser->addToDatabase();
			}
		}

		/* @todo run tests - run many tests */
		$dbw = wfGetDB( DB_MASTER );

			/* run through text and do all like it should be */
			$lines = explode( "\n", $line );
			foreach ( $lines as $single_page ) {
				/* explode and give me a reason */
				$page_data = explode( "|", trim( $single_page ) );
				if ( count( $page_data ) < 2 )
					$page_data[1] = '';
				$this->undeletePage( $page_data[0], $page_data[1], $dbw, false, 0, $OldUser );
			}


		/* restore user back */
		if ( 'script' == $this->mMode ) {
			$wgUser = $OldUser;
		}

		$link_back = Linker::linkKnown(
			$this->title,
			$this->context->msg( 'undeletebatch-link-back' )->escaped()
		);
		$this->context->getOutput()->addHTML( "<br /><b>" . $link_back . "</b>" );
	}

	/**
	 * Performs a single undelete
	 * @$mode String - singular/multi
	 * @$linennum Integer - mostly for informational reasons
	 * @param $line
	 * @param string $reason
	 * @param DatabaseBase $db
	 * @param bool $multi
	 * @param int $linenum
	 * @param null|User $user
	 * @return bool
	 */
	function undeletePage( $line, $reason = '', &$db, $multi = false, $linenum = 0, $user = null ) {
		global $wgUser;
		$page = Title::newFromText( $line );
			if ( is_null( $page ) ) { /* invalid title? */
				$this->context->getOutput()->addWikiMsg(
					'undeletebatch-omitting-invalid', $line );
			if ( !$multi ) {
				if ( !is_null( $user ) ) {
					$wgUser = $user;
				}
			}
			return false;
		}
		$archive = new PageArchive( $page );
		$archive->undelete( array(), $reason );
		return true;
	}

	/* on submit */
	function doSubmit() {
		$out = $this->context->getOutput();

		$out->setPageTitle( $this->context->msg( 'undeletebatch-title' ) );
		if ( !$this->mPage && !$this->mFileTemp ) {
			$this->showForm( 'undeletebatch-no-page' );
			return;
		}

		if ( $this->mPage ) {
			$out->setSubTitle( $this->context->msg( 'undeletebatch-processing-from-form' ) );
		} else {
			$out->setSubTitle( $this->context->msg( 'undeletebatch-processing-from-file' ) );
		}

		$this->undeleteBatch( $this->mUser, $this->mPage, $this->mFileTemp );
	}
}
