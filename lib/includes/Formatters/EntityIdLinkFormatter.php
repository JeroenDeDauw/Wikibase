<?php

namespace Wikibase\Lib;

use Wikibase\DataModel\Entity\EntityId;

/**
 * Formats entity IDs by generating a wiki link to the corresponding page title
 * with the id serialization as text.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class EntityIdLinkFormatter extends EntityIdTitleFormatter {

	/**
	 * @see EntityIdFormatter::formatEntityId
	 *
	 * @param EntityId $entityId
	 *
	 * @return string Wikitext
	 */
	public function formatEntityId( EntityId $entityId ) {
		$title = parent::formatEntityId( $entityId );

		return "[[$title|" . wfEscapeWikiText( $entityId->getSerialization() ) . "]]";
	}

}
