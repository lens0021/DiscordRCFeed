<?php
namespace MediaWiki\Extension\DiscordRCFeed;

use SpecialPage;
use Title;
use User;

class LinkRenderer {
	/** @var array */
	private $userTools;

	/** @var array */
	private $pageTools;

	/**
	 * @param array $userTools
	 * @param array $pageTools
	 */
	public function __construct( $userTools = [], $pageTools = [] ) {
		$this->userTools = $userTools;
		$this->pageTools = $pageTools;
	}

	/**
	 * Gets nice HTML text for user containing the link to user page and also links to user site,
	 * groups editing, talk and contribs pages if configured.
	 * @param User $user
	 * @return string
	 */
	public function getDiscordUserTextWithTools( User $user ): string {
		$rt = self::makeLink( $user->getUserPage()->getFullURL(), $user->getName() );
		if ( $this->userTools ) {
			$tools = [];
			foreach ( $this->userTools as $tool ) {
				if ( $tool['target'] == 'talk' ) {
					$link = $user->getTalkPage()->getFullURL();
				} else {
					$link = SpecialPage::getTitleFor( $tool['special'], $user->getName() )->getFullURL();
				}
				$text = isset( $tool['msg'] ) ? Util::msg( $tool['msg'] ) : $tool['text'];
				$tools[] = self::makeLink( $link, $text );
			}
			$rt .= ' ' . self::MakeNiceTools( $tools );
		}
		return $rt;
	}

	/**
	 * Gets nice HTML text for article containing the link to article page
	 * and also into edit, delete and article history pages.
	 * @param Title $title
	 * @param int|null $thisOldId
	 * @param int|null $lastOldId
	 * @return string
	 */
	public function getDiscordPageTextWithTools( Title $title, $thisOldId = null, $lastOldId = null ): string {
		$rt = self::makeLink( $title->getFullURL(), $title->getFullText() );
		if ( $this->pageTools ) {
			$tools = [];
			foreach ( $this->pageTools as $tool ) {
				$tools[] = self::makeLink( $title->getFullURL( $tool['query'] ),
					Util::msg( $tool['msg'] ) );
			}
			if ( $thisOldId && $lastOldId ) {
				$tools[] = self::makeLink( $title->getFullURL( "diff=$thisOldId&oldid=$lastOldId" ),
					Util::msg( 'diff' ) );
			}
			$rt .= ' ' . self::makeNiceTools( $tools );
		}
		return $rt;
	}

	/**
	 * @param string $wt wikitext to parse.
	 * @param User|null $user
	 * @return string text with Discord syntax.
	 */
	public function makeLinksClickable( string $wt, $user = null ): string {
		if ( $user ) {
			$name = $user->getName();
			if ( strpos( $wt, $name ) === 0 ) {
				$replacement = $this->getDiscordUserTextWithTools( $user );
				$wt = $replacement . substr( $wt, strlen( $name ) );
			}
		}
		if ( preg_match_all( '/\[\[([^|\]]+)\]\]/', $wt, $matches ) ) {
			foreach ( $matches[0] as $i => $match ) {
				$titleText = $matches[1][$i];
				$titleObj = Title::newFromText( $titleText );
				if ( !$titleObj ) {
					continue;
				}
				$replacement = $this->getDiscordPageTextWithTools( $titleObj );
				$wt = str_replace( $match, $replacement, $wt );
			}
		}
		if ( preg_match_all( '/\[\[([^|]+)\|([^\]]+)\]\]/', $wt, $matches ) ) {
			foreach ( $matches[0] as $i => $match ) {
				$titleObj = Title::newFromText( $matches[1][$i] );
				if ( !$titleObj ) {
					continue;
				}
				$label = $matches[2][$i];
				$replacement = self::makeLink( $titleObj->getFullURL(), $label );
				$wt = str_replace( $match, $replacement, $wt );
			}
		}

		return $wt;
	}

	/**
	 * @param string $target
	 * @param string $text
	 * @return string
	 */
	public static function makeLink( string $target, string $text ): string {
		if ( !$target ) {
			return $text;
		}
		$target = self::parseUrl( $target );
		return "[$text]($target)";
	}

	/**
	 * @param array $tools
	 * @return string
	 */
	private static function makeNiceTools( array $tools ): string {
		$tools = implode( Util::msg( 'pipe-separator' ), $tools );
		return Util::msg( 'parentheses', $tools );
	}

	/**
	 * Replaces some special characters on urls. This has to be done as Discord webhook api does not
	 * accept urlencoded text.
	 * @param string $url
	 * @return string
	 */
	private static function parseUrl( string $url ): string {
		foreach ( [
			' ' => '%20',
			'(' => '%28',
			')' => '%29',
		] as $search => $replace ) {
			$url = str_replace( $search, $replace, $url );
		}
		return $url;
	}
}
