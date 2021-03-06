<?php

namespace SMW\Tests;

use SMW\ApplicationFactory;
use SMW\Setup;

/**
 * @covers \SMW\Setup
 *
 * @group SMW
 * @group SMWExtension
 *
 * @group medium
 *
 * @license GNU GPL v2+
 * @since 1.9
 *
 * @author mwjames
 */
class SetupTest extends \PHPUnit_Framework_TestCase {

	private $applicationFactory;
	private $defaultConfig;

	protected function setUp() {
		parent::setUp();

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$store->expects( $this->any() )
			->method( 'getProperties' )
			->will( $this->returnValue( array() ) );

		$store->expects( $this->any() )
			->method( 'getInProperties' )
			->will( $this->returnValue( array() ) );

		$language = $this->getMockBuilder( '\Language' )
			->disableOriginalConstructor()
			->getMock();

		$this->applicationFactory = ApplicationFactory::getInstance();
		$this->applicationFactory->registerObject( 'Store', $store );

		$this->defaultConfig = array(
			'smwgCacheType' => CACHE_NONE,
			'smwgNamespacesWithSemanticLinks' => array(),
			'smwgEnableUpdateJobs' => false,
			'wgNamespacesWithSubpages' => array(),
			'wgExtensionAssetsPath'    => false,
			'smwgResourceLoaderDefFiles' => [],
			'wgResourceModules' => array(),
			'wgScriptPath'      => '/Foo',
			'wgServer'          => 'http://example.org',
			'wgVersion'         => '1.21',
			'wgLanguageCode'    => 'en',
			'wgLang'            => $language,
			'IP'                => 'Foo',
			'smwgSemanticsEnabled' => true
		);

		foreach ( $this->defaultConfig as $key => $value ) {
			$this->applicationFactory->getSettings()->set( $key, $value );
		}
	}

	protected function tearDown() {
		$this->applicationFactory->clear();

		parent::tearDown();
	}

	public function testCanConstruct() {

		$applicationFactory = $this->getMockBuilder( '\SMW\ApplicationFactory' )
			->disableOriginalConstructor()
			->getMock();

		$this->assertInstanceOf(
			Setup::class,
			new Setup( $applicationFactory )
		);
	}

	public function testResourceModules() {

		$config = $this->defaultConfig;
		$config['smwgResourceLoaderDefFiles'] = $GLOBALS['smwgResourceLoaderDefFiles'];

		$instance = new Setup( $this->applicationFactory );
		$instance->init( $config, '' );

		$this->assertNotEmpty(
			$config['wgResourceModules']
		);
	}

	/**
	 * @dataProvider apiModulesDataProvider
	 */
	public function testGetAPIModules( $name ) {

		$vars = Setup::getAPIModules();

		$this->assertArrayHasKey(
			$name,
			$vars
		);
	}

	/**
	 * @dataProvider jobClassesDataProvider
	 */
	public function testRegisterJobClasses( $jobEntry, $setup ) {
		$this->assertArrayEntryExists( 'wgJobClasses', $jobEntry, $setup );
	}

	/**
	 * @dataProvider specialPageDataProvider
	 */
	public function testInitSpecialPageList( $name ) {

		$vars = [];

		Setup::initSpecialPageList( $vars );

		$this->assertArrayHasKey(
			$name,
			$vars
		);
	}

	public function testRegisterDefaultRightsUserGroupPermissions() {

		$config = $this->defaultConfig;

		$instance = new Setup( $this->applicationFactory );
		$instance->init( $config, 'Foo' );

		$this->assertNotEmpty(
			$config['wgAvailableRights']
		);

		$this->assertTrue(
			$config['wgGroupPermissions']['sysop']['smw-admin']
		);

		$this->assertTrue(
			$config['wgGroupPermissions']['smwcurator']['smw-patternedit']
		);

		$this->assertTrue(
			$config['wgGroupPermissions']['smwcurator']['smw-pageedit']
		);

		$this->assertTrue(
			$config['wgGroupPermissions']['smwadministrator']['smw-admin']
		);
	}

