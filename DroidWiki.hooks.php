<?php

class DroidWikiHooks {
	private static $adAlreadyAdded = false;

	const ADSENSE_AD_CLIENT = 'ca-pub-4622825295514928';

	const ADSENSE_AD_PUSH_CODE = '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';

	public static function onSkinTemplateOutputPageBeforeExec(
		SkinTemplate &$sk, QuickTemplate &$tpl
	) {
		if (
			!self::$adAlreadyAdded && $sk->getSkinName() === 'vector' &&
			self::checkShowAd( $sk, 'right' )
		) {
			self::$adAlreadyAdded = true;
			self::addAdCodeToBodyText( $tpl );
		}

		$lockedPages = array(
			SpecialPage::getTitleFor( 'MobileDiff' )->getRootText(),
		);
		// this is the mobile web ad block
		if (
			ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			MobileContext::singleton()->shouldDisplayMobileView() &&
		    !in_array( $sk->getTitle()->getRootText(), $lockedPages )
		) {
			$tpl->data['bodytext'] =
				$tpl->data['bodytext'] .
				Html::openElement( 'div', [ 'id' => 'ad-cat', 'class' => 'adsbygoogleCategory' ] ) .
				self::getAdSenseScriptTag() .
				self::getAdSenseINSBlock( '6645983899', 'horizontal', 'display:block' ) .
				self::ADSENSE_AD_PUSH_CODE . Html::closeElement( 'div' );
		}


		$devDestination =
			Skin::makeInternalOrExternalUrl( $sk->msg( 'droidwiki-developers-url' )
				->inContentLanguage()
				->text() );
		$devLink = Html::element(
			'a',
			[ 'href' => $devDestination ],
			$sk->msg( 'droidwiki-developers' )->text()
		);
		$tpl->set( 'developers', $devLink );
		$tpl->data['footerlinks']['places'][] = 'developers';
		$cookieDestination =
			Skin::makeInternalOrExternalUrl( $sk->msg( 'droidwiki-imprint-url' )
				->inContentLanguage()
				->text() );
		$cookieLink = Html::element(
			'a',
			[ 'href' => $cookieDestination ],
			$sk->msg( 'droidwiki-imprint' )->text()
		);
		$tpl->set( 'imprint', $cookieLink );
		$tpl->data['footerlinks']['places'][] = 'imprint';

		return true;
	}

	public static function onSkinAfterContent( &$data, Skin $sk ) {
		if ( !self::checkShowAd( $sk, 'bottom' ) ) {
			return;
		}

		// the desktop ad block is slightly different
		$data = Html::openElement( 'div', array(
				'class' => 'adsbygoogleCategory',
			) ) .
		    self::getAdSenseScriptTag() .
		    self::getAdSenseINSBlock( '6216454699', 'auto', 'display:block' ) .
		    self::ADSENSE_AD_PUSH_CODE .
		    Html::closeElement( 'div' );
	}

