<?php

namespace Wikibase\Api;

use ApiBase;
use InvalidArgumentException;
use User;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Lib\Store\EntityRedirect;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\UnresolvedRedirectException;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\StorageException;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * API module for creating entity redirects.
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class CreateRedirect extends ApiBase {

	/**
	 * Main method. Does the actual work and sets the result.
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$redirectCreator = WikibaseRepo::getDefaultInstance()->newApiRedirectCreator( $this );
		$redirectCreator->createRedirect( $this->extractRequestParams() );

		//XXX: return a serialized version of the redirect?
		$this->getResult()->addValue( null, 'success', 1 );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'invalid-entity-id', 'info' => 'Invalid entity ID' ),
			array( 'code' => 'not-empty', 'info' => 'The entity that is to be turned into a redirect is not empty' ),
			array( 'code' => 'no-such-entity', 'info' => 'Entity not found' ),
			array( 'code' => 'target-is-redirect', 'info' => 'The redirect target is itself a redirect' ),
			array( 'code' => 'target-is-incompatible', 'info' => 'The redirect target is incompatible (e.g. a different type of entity)' ),
			array( 'code' => 'cant-redirect', 'info' => 'Can\'t create the redirect (e.g. the given type of entity does not support redirects)' ),
		) );
	}

	/**
	 * @see ApiBase::isWriteMode()
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::needsToken()
	 */
	public function needsToken() {
		return true;
	}

	/**
	 * @see ApiBase::mustBePosted()
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		return array(
			'from' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'to' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'token' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => 'true',
			),
			'bot' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			)
		);
	}

	/**
	 * Get final parameter descriptions, after hooks have had a chance to tweak it as
	 * needed.
	 *
	 * @return array|bool False on no parameter descriptions
	 */
	public function getParamDescription() {
		return array(
			'from' => array( 'Entity ID to make a redirect' ),
			'to' => array( 'Entity ID to point the redirect to' ),
			'token' => array( 'A "edittoken" token previously obtained through the token module' ),
			'bot' => array( 'Mark this edit as bot',
				'This URL flag will only be respected if the user belongs to the group "bot".'
			),
		);
	}

	/**
	 * Returns the description string for this module
	 * @return mixed string or array of strings
	 */
	public function getDescription() {
		return array(
			'API module for creating Entity redirects.'
		);
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbcreateredirect&from=Q11&to=Q12'
				=> 'Turn Q11 into a redirect to Q12',
		);
	}

}

class ApiRedirectCreator {

	private $redirectCreator;
	private $idParser;
	private $errorReporter;
	private $moduleName;

	public function __construct( RedirectCreator $redirectCreator, EntityIdParser $idParser, ApiErrorReporter $errorReporter, $moduleName ) {
		$this->redirectCreator = $redirectCreator;
		$this->idParser = $idParser;
		$this->moduleName = $moduleName;
		$this->errorReporter = $errorReporter;
	}

	public function createRedirect( array $requestParams ) {
		try {
			$this->callRedirectCreator( $requestParams );
		}
		catch ( RedirectCreationException $ex ) {
			$this->errorReporter->dieException( $ex, $ex->getStringCode() );
		}
	}

	private function callRedirectCreator( array $requestParams ) {
		$this->redirectCreator->createRedirect(
			$this->redirectFromRequestParams( $requestParams ),
			$this->moduleName
		);
	}

	private function redirectFromRequestParams( array $requestParams ) {
		try {
			return new EntityRedirect(
				$this->extractEntityIdFromParams( $requestParams, 'from' ),
				$this->extractEntityIdFromParams( $requestParams, 'to' )
			);
		}
		catch ( InvalidArgumentException $ex ) {
			$this->errorReporter->dieError(
				'Incompatible entity types',
				'target-is-incompatible'
			);
		}
	}

	private function extractEntityIdFromParams( array $params, $paramName ) {
		try {
			return $this->idParser->parse( $params[$paramName] );
		} catch ( EntityIdParsingException $ex ) {
			$this->errorReporter->dieException( $ex, 'invalid-entity-id' );
		}
	}

}

class RedirectCreator {

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var EntityStore
	 */
	private $entityStore;

	/**
	 * @var SummaryFormatter
	 */
	private $summaryFormatter;

	/**
	 * @var User
	 */
	private $user;

	public function __construct(
		EntityRevisionLookup $entityRevisionLookup,
		EntityStore $entityStore,
		SummaryFormatter $summaryFormatter,
		User $user
	) {
		$this->entityRevisionLookup =$entityRevisionLookup;
		$this->entityStore = $entityStore;
		$this->summaryFormatter = $summaryFormatter;
		$this->user = $user;
	}

	public function createRedirect( EntityRedirect $redirect, $moduleName ) {
		$this->checkExists( $redirect->getTargetId() );
		$this->checkEmpty( $redirect->getEntityId() );

		$this->saveRedirect( $redirect, $moduleName );
	}

	private function checkEmpty( EntityId $id ) {
		try {
			$revision = $this->entityRevisionLookup->getEntityRevision( $id );

			if ( !$revision ) {
				throw new RedirectCreationException(
					'Entity ' . $id->getSerialization() . ' not found',
					'no-such-entity'
				);
			}

			$entity = $revision->getEntity();

			if ( !$entity->isEmpty() ) {
				throw new RedirectCreationException(
					'Entity ' . $id->getSerialization() . ' is not empty',
					'not-empty'
				);
			}
		} catch ( UnresolvedRedirectException $ex ) {
			// Nothing to do. It's ok to override a redirect with a redirect.
		} catch ( StorageException $ex ) {
			throw new RedirectCreationException(
				$ex->getMessage(),
				'cant-load-entity-content'
			);
		}
	}

	private function checkExists( EntityId $id ) {
		try {
			$revision = $this->entityRevisionLookup->getLatestRevisionId( $id );

			if ( !$revision ) {
				throw new RedirectCreationException(
					'Entity ' . $id->getSerialization() . ' not found',
					'no-such-entity'
				);
			}
		} catch ( UnresolvedRedirectException $ex ) {
			throw new RedirectCreationException(
				$ex->getMessage(),
				'target-is-redirect'
			);
		}
	}

	private function saveRedirect( EntityRedirect $redirect, $moduleName ) {
		$summary = new Summary( $moduleName, 'redirect' );
		$summary->addAutoSummaryArgs( $redirect->getEntityId(), $redirect->getTargetId() );

		try {
			$this->entityStore->saveRedirect(
				$redirect,
				$this->summaryFormatter->formatSummary( $summary ),
				$this->user,
				EDIT_UPDATE
			);
		} catch ( StorageException $ex ) {
			throw new RedirectCreationException(
				$ex->getMessage(),
				'cant-redirect'
			);
		}
	}

}

class RedirectCreationException extends \Exception {

	private $stringCode;

	public function __construct( $message, $stringCode ) {
		parent::__construct( $message );
		$this->stringCode = $stringCode;
	}

	public function getStringCode() {
		return $this->stringCode;
	}

}