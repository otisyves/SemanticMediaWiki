<?php

namespace SMW\Tests\ParserFunctions;

use ParserOutput;
use ReflectionClass;
use SMW\ApplicationFactory;
use SMW\ParserFunctions\AskParserFunction;
use SMW\Localizer;
use SMW\Tests\TestEnvironment;
use Title;

/**
 * @covers \SMW\ParserFunctions\AskParserFunction
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 1.9
 *
 * @author mwjames
 */
class AskParserFunctionTest extends \PHPUnit_Framework_TestCase {

	private $testEnvironment;
	private $semanticDataValidator;
	private $messageFormatter;
	private $circularReferenceGuard;
	private $expensiveFuncExecutionWatcher;

	protected function setUp() {
		parent::setUp();

		$this->testEnvironment = new TestEnvironment();
		$this->semanticDataValidator = $this->testEnvironment->getUtilityFactory()->newValidatorFactory()->newSemanticDataValidator();

		$this->testEnvironment->addConfiguration( 'smwgQueryProfiler', true );
		$this->testEnvironment->addConfiguration( 'smwgQMaxLimit', 1000 );

		$this->messageFormatter = $this->getMockBuilder( '\SMW\MessageFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$this->circularReferenceGuard = $this->getMockBuilder( '\SMW\Utils\CircularReferenceGuard' )
			->disableOriginalConstructor()
			->getMock();

		$this->expensiveFuncExecutionWatcher = $this->getMockBuilder( '\SMW\ParserFunctions\ExpensiveFuncExecutionWatcher' )
			->disableOriginalConstructor()
			->getMock();

		$this->expensiveFuncExecutionWatcher->expects( $this->any() )
			->method( 'hasReachedExpensiveLimit' )
			->will( $this->returnValue( false ) );

		$queryResult = $this->getMockBuilder( '\SMWQueryResult' )
			->disableOriginalConstructor()
			->getMock();

		$queryResult->expects( $this->any() )
			->method( 'getErrors' )
			->will( $this->returnValue( [] ) );

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$store->expects( $this->any() )
			->method( 'getQueryResult' )
			->will( $this->returnValue( $queryResult ) );

		$this->testEnvironment->registerObject( 'Store', $store );
	}

	protected function tearDown() {
		$this->testEnvironment->tearDown();
		parent::tearDown();
	}

	public function testCanConstruct() {

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$this->assertInstanceOf(
			'\SMW\ParserFunctions\AskParserFunction',
			new AskParserFunction( $parserData, $this->messageFormatter, $this->circularReferenceGuard, $this->expensiveFuncExecutionWatcher )
		);
	}

	/**
	 * @dataProvider queryDataProvider
	 */
	public function testParse( array $params ) {

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$this->assertInternalType(
			'string',
			$instance->parse( $params )
		);
	}

	public function testIsQueryDisabled() {

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$this->messageFormatter->expects( $this->any() )
			->method( 'addFromKey' )
			->will( $this->returnSelf() );

		$this->messageFormatter->expects( $this->once() )
			->method( 'getHtml' );

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$instance->isQueryDisabled();
	}

	public function testHasReachedExpensiveLimit() {

		$params = array(
			'[[Modification date::+]]',
			'?Modification date',
			'format=list'
		);

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$expensiveFuncExecutionWatcher = $this->getMockBuilder( '\SMW\ParserFunctions\ExpensiveFuncExecutionWatcher' )
			->disableOriginalConstructor()
			->getMock();

		$expensiveFuncExecutionWatcher->expects( $this->any() )
			->method( 'hasReachedExpensiveLimit' )
			->will( $this->returnValue( true ) );

		$this->messageFormatter->expects( $this->any() )
			->method( 'addFromKey' )
			->will( $this->returnSelf() );

		$this->messageFormatter->expects( $this->once() )
			->method( 'getHtml' );

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$expensiveFuncExecutionWatcher
		);

		$instance->parse( $params );
	}

	public function testSetShowMode() {

		$parserData = $this->getMockBuilder( '\SMW\ParserData' )
			->disableOriginalConstructor()
			->getMock();

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$reflector = new ReflectionClass( '\SMW\ParserFunctions\AskParserFunction' );
		$showMode = $reflector->getProperty( 'showMode' );
		$showMode->setAccessible( true );

		$this->assertFalse( $showMode->getValue( $instance ) );
		$instance->setShowMode( true );

		$this->assertTrue( $showMode->getValue( $instance ) );
	}

	public function testCircularGuard() {

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$this->circularReferenceGuard->expects( $this->once() )
			->method( 'mark' );

		$this->circularReferenceGuard->expects( $this->never() )
			->method( 'unmark' );

		$this->circularReferenceGuard->expects( $this->once() )
			->method( 'isCircular' )
			->will( $this->returnValue( true ) );

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$params = array();

		$this->assertEmpty(
			$instance->parse( $params )
		);
	}

	public function testQueryIdStabilityForFixedSetOfParameters() {

		$this->testEnvironment->addConfiguration( 'smwgQueryResultCacheType', false );
		$this->testEnvironment->addConfiguration( 'smwgQFilterDuplicates', false );

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$params = array(
			'[[Modification date::+]]',
			'?Modification date',
			'format=list'
		);

		$instance->parse( $params );

		$this->assertTrue(
			$parserData->getSemanticData()->hasSubSemanticData( '_QUERY702bb82fc5ac212df176709f98b4f5b9' )
		);

		// Limit is a factor that influences the query id, count uses the
		// max limit available in $GLOBALS['smwgQMaxLimit'] therefore we set
		// the limit to make the test independent from possible other settings

		$params = array(
			'[[Modification date::+]]',
			'?Modification date',
			'format=count'
		);

		$instance->parse( $params );

		$this->assertTrue(
			$parserData->getSemanticData()->hasSubSemanticData( '_QUERYf161b0f405d169d1f038812484619c1f' )
		);
	}

	public function testQueryIdStabilityForFixedSetOfParametersWithFingerprintMethod() {

		$this->testEnvironment->addConfiguration( 'smwgQueryResultCacheType', false );
		$this->testEnvironment->addConfiguration( 'smwgQFilterDuplicates', true );

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$params = array(
			'[[Modification date::+]]',
			'?Modification date',
			'format=list'
		);

		$instance->parse( $params );

		$this->assertTrue(
			$parserData->getSemanticData()->hasSubSemanticData( '_QUERYaa38249db4bc6d3e8133588fb08d0f0d' )
		);

		// Limit is a factor that influences the query id, count uses the
		// max limit available in $GLOBALS['smwgQMaxLimit'] therefore we set
		// the limit to make the test independent from possible other settings

		$params = array(
			'[[Modification date::+]]',
			'?Modification date',
			'format=count'
		);

		$instance->parse( $params );

		$this->assertTrue(
			$parserData->getSemanticData()->hasSubSemanticData( '_QUERYaa38249db4bc6d3e8133588fb08d0f0d' )
		);
	}

	/**
	 * @dataProvider queryDataProvider
	 */
	public function testInstantiatedQueryData( array $params, array $expected, array $settings ) {

		foreach ( $settings as $key => $value ) {
			$this->testEnvironment->addConfiguration( $key, $value );
		}

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$instance->parse( $params );

		foreach ( $parserData->getSemanticData()->getSubSemanticData() as $containerSemanticData ){
			$this->assertInstanceOf( 'SMWContainerSemanticData', $containerSemanticData );

			$this->semanticDataValidator->assertThatPropertiesAreSet(
				$expected,
				$containerSemanticData
			);
		}
	}

