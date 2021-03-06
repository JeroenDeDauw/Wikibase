<?php

namespace Wikibase\Lib\Store;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\EntityRevision;

/**
 * Service interface for retrieving EntityRevisions from storage.
 *
 * @since 0.4
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
interface EntityRevisionLookup {

	/**
	 * Flag to use instead of a revision ID to indicate that the latest revision is desired,
	 * but a slightly lagged version is acceptable. This would generally be the case when fetching
	 * entities for display.
	 */
	const LATEST_FROM_SLAVE = 'slave';

	/**
	 * Flag used to indicate that loading slightly lagged data is fine (like
	 * LATEST_FROM_SLAVE), but in case an entity or revision couldn't be found,
	 * we try loading it from master.
	 */
	const LATEST_FROM_SLAVE_WITH_FALLBACK = 'master_fallback';

	/**
	 * Flag to use instead of a revision ID to indicate that the latest revision is desired,
	 * and it is essential to assert that there really is no newer version, to avoid data loss
	 * or conflicts. This would generally be the case when loading an entity for
	 * editing/modification.
	 */
	const LATEST_FROM_MASTER = 'master';

	/**
	 * Returns the entity revision with the provided id or null if there is no such
	 * entity. If a $revision is given, the requested revision of the entity is loaded.
	 * If that revision does not exist or does not belong to the given entity,
	 * an exception is thrown.
	 *
	 * Implementations of this method must not silently resolve redirects.
	 *
	 * @since 0.4
	 *
	 * @param EntityId $entityId
	 * @param int $revisionId The desired revision id, or 0 for the latest revision.
	 * @param string $mode LATEST_FROM_SLAVE, LATEST_FROM_SLAVE_WITH_FALLBACK or
	 *        LATEST_FROM_MASTER.
	 *
	 * @throws RevisionedUnresolvedRedirectException
	 * @throws StorageException
	 * @return EntityRevision|null
	 */
	public function getEntityRevision(
		EntityId $entityId,
		$revisionId = 0,
		$mode = self::LATEST_FROM_SLAVE
	);

	/**
	 * Returns the id of the latest revision of the given entity, or false if there is no such entity.
	 *
	 * Implementations of this method must not silently resolve redirects.
	 *
	 * @param EntityId $entityId
	 * @param string $mode LATEST_FROM_SLAVE, LATEST_FROM_SLAVE_WITH_FALLBACK or LATEST_FROM_MASTER.
	 *        LATEST_FROM_MASTER would force the revision to be determined from the canonical master database.
	 *
	 * @return int|false Returns false in case the entity doesn't exist (this includes redirects).
	 */
	public function getLatestRevisionId( EntityId $entityId, $mode = self::LATEST_FROM_SLAVE );

}