	public static function checkShowAd( SkinTemplate $sk, $position = 'right' ) {
		global $wgNoAdSites, $wgDroidWikiAdDisallowedNamespaces, $wgDroidWikiNoAdSites;

		if ( is_array( $wgNoAdSites ) ) {
			$wgDroidWikiNoAdSites = array_merge( $wgDroidWikiNoAdSites, $wgNoAdSites );
		}

		$urlTitle = $sk->getRequest()->getText( 'title' );
		if ( $wgDroidWikiNoAdSites && in_array( $urlTitle, $wgDroidWikiNoAdSites ) ) {
			return false;
		}

		if ( in_array( $sk->getTitle()->getNamespace(), $wgDroidWikiAdDisallowedNamespaces ) ) {
			return false;
		}

		if ( !$sk->getOutput()->isArticleRelated() ) {
			return false;
		}

		$loggedIn = $sk->getUser()->isLoggedIn();
		switch ( $position ) {
			case 'right':
				return !$loggedIn;
				break;
			case 'bottom':
				return $loggedIn;
				break;
			default:
				return false;
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $sk ) {
		$skinModules = [];
		if ( $sk->getSkinName() === 'vector' && self::checkShowAd( $sk ) ) {
			$skinModules[] = 'ext.DroidWiki.adstyle';
		}
		if ( $out->getTitle()->isMainPage() ) {
			$skinModules[] = 'ext.DroidWiki.mainpage.styles';
		}
		$out->addModuleStyles( $skinModules );
		$out->addModules( [ 'ext.DroidWiki.adstyle.category' ] );

		$out->addHeadItem( 'google_ad_sitelevel', self::getAdSenseScriptTag() );
		$out->addHeadItem( 'google_ad_sitelevel_config', '<script>
		(adsbygoogle = window.adsbygoogle || []).push({
			google_ad_client: "ca-pub-4622825295514928",
			enable_page_level_ads: true
		});
		</script>' );
	}

	public static function onGetSoftwareInfo( &$software ) {
		global $IP;

		$gitInfo = new GitInfo( "$IP/../mw-config/mw-config" );
		if ( $gitInfo ) {
			$software['[http://github.com/droidwiki/operations-mediawiki-config.git MWC]'] =
				'[' . $gitInfo->getHeadViewUrl() . ' ' . substr( $gitInfo->getHeadSHA1(), 0, 7 ) .
				']';
		}
	}

	public static function onSkinCopyrightFooter( $title, $type, &$msg, &$link ) {
		global $wgRightsUrl;

		if ( strpos( $wgRightsUrl, 'creativecommons.org/licenses/by-sa/3.0' ) !== false ) {
			if ( $type !== 'history' ) {
				$msg = 'droidwiki-copyright';
			}
		}

		return true;
	}

	public static function onPageContentLanguage( Title $title, Language &$pageLang, $userLang ) {
		// FIXME: temporary hack for T121666, this shouldn't be needed
		if ( strpos( $title->getText(), 'Android Training/' ) !== false ) {
			$pageLang = wfGetLangObj( 'en' );
		}
	}

	/**
	 * SkinTemplateGetLanguageLink hook handler, which adds the interwiki-www css class to the interwiki-de interlanguage link,
	 * which should indicate to the ContentTranslation extension, that the main droidwiki language (german, with interwiki link
	 * de but mapped to www for ContentTranslation) is already translated.
	 */
	public static function onSkinTemplateGetLanguageLink(
		&$languageLink, $languageLinkTitle, Title $title, OutputPage $out
	) {
		if ( strpos( $languageLink['class'], 'interwiki-de' ) === - 1 ) {
			return;
		}

		$languageLink['class'] .= ' interwiki-www';
	}

	private static function addAdCodeToBodyText( QuickTemplate &$tpl ) {
		$adContent = Html::openElement( 'aside', [
				'id' => 'adContent',
				'class' => 'mw-body-rightcontainer',
			] ) .
		    self::getAdSenseScriptTag() .
		    self::getAdSenseINSBlock(
		    	'8031689899',
			    null,
			    'display:inline-block;width:160px;height:600px'
		    ) .
		    self::ADSENSE_AD_PUSH_CODE .
		    Html::closeElement( 'aside' );

		$tpl->data['bodytext'] = $adContent . $tpl->data['bodytext'];
	}

	private static function getAdSenseScriptTag() {
		return Html::element( 'script', [
			'async',
			'src' => '//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
		] );
	}

	private static function getAdSenseINSBlock( $slot, $adFormat = null, $style = '' ) {
		$attribs = [
			'class' => 'adsbygoogle',
			'style' => $style,
			'data-ad-client' => self::ADSENSE_AD_CLIENT,
			'data-ad-slot' => $slot,
		];

		if ( $adFormat ) {
			$attribs['data-ad-format'] = $adFormat;
		}

		return Html::element( 'ins', $attribs );
	}
}
