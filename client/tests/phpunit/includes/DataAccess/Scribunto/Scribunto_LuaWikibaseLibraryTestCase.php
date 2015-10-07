<?php

namespace Wikibase\Client\Tests\DataAccess\Scribunto;

if ( !class_exists( 'Scribunto_LuaEngineTestBase' ) ) {
	abstract class Scribunto_LuaWikibaseLibraryTestCase extends \MediaWikiTestCase {

		protected function setUp() {
			$this->markTestSkipped( 'Scribunto is not available' );
		}

	}

	return;
}

use Language;
use Title;
use Wikibase\Client\Tests\DataAccess\WikibaseDataAccessTestItemSetUpHelper;
use Wikibase\Client\WikibaseClient;
use Wikibase\Test\MockClientStore;

/**
 * Base class for Wikibase Scribunto Tests
 *
 * @group WikibaseScribunto
 * @group WikibaseIntegration
 * @group WikibaseClient
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 * @author Daniel Kinzler
 */
abstract class Scribunto_LuaWikibaseLibraryTestCase extends \Scribunto_LuaEngineTestBase {

	/**
	 * @var bool|null
	 */
	private static $oldAllowArbitraryDataAccess = null;

	/**
	 * Whether to allow arbitrary data access or not
	 *
	 * @return bool
	 */
	protected static function allowArbitraryDataAccess() {
		return true;
	}

	/**
	 * Makes sure WikibaseClient uses our ClientStore mock
	 */
	private static function doMock() {
		$wikibaseClient = WikibaseClient::getDefaultInstance( 'reset' );

		$store = new MockClientStore( 'de' );
		$store->setEntityLookup( static::getEntityLookup() );
		$wikibaseClient->overrideStore( $store );

		$settings = $wikibaseClient->getSettings();
		if ( self::$oldAllowArbitraryDataAccess === null ) {
			// Only need to set this once, as this is supposed to be the original value
			self::$oldAllowArbitraryDataAccess = $settings->getSetting( 'allowArbitraryDataAccess' );
		}

		$settings->setSetting(
			'allowArbitraryDataAccess',
			static::allowArbitraryDataAccess()
		);

		$settings->setSetting(
			'entityAccessLimit',
			static::getEntityAccessLimit()
		);

		$testHelper = new WikibaseDataAccessTestItemSetUpHelper( $store );
		$testHelper->setUp();
	}

	/**
	 * Set up stuff we need to have in place even before Scribunto does its stuff
	 *
	 * @param string $className
	 *
	 * @return \PHPUnit_Framework_TestSuite
	 */
	public static function suite( $className ) {
		self::doMock();

		return parent::suite( $className );
	}

	protected function setUp() {
		parent::setUp();
		self::doMock();

		$wikibaseClient = WikibaseClient::getDefaultInstance();

		$this->assertInstanceOf(
			'Wikibase\Test\MockClientStore',
			$wikibaseClient->getStore(),
			'Mocking the default ClientStore failed'
		);

		$this->setMwGlobals( 'wgContLang', Language::factory( 'de' ) );
	}

	protected function tearDown() {
		parent::tearDown();

		$wikibaseClient = WikibaseClient::getDefaultInstance( 'reset' );

		if ( self::$oldAllowArbitraryDataAccess !== null ) {
			$wikibaseClient->getSettings()->setSetting(
				'allowArbitraryDataAccess',
				self::$oldAllowArbitraryDataAccess
			);
		}
	}

	/**
	 * @return Title
	 */
	protected function getTestTitle() {
		return Title::newFromText( 'WikibaseClientDataAccessTest' );
	}

	/**
	 * @return EntityLookup|null
	 */
	protected static function getEntityLookup() {
		return null;
	}

	/**
	 * @return int
	 */
	protected static function getEntityAccessLimit() {
		return PHP_INT_MAX;
	}

}
