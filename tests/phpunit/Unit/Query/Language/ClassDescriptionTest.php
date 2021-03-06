<?php

namespace SMW\Tests\Query\Language;

use SMW\DIWikiPage;
use SMW\Localizer;
use SMW\Query\Language\ClassDescription;
use SMW\Query\Language\ThingDescription;

/**
 * @covers \SMW\Query\Language\ClassDescription
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 2.1
 *
 * @author mwjames
 */
class ClassDescriptionTest extends \PHPUnit_Framework_TestCase {

	public function testCanConstruct() {

		$class = $this->getMockBuilder( '\SMW\DIWikiPage' )
			->disableOriginalConstructor()
			->getMock();

		$this->assertInstanceOf(
			'SMW\Query\Language\ClassDescription',
			new ClassDescription( $class )
		);

		// Legacy
		$this->assertInstanceOf(
			'SMW\Query\Language\ClassDescription',
			new \SMWClassDescription( $class )
		);
	}

	public function testConstructThrowsException() {

		$this->setExpectedException( 'Exception' );

		new ClassDescription( new \stdClass );
	}

	public function testCommonMethods() {

		$ns = Localizer::getInstance()->getNamespaceTextById( NS_CATEGORY );

		$class = new DIWikiPage( 'Foo', NS_CATEGORY );
		$instance = new ClassDescription( $class );

		$this->assertEquals( array( $class ), $instance->getCategories() );

		$this->assertEquals( "[[{$ns}:Foo]]", $instance->getQueryString() );
		$this->assertEquals( " <q>[[{$ns}:Foo]]</q> ", $instance->getQueryString( true ) );

		$this->assertEquals( false, $instance->isSingleton() );
		$this->assertEquals( array(), $instance->getPrintRequests() );

		$this->assertEquals( 1, $instance->getSize() );
		$this->assertEquals( 0, $instance->getDepth() );
		$this->assertEquals( 2, $instance->getQueryFeatures() );
	}

	public function testAddDescription() {

		$ns = Localizer::getInstance()->getNamespaceTextById( NS_CATEGORY );

		$instance = new ClassDescription( new DIWikiPage( 'Foo', NS_CATEGORY ) );
		$instance->addDescription( new ClassDescription( new DIWikiPage( 'Bar', NS_CATEGORY ) ) );

		$this->assertEquals(
			"[[{$ns}:Foo||Bar]]",
			$instance->getQueryString()
		);

		$this->assertEquals(
			" <q>[[{$ns}:Foo||Bar]]</q> ",
			$instance->getQueryString( true )
		);
	}

	public function testGetFingerprint() {

		$instance = new ClassDescription(
			new DIWikiPage( 'Foo', NS_CATEGORY )
		);

		$instance->addDescription(
			new ClassDescription( new DIWikiPage( 'Bar', NS_CATEGORY ) )
		);

		$expected = $instance->getFingerprint();

		// Different position, same hash
		$instance = new ClassDescription(
			new DIWikiPage( 'Bar', NS_CATEGORY )
		);

		$instance->addDescription(
			new ClassDescription( new DIWikiPage( 'Foo', NS_CATEGORY ) )
		);

		$this->assertSame(
			$expected,
			$instance->getFingerprint()
		);

		// Adds extra description, changes hash
		$instance->addDescription(
			new ClassDescription( new DIWikiPage( 'Foobar', NS_CATEGORY ) )
		);

		$this->assertNotSame(
			$expected,
			$instance->getFingerprint()
		);
	}

	public function testPrune() {

		$instance = new ClassDescription( new DIWikiPage( 'Foo', NS_CATEGORY ) );

		$maxsize  = 1;
		$maxDepth = 1;
		$log      = array();

		$this->assertEquals(
			$instance,
			$instance->prune( $maxsize, $maxDepth, $log )
		);

		$maxsize  = 0;
		$maxDepth = 1;
		$log      = array();

		$this->assertEquals(
			new ThingDescription(),
			$instance->prune( $maxsize, $maxDepth, $log )
		);
	}

	public function testStableFingerprint() {

		$instance = new ClassDescription(
			new DIWikiPage( 'Foo', NS_CATEGORY )
		);

		$this->assertSame(
			'Cl:f35b531270067b4772aa3a1a907b8c81',
			$instance->getFingerprint()
		);
	}

	public function testHierarchyDepthToBeCeiledOnMaxQSubcategoryDepthSetting() {

		$instance = new ClassDescription(
			new DIWikiPage( 'Foo', NS_CATEGORY )
		);

		$instance->setHierarchyDepth( 9999999 );

		$this->assertSame(
			$GLOBALS['smwgQSubcategoryDepth'],
			$instance->getHierarchyDepth()
		);
	}

	public function testGetQueryStringWithHierarchyDepth() {

		$ns = Localizer::getInstance()->getNamespaceTextById( NS_CATEGORY );

		$instance = new ClassDescription(
			new DIWikiPage( 'Foo', NS_CATEGORY )
		);

		$instance->setHierarchyDepth( 1 );

		$this->assertSame(
			"[[$ns:Foo|+depth=1]]",
			$instance->getQueryString()
		);
	}

	public function testVaryingHierarchyDepthCausesDifferentFingerprint() {

		$instance = new ClassDescription(
			new DIWikiPage( 'Foo', NS_CATEGORY )
		);

		$instance->setHierarchyDepth( 9999 );
		$expected = $instance->getFingerprint();

		$instance = new ClassDescription(
			new DIWikiPage( 'Foo', NS_CATEGORY )
		);

		$this->assertNotSame(
			$expected,
			$instance->getFingerprint()
		);
	}

}