	public function testEmbeddedQueryWithError() {

		$params = array(
			'[[--ABC·|DEF::123]]',
			'format=table'
		);

		$expected = array(
			'propertyCount'  => 2,
			'propertyKeys'   => array( '_ASK', '_ERRC' ),
		);

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$instance->parse( $params );

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()
		);
	}

	public function testWithDisabledQueryProfiler() {

		$params = array(
			'[[Modification date::+]]',
			'format=table'
		);

		$expected = array(
			'propertyCount'  => 0
		);

		$this->testEnvironment->addConfiguration( 'smwgQueryProfiler', false );

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$instance->parse( $params );

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()
		);
	}

	public function testNoQueryProfileOnSpecialPages() {

		$params = array(
			'[[Modification date::+]]',
			'format=table'
		);

		$expected = array(
			'propertyCount'  => 0
		);

		$this->testEnvironment->addConfiguration( 'smwgQueryProfiler', true );

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__, NS_SPECIAL ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$instance->parse( $params );

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()
		);
	}

	public function testQueryWithAnnotationMarker() {

		$params = array(
			'[[Modification date::+]]',
			'format=table',
			'@annotation'
		);

		$postProcHandler = $this->getMockBuilder( '\SMW\PostProcHandler' )
			->disableOriginalConstructor()
			->getMock();

		$postProcHandler->expects( $this->once() )
			->method( 'addQueryRef' );

		$parserData = ApplicationFactory::getInstance()->newParserData(
			Title::newFromText( __METHOD__ ),
			new ParserOutput()
		);

		$instance = new AskParserFunction(
			$parserData,
			$this->messageFormatter,
			$this->circularReferenceGuard,
			$this->expensiveFuncExecutionWatcher
		);

		$instance->setPostProcHandler( $postProcHandler );
		$instance->parse( $params );
	}

	public function queryDataProvider() {

		$categoryNS = Localizer::getInstance()->getNamespaceTextById( NS_CATEGORY );
		$fileNS = Localizer::getInstance()->getNamespaceTextById( NS_FILE );

		$provider = array();

		// #0
		// {{#ask: [[Modification date::+]]
		// |?Modification date
		// |format=list
		// }}
		$provider[] = array(
			array(
				'[[Modification date::+]]',
				'?Modification date',
				'format=list'
			),
			array(
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKST', '_ASKSI', '_ASKDE', '_ASKFO' ),
				'propertyValues' => array( 'list', 1, 1, '[[Modification date::+]]' )
			),
			array(
				'smwgQueryProfiler' => true
			)
		);

		// #1 Query string with spaces
		// {{#ask: [[Modification date::+]] [[Category:Foo bar]] [[Has title::!Foo bar]]
		// |?Modification date
		// |?Has title
		// |format=list
		// }}
		$provider[] = array(
			array(
				'[[Modification date::+]] [[Category:Foo bar]] [[Has title::!Foo bar]]',
				'?Modification date',
				'?Has title',
				'format=list'
			),
			array(
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKST', '_ASKSI', '_ASKDE', '_ASKFO' ),
				'propertyValues' => array( 'list', 4, 1, "[[Modification date::+]] [[$categoryNS:Foo bar]] [[Has title::!Foo bar]]" )
			),
			array(
				'smwgCreateProtectionRight' => false,
				'smwgQueryProfiler' => true
			)
		);

		// #2
		// {{#ask: [[Modification date::+]][[Category:Foo]]
		// |?Modification date
		// |?Has title
		// |format=list
		// }}
		$provider[] = array(
			array(
				'[[Modification date::+]][[Category:Foo]]',
				'?Modification date',
				'?Has title',
				'format=list'
			),
			array(
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKST', '_ASKSI', '_ASKDE', '_ASKFO' ),
				'propertyValues' => array( 'list', 2, 1, "[[Modification date::+]] [[$categoryNS:Foo]]" )
			),
			array(
				'smwgQueryProfiler' => true
			)
		);

		// #3 Known format
		// {{#ask: [[File:Fooo]]
		// |?Modification date
		// |default=no results
		// |format=feed
		// }}
		$provider[] = array(
			array(
				'[[File:Fooo]]',
				'?Modification date',
				'default=no results',
				'format=feed'
			),
			array(
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKST', '_ASKSI', '_ASKDE', '_ASKFO' ),
				'propertyValues' => array( 'feed', 1, 1, "[[:$fileNS:Fooo]]" )
			),
			array(
				'smwgQueryProfiler' => true
			)
		);

		// #4 Unknown format, default table
		// {{#ask: [[Modification date::+]][[Category:Foo]]
		// |?Modification date
		// |?Has title
		// |format=bar
		// }}
		$provider[] = array(
			array(
				'[[Modification date::+]][[Category:Foo]]',
				'?Modification date',
				'?Has title',
				'format=lula'
			),
			array(
				'propertyCount'  => 4,
				'propertyKeys'   => array( '_ASKST', '_ASKSI', '_ASKDE', '_ASKFO' ),
				'propertyValues' => array( 'table', 2, 1, "[[Modification date::+]] [[$categoryNS:Foo]]" )
			),
			array(
				'smwgQueryProfiler' => true
			)
		);

		// #6 Invalid parameters
		// {{#ask: [[Modification date::+]]
		// |?Modification date
		// |format=list
		// |someParameterWithoutValue
		// |{{{template}}}
		// |@internal
		// }}
		$provider[] = array(
			array(
				'[[Modification date::+]]',
				'someParameterWithoutValue',
				'{{{template}}}',
				'format=list',
				'@internal',
				'?Modification date'
			),
			array(
				'propertyCount'  => 5,
				'propertyKeys'   => array( '_ASKST', '_ASKSI', '_ASKDE', '_ASKFO', '_ASKPA' ),
				'propertyValues' => array( 'list', 1, 1, '[[Modification date::+]]', '{"limit":50,"offset":0,"sort":[""],"order":["asc"],"mode":1}' )
			),
			array(
				'smwgQueryProfiler' => SMW_QPRFL_PARAMS
			)
		);

		return $provider;
	}

}
