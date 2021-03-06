<?php

namespace Wikibase\Client\Changes;

use Job;
use JobQueueGroup;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use RefreshLinksJob;
use Title;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\RecentChanges\RecentChangesDuplicateDetector;
use Wikibase\EntityChange;

/**
 * Service object for triggering different kinds of page updates
 * and generally notifying the local wiki of external changes.
 *
 * Used by ChangeHandler as an interface to the local wiki.
 *
 * @since 0.4
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class WikiPageUpdater implements PageUpdater {

	/**
	 * @var JobQueueGroup
	 */
	private $jobQueueGroup;

	/**
	 * @var RecentChangeFactory
	 */
	private $recentChangeFactory;

	/**
	 * @var RecentChangesDuplicateDetector|null
	 */
	private $recentChangesDuplicateDetector;

	/**
	 * @var StatsdDataFactoryInterface|null
	 */
	private $stats;

	/**
	 * @param JobQueueGroup $jobQueueGroup
	 * @param RecentChangeFactory $recentChangeFactory
	 * @param RecentChangesDuplicateDetector|null $recentChangesDuplicateDetector
	 * @param StatsdDataFactoryInterface|null $stats
	 */
	public function __construct(
		JobQueueGroup $jobQueueGroup,
		RecentChangeFactory $recentChangeFactory,
		RecentChangesDuplicateDetector $recentChangesDuplicateDetector  = null,
		StatsdDataFactoryInterface $stats = null
	) {
		$this->jobQueueGroup = $jobQueueGroup;
		$this->recentChangeFactory = $recentChangeFactory;
		$this->recentChangesDuplicateDetector = $recentChangesDuplicateDetector;
		$this->stats = $stats;
	}

	private function incrementStats( $updateType, $delta ) {
		if ( $this->stats ) {
			$this->stats->updateCount( 'wikibase.client.pageupdates.' . $updateType, $delta );
		}
	}

	/**
	 * Invalidates local cached of the given pages.
	 *
	 * @since 0.4
	 *
	 * @param Title[] $titles The Titles of the pages to update
	 */
	public function purgeParserCache( array $titles ) {
		/* @var Title $title */
		foreach ( $titles as $title ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": purging page " . $title->getText() );
			$title->invalidateCache();
		}
		$this->incrementStats( 'ParserCache', count( $titles ) );
	}

	/**
	 * Invalidates external web cached of the given pages.
	 *
	 * @since 0.4
	 *
	 * @param Title[] $titles The Titles of the pages to update
	 */
	public function purgeWebCache( array $titles ) {
		/* @var Title $title */
		foreach ( $titles as $title ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": purging web cache for " . $title->getText() );
			$title->purgeSquid();
		}
		$this->incrementStats( 'WebCache', count( $titles ) );
	}

	/**
	 * Schedules RefreshLinks jobs for the given titles
	 *
	 * @since 0.4
	 *
	 * @param Title[] $titles The Titles of the pages to update
	 */
	public function scheduleRefreshLinks( array $titles ) {
		/* @var Title $title */
		foreach ( $titles as $title ) {
			wfDebugLog( __CLASS__, __FUNCTION__ . ": scheduling refresh links for "
				. $title->getText() );

			$job = new RefreshLinksJob(
				$title,
				Job::newRootJobParams(
					$title->getPrefixedDBkey()
				)
			);

			$this->jobQueueGroup->push( $job );
			$this->jobQueueGroup->deduplicateRootJob( $job );
		}
		$this->incrementStats( 'RefreshLinksJob', count( $titles ) );
	}

	/**
	 * Injects an RC entry into the recentchanges, using the the given title and attribs
	 *
	 * @param Title[] $titles
	 * @param EntityChange $change
	 */
	public function injectRCRecords( array $titles, EntityChange $change ) {
		$rcAttribs = $this->recentChangeFactory->prepareChangeAttributes( $change );

		// TODO: do this via the job queue, in batches, see T107722
		foreach ( $titles as $title ) {
			if ( !$title->exists() ) {
				continue;
			}

			$rc = $this->recentChangeFactory->newRecentChange( $change, $title, $rcAttribs );

			if ( $this->recentChangesDuplicateDetector
				&& $this->recentChangesDuplicateDetector->changeExists( $rc )
			) {
				wfDebugLog( __CLASS__, __FUNCTION__ . ": skipping duplicate RC entry for " . $title->getFullText() );
			} else {
				wfDebugLog( __CLASS__, __FUNCTION__ . ": saving RC entry for " . $title->getFullText() );
				$rc->save();
			}
		}
		$this->incrementStats( 'InjectRCRecords', count( $titles ) );
	}

}
