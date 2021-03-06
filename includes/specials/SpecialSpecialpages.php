<?php
/**
 * Implements Special:Specialpages
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
 * @ingroup SpecialPage
 */

/**
 * A special page that lists special pages
 *
 * @ingroup SpecialPage
 */
class SpecialSpecialpages extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'Specialpages' );
	}

	function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();
		$this->outputHeader();
		$out->allowClickjacking();
		$out->addModuleStyles( 'mediawiki.special' );

		$groups = $this->getPageGroups();

		if ( $groups === false ) {
			return;
		}

		$this->outputPageList( $groups );
	}

	private function getPageGroups() {
		global $wgSortSpecialPages;

		$pages = SpecialPageFactory::getUsablePages( $this->getUser() );

		if ( !count( $pages ) ) {
			# Yeah, that was pointless. Thanks for coming.
			return false;
		}

		/** Put them into a sortable array */
		$groups = array();
		/** @var SpecialPage $page */
		foreach ( $pages as $page ) {
			if ( $page->isListed() ) {
				$group = $page->getFinalGroupName();
				if ( !isset( $groups[$group] ) ) {
					$groups[$group] = array();
				}
				$groups[$group][$page->getDescription()] = array(
					$page->getPageTitle(),
					$page->isRestricted(),
					$page->isCached()
				);
			}
		}

		/** Sort */
		if ( $wgSortSpecialPages ) {
			foreach ( $groups as $group => $sortedPages ) {
				ksort( $groups[$group] );
			}
		}

		/** Always move "other" to end */
		if ( array_key_exists( 'other', $groups ) ) {
			$other = $groups['other'];
			unset( $groups['other'] );
			$groups['other'] = $other;
		}

		return $groups;
	}

	private function outputPageList( $groups ) {
		$out = $this->getOutput();

		$includesRestrictedPages = false;
		$includesCachedPages = false;

		foreach ( $groups as $group => $sortedPages ) {
			$total = count( $sortedPages );
			$middle = ceil( $total / 2 );
			$count = 0;

			// JRS 12/5/13 Added html to match redesign styling
			$out->addHTML( Html::openElement( 'div', array( 'class' => 'section' ) ) );
			$out->wrapWikiMsg( "<h2 class=\"mw-specialpagesgroup\" id=\"mw-specialpagesgroup-$group\">$1</h2>\n", "specialpages-group-$group" );
			$out->addHTML( Html::openElement( 'div', array( 'class' => 'section_text' ) ) );
			$out->addHTML(
				Html::openElement( 'table', array( 'style' => 'width:100%;', 'class' => 'mw-specialpages-table' ) ) . "\n" .
				Html::openElement( 'tr' ) . "\n" .
				Html::openElement( 'td', array( 'style' => 'width:30%;vertical-align:top' ) ) . "\n" .
				Html::openElement( 'ul' ) . "\n"
			);
			foreach ( $sortedPages as $desc => $specialpage ) {
				list( $title, $restricted, $cached ) = $specialpage;

				$pageClasses = array();
				if ( $cached ) {
					$includesCachedPages = true;
					$pageClasses[] = 'mw-specialpagecached';
				}
				if ( $restricted ) {
					$includesRestrictedPages = true;
					$pageClasses[] = 'mw-specialpagerestricted';
				}

				$link = Linker::linkKnown( $title, htmlspecialchars( $desc ) );
				$out->addHTML( Html::rawElement( 'li', array( 'class' => implode( ' ', $pageClasses ) ), $link ) . "\n" );

				# Split up the larger groups
				$count++;
				if ( $total > 3 && $count == $middle ) {
					$out->addHTML(
						Html::closeElement( 'ul' ) . Html::closeElement( 'td' ) .
						Html::element( 'td', array( 'style' => 'width:10%' ), '' ) .
						Html::openElement( 'td', array( 'style' => 'width:30%' ) ) . Html::openElement( 'ul' ) . "\n"
					);
				}
			}
			$out->addHTML(
				Html::closeElement( 'ul' ) . Html::closeElement( 'td' ) .
				Html::element( 'td', array( 'style' => 'width:30%' ), '' ) .
				Html::closeElement( 'tr' ) . Html::closeElement( 'table' ) . "\n"
			);
			// JRS 12/5/13 Added html to match redesign styling
			$out->addHtml( Html::closeElement( 'div' ) );
			$out->addHtml( Html::closeElement( 'div' ) );
		}

		if ( $includesRestrictedPages || $includesCachedPages ) {
			//SAC 03/12/14 Commented out to match Jordan's change (also because it looked weird)
			//$out->wrapWikiMsg( "<h2 class=\"mw-specialpages-note-top\">$1</h2>", 'specialpages-note-top' );
			// JRS 12/5/13 Commented out to match styling
			//$out->wrapWikiMsg( "<div class=\"mw-specialpages-notes\">\n$1\n</div>", 'specialpages-note' );
		}
	}
}
