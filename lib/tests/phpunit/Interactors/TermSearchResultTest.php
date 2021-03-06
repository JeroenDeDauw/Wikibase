<?php

namespace Wikibase\Lib\Tests\Interactors;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;

/**
 * @covers Wikibase\Lib\Interactors\TermSearchResult
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @license GPL-2.0+
 * @author Addshore
 */
class TermSearchResultTest extends PHPUnit_Framework_TestCase {

	public function provideGoodConstruction() {
		return array(
			array(
				new Term( 'br', 'FooText' ),
				'label',
				new ItemId( 'Q1234' ),
				new Term( 'pt', 'ImaLabel' ),
				new Term( 'en', 'ImaDescription' ),
				''
			),
			array(
				new Term( 'en-gb', 'FooText' ),
				'description',
				new PropertyId( 'P777' ),
				null,
				null,
				''
			),
			array(
				new Term( 'en-gb', 'FooText' ),
				'description',
				new PropertyId( 'foo:P777' ),
				null,
				null,
				'foo'
			),
		);
	}

	/**
	 * @dataProvider provideGoodConstruction
	 */
	public function testGoodConstruction(
		$matchedTerm,
		$matchedTermType,
		$entityId,
		$displayLabel,
		$displayDescription,
		$expectedRepositoryName
	) {
		$result = new TermSearchResult(
			$matchedTerm,
			$matchedTermType,
			$entityId,
			$displayLabel,
			$displayDescription
		);

		$this->assertEquals( $matchedTerm, $result->getMatchedTerm() );
		$this->assertEquals( $matchedTermType, $result->getMatchedTermType() );
		$this->assertEquals( $entityId, $result->getEntityId() );
		$this->assertEquals( $expectedRepositoryName, $result->getRepositoryName() );
		$this->assertEquals( $displayLabel, $result->getDisplayLabel() );
		$this->assertEquals( $displayDescription, $result->getDisplayDescription() );
	}

}
