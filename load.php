<?php

use SMW\NamespaceManager;
use SMW\ApplicationFactory;
use SMW\Setup;

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

/**
 * ExtensionRegistry only maps classes and functions after all extensions have
 * been queued from the LocalSettings.php resulting in DefaultSettings not being
 * loaded in-time.
 *
 * When changing the load order, please ensure that this function is run either
 * via Composer's autoloading or as part of your internal registration.
 */
SemanticMediaWiki::load();

/**
 * @codeCoverageIgnore
 */
class SemanticMediaWiki {

	/**
	 * @since 2.5
	 *
	 * @note It is expected that this function is loaded before LocalSettings.php
	 * to ensure that settings and global functions are available by the time
	 * the extension is activated.
	 */
	public static function load() {

		if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
			include_once __DIR__ . '/vendor/autoload.php';
		}

		include_once __DIR__ . '/src/Aliases.php';
		include_once __DIR__ . '/src/Defines.php';
		include_once __DIR__ . '/src/GlobalFunctions.php';

		foreach ( include __DIR__ . '/DefaultSettings.php' as $key => $value ) {
			if ( !isset( $GLOBALS[$key] ) ) {
				$GLOBALS[$key] = $value;
			}
		}

		// In case extension.json is being used, the succeeding steps will
		// be handled by the ExtensionRegistry
		self::initExtension();

		$GLOBALS['wgExtensionFunctions'][] = function() {
			self::onExtensionFunction();
		};
	}

	/**
	 * @since 2.4
	 */
	public static function initExtension() {

		define( 'SMW_VERSION', '3.0.0-alpha' );

		// Registration of the extension credits, see Special:Version.
		$GLOBALS['wgExtensionCredits']['semantic'][] = array(
			'path' => __FILE__,
			'name' => 'Semantic MediaWiki',
			'version' => SMW_VERSION,
			'author' => array(
				'[http://korrekt.org Markus Krötzsch]',
				'[https://www.mediawiki.org/wiki/User:Jeroen_De_Dauw Jeroen De Dauw]',
				'James Hong Kong',
				'[https://www.semantic-mediawiki.org/wiki/Contributors ...]'
				),
			'url' => 'https://www.semantic-mediawiki.org',
			'descriptionmsg' => 'smw-desc',
			'license-name'   => 'GPL-2.0-or-later'
		);

		// Registration point for required early registration
		Setup::initExtension( $GLOBALS );
	}

	/**
	 * Setup and initialization
	 *
	 * @note $wgExtensionFunctions variable is an array that stores
	 * functions to be called after most of MediaWiki initialization
	 * has finalized
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:$wgExtensionFunctions
	 *
	 * @since  1.9
	 */
	public static function onExtensionFunction() {

		// 3.x reverse the order to ensure that smwgMainCacheType is used
		// as main and smwgCacheType being deprecated with 3.x
		$GLOBALS['smwgMainCacheType'] = $GLOBALS['smwgCacheType'];

		$applicationFactory = ApplicationFactory::getInstance();

		$namespace = new NamespaceManager();
		$namespace->init( $GLOBALS );

		$setup = new Setup( $applicationFactory );
		$setup->init( $GLOBALS, __DIR__ );
	}

	/**
	 * @since 2.4
	 *
	 * @return string|null
	 */
	public static function getVersion() {
		return SMW_VERSION;
	}

	/**
	 * @since 2.4
	 *
	 * @return array
	 */
	public static function getEnvironment() {

		$store = '';

		if ( isset( $GLOBALS['smwgDefaultStore'] ) ) {
			$store = $GLOBALS['smwgDefaultStore'];
		};

		if ( strpos( strtolower( $store ), 'sparql' ) ) {
			$store = [ $store, strtolower( $GLOBALS['smwgSparqlRepositoryConnector'] ) ];
		}

		return array(
			'store' => $store,
			'db'    => isset( $GLOBALS['wgDBtype'] ) ? $GLOBALS['wgDBtype'] : 'N/A'
		);
	}

}
