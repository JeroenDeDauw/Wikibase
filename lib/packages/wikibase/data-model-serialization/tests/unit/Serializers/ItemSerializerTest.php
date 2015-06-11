<?php

namespace Tests\Wikibase\DataModel\Serializers;

use stdClass;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Serializers\FingerprintSerializer;
use Wikibase\DataModel\Serializers\ItemSerializer;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\StatementList;

/**
 * @covers Wikibase\DataModel\Serializers\ItemSerializer
 *
 * @licence GNU GPL v2+
 * @author Thomas Pellissier Tanon
 * @author Jan Zerebecki < jan.wikimedia@zerebecki.de >
 */
class ItemSerializerTest extends SerializerBaseTest {

	protected function buildSerializer() {
		$statementListSerializerMock = $this->getMock( '\Serializers\Serializer' );
		$statementListSerializerMock->expects( $this->any() )
			->method( 'serialize' )
			->will( $this->returnCallback( function( StatementList $statementList ) {
				if ( $statementList->isEmpty() ) {
					return array();
				}

				return array(
					'P42' => array(
						array(
							'mainsnak' => array(
								'snaktype' => 'novalue',
								'property' => 'P42'
							),
							'type' => 'statement',
							'rank' => 'normal'
						)
					)
				);
			} ) );

		$siteLinkSerializerMock = $this->getMock( '\Serializers\Serializer' );
		$siteLinkSerializerMock->expects( $this->any() )
			->method( 'serialize' )
			->with( $this->equalTo( new SiteLink( 'enwiki', 'Nyan Cat' ) ) )
			->will( $this->returnValue( array(
				'site' => 'enwiki',
				'title' => 'Nyan Cat',
				'badges' => array()
			) ) );

		$fingerprintSerializer = new FingerprintSerializer( FingerprintSerializer::USE_ARRAYS_FOR_MAPS );

		return new ItemSerializer( $fingerprintSerializer, $statementListSerializerMock, $siteLinkSerializerMock, false );
	}

	public function serializableProvider() {
		return array(
			array(
				new Item()
			),
		);
	}

	public function nonSerializableProvider() {
		return array(
			array(
				5
			),
			array(
				array()
			),
			array(
				Property::newFromType( '' )
			),
		);
	}

	public function serializationProvider() {
		$provider = array(
			array(
				array(
					'type' => 'item',
					'labels' => array(),
					'descriptions' => array(),
					'aliases' => array(),
					'sitelinks' => array(),
					'claims' => array(),
				),
				new Item()
			),
		);

		$entity = new Item();
		$entity->getStatements()->addNewStatement( new PropertyNoValueSnak( 42 ), null, null, 'test' );
		$provider[] = array(
			array(
				'type' => 'item',
				'claims' => array(
					'P42' => array(
						array(
							'mainsnak' => array(
								'snaktype' => 'novalue',
								'property' => 'P42'
							),
							'type' => 'statement',
							'rank' => 'normal'
						)
					)
				),
				'labels' => array(),
				'descriptions' => array(),
				'aliases' => array(),
				'sitelinks' => array(),
			),
			$entity
		);

		$item = new Item();
		$item->addSiteLink( new SiteLink( 'enwiki', 'Nyan Cat' ) );
		$provider[] = array(
			array(
				'type' => 'item',
				'sitelinks' => array(
					'enwiki' => array(
						'site' => 'enwiki',
						'title' => 'Nyan Cat',
						'badges' => array()
					)
				),
				'labels' => array(),
				'descriptions' => array(),
				'aliases' => array(),
				'claims' => array(),
			),
			$item
		);

		return $provider;
	}

	public function testItemSerializerWithOptionObjectsForMaps() {
		$statementListSerializerMock = $this->getMock( '\Serializers\Serializer' );
		$statementListSerializerMock->expects( $this->any() )
			->method( 'serialize' )
			->with( $this->equalTo( new StatementList() ) )
			->will( $this->returnValue( array() ) );

		$siteLinkSerializerMock = $this->getMock( '\Serializers\Serializer' );
		$siteLinkSerializerMock->expects( $this->any() )
			->method( 'serialize' )
			->with( $this->equalTo( new SiteLink( 'enwiki', 'Nyan Cat' ) ) )
			->will( $this->returnValue( array(
				'site' => 'enwiki',
				'title' => 'Nyan Cat',
				'badges' => array()
			) ) );

		$fingerprintSerializer = new FingerprintSerializer( FingerprintSerializer::USE_ARRAYS_FOR_MAPS );

		$serializer = new ItemSerializer( $fingerprintSerializer, $statementListSerializerMock, $siteLinkSerializerMock, true );

		$item = new Item();
		$item->addSiteLink( new SiteLink( 'enwiki', 'Nyan Cat' ) );

		$sitelinks = new stdClass();
		$sitelinks->enwiki = array(
			'site' => 'enwiki',
			'title' => 'Nyan Cat',
			'badges' => array(),
		);
		$serial = array(
			'type' => 'item',
			'labels' => array(),
			'descriptions' => array(),
			'aliases' => array(),
			'claims' => array(),
			'sitelinks' => $sitelinks,
		);
		$this->assertEquals( $serial, $serializer->serialize( $item ) );
	}

}
