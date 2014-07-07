<?php

namespace Wikibase\Test\Api;

use ApiMain;
use FauxRequest;
use Language;
use UsageException;
use Wikibase\Api\ApiErrorReporter;
use Wikibase\Api\ApiRedirectCreator;
use Wikibase\Api\CreateRedirect;
use Wikibase\Api\RedirectCreator;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRedirect;
use Wikibase\Lib\Store\UnresolvedRedirectException;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Test\MockRepository;

/**
 * @covers Wikibase\Api\ApiRedirectCreator
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class CreateRedirectTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var MockRepository
	 */
	private $repo = null;

	public function setUp() {
		parent::setUp();

		$this->repo = new MockRepository();

		// empty item
		$item = Item::newEmpty();
		$item->setId( new ItemId( 'Q11' ) );
		$this->repo->putEntity( $item );

		// non-empty item
		$item->setLabel( 'en', 'Foo' );
		$item->setId( new ItemId( 'Q12' ) );
		$this->repo->putEntity( $item );

		// a property
		$prop = Property::newEmpty();
		$prop->setId( new PropertyId( 'P11' ) );
		$this->repo->putEntity( $prop );

		// another property
		$prop->setId( new PropertyId( 'P12' ) );
		$this->repo->putEntity( $prop );

		// redirect
		$redirect = new EntityRedirect( new ItemId( 'Q22' ), new ItemId( 'Q12' ) );
		$this->repo->putRedirect( $redirect );
	}

	/**
	 * @param array $params
	 *
	 * @return CreateRedirect
	 */
	private function newApiModule( $params ) {
		$request = new FauxRequest( $params, true );
		$main = new ApiMain( $request );
		return new CreateRedirect( $main, 'wbcreateredirect' );
	}

	private function newRedirectCreator( array $params ) {
		$api = $this->newApiModule( $params );
		$repoFactory = WikibaseRepo::getDefaultInstance();

		$errorReporter = new ApiErrorReporter(
			$api,
			$repoFactory->getExceptionLocalizer(),
			$api->getLanguage()
		);

		$redirectCreator = new RedirectCreator(
			$this->repo,
			$this->repo,
			$repoFactory->getSummaryFormatter(),
			$api->getUser()
		);

		return new ApiRedirectCreator(
			$redirectCreator,
			$repoFactory->getEntityIdParser(),
			$errorReporter,
			$api->getModuleName()
		);
	}

	/**
	 * @dataProvider setRedirectProvider_success
	 */
	public function testSetRedirect_success( $from, $to ) {
		$params = array( 'from' => $from, 'to' => $to );

		$redirectCreator = $this->newRedirectCreator( $params );
		$redirectCreator->createRedirect( $params );

		$fromId = new ItemId( $from );
		$toId = new ItemId( $to );

		try {
			$this->repo->getEntity( $fromId );
			$this->fail( 'getEntity( ' . $from . ' ) did not throw an UnresolvedRedirectException' );
		} catch ( UnresolvedRedirectException $ex ) {
			$this->assertEquals( $toId->getSerialization(), $ex->getRedirectTargetId()->getSerialization() );
		}
	}

	public function setRedirectProvider_success() {
		return array(
			'redirect empty entity' => array( 'Q11', 'Q12' ),
			'update redirect' => array( 'Q22', 'Q11' ),
		);
	}

	/**
	 * @dataProvider setRedirectProvider_failure
	 */
	public function testSetRedirect_failure( $from, $to, $expectedCode ) {
		$params = array( 'from' => $from, 'to' => $to );

		$redirectCreator = $this->newRedirectCreator( $params );

		try {
			$redirectCreator->createRedirect( $params );
			$this->fail( 'API did not fail with error ' . $expectedCode . ' as expected!' );
		} catch ( UsageException $ex ) {
			$this->assertEquals( $expectedCode, $ex->getCodeString() );
		}
	}

	public function setRedirectProvider_failure() {
		return array(
			'bad source id' => array( 'xyz', 'Q12', 'invalid-entity-id' ),
			'bad target id' => array( 'Q11', 'xyz', 'invalid-entity-id' ),

			'source not found' => array( 'Q77', 'Q12', 'no-such-entity' ),
			'target not found' => array( 'Q11', 'Q77', 'no-such-entity' ),
			'target is a redirect' => array( 'Q11', 'Q22', 'target-is-redirect' ),
			'target is incompatible' => array( 'Q11', 'P11', 'target-is-incompatible' ),

			'source not empty' => array( 'Q12', 'Q11', 'not-empty' ),
			'can\'t redirect' => array( 'P11', 'P12', 'cant-redirect' ),
		);
	}

}