	public function testNoResetOfAlreadyRegisteredGroupPermissions() {

		// Avoid re-setting permissions, refs #1137
		$localConfig['wgGroupPermissions']['sysop']['smw-admin'] = false;
		$localConfig['wgGroupPermissions']['smwadministrator']['smw-admin'] = false;

		$localConfig = array_merge(
			$this->defaultConfig,
			$localConfig
		);

		$instance = new Setup( $this->applicationFactory );
		$instance->init( $localConfig, 'Foo' );

		$this->assertFalse(
			$localConfig['wgGroupPermissions']['sysop']['smw-admin']
		);

		$this->assertFalse(
			$localConfig['wgGroupPermissions']['smwadministrator']['smw-admin']
		);

	}

	public function testRegisterParamDefinitions() {

		$config = $this->defaultConfig;

		$config['wgParamDefinitions']['smwformat'] = '';

		$this->assertEmpty(
			$config['wgParamDefinitions']['smwformat']
		);

		$instance = new Setup( $this->applicationFactory );
		$instance->init( $config, 'Foo' );

		$this->assertNotEmpty(
			$config['wgParamDefinitions']['smwformat']
		);
	}

	public function testRegisterFooterIcon() {

		$config = $this->defaultConfig;

		$config['wgFooterIcons']['poweredby'] = array();

		$instance = new Setup( $this->applicationFactory );
		$instance->init( $config, 'Foo' );

		$this->assertNotEmpty(
			$config['wgFooterIcons']['poweredby']['semanticmediawiki']
		);
	}

	/**
	 * @return array
	 */
	public function specialPageDataProvider() {

		$specials = array(
			'Ask',
			'Browse',
			'PageProperty',
			'SearchByProperty',
			'SMWAdmin',
			'Concepts',
			'ExportRDF',
			'Types',
			'URIResolver',
			'Properties',
			'UnusedProperties',
			'WantedProperties',
			'DeferredRequestDispatcher',
			'ProcessingErrorList',
			'PropertyLabelSimilarity'
		);

		return $this->buildDataProvider( 'wgSpecialPages', $specials, '' );
	}

	/**
	 * @return array
	 */
	public function jobClassesDataProvider() {

		$jobs = array(
			'SMW\UpdateJob',
			'SMW\RefreshJob',
			'SMW\UpdateDispatcherJob',
			'SMW\ParserCachePurgeJob',
			'SMW\FulltextSearchTableUpdateJob',
			'SMW\EntityIdDisposerJob',
			'SMW\PropertyStatisticsRebuildJob',
			'SMW\FulltextSearchTableRebuildJob',
			'SMW\ChangePropagationDispatchJob',
			'SMW\ChangePropagationUpdateJob',
			'SMW\ChangePropagationClassUpdateJob',

			// Legacy
			'SMWUpdateJob',
			'SMWRefreshJob',
		);

		return $this->buildDataProvider( 'wgJobClasses', $jobs, '' );
	}

	/**
	 * @return array
	 */
	public function apiModulesDataProvider() {

		$modules = array(
			'ask',
			'smwinfo',
			'askargs',
			'browsebysubject',
			'browsebyproperty'
		);

		return $this->buildDataProvider( 'wgAPIModules', $modules, '' );
	}

	private function assertArrayEntryExists( $target, $entry, $config, $type = 'class' ) {

		$config = $config + $this->defaultConfig;

		$this->assertEmpty(
			$config[$target][$entry],
			"Asserts that {$entry} is empty"
		);

		$instance = new Setup( $this->applicationFactory );
		$instance->init( $config, 'Foo' );

		$this->assertNotEmpty( $config[$target][$entry] );

		switch ( $type ) {
			case 'class':
				$this->assertTrue( class_exists( $config[$target][$entry] ) );
				break;
			case 'file':
				$this->assertTrue( file_exists( $config[$target][$entry] ) );
				break;
		}
	}

	/**
	 * @return array
	 */
	private function buildDataProvider( $id, $definitions, $default ) {

		$provider = array();

		foreach ( $definitions as $definition ) {
			$provider[] = array(
				$definition,
				array( $id => array( $definition => $default ) ),
			);
		}

		return $provider;
	}

}
