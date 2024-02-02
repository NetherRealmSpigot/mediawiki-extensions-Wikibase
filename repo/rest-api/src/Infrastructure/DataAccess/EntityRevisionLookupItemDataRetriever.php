<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Infrastructure\DataAccess;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementList as DataModelStatementList;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Repo\RestApi\Domain\ReadModel\Aliases;
use Wikibase\Repo\RestApi\Domain\ReadModel\Descriptions;
use Wikibase\Repo\RestApi\Domain\ReadModel\ItemParts;
use Wikibase\Repo\RestApi\Domain\ReadModel\ItemPartsBuilder;
use Wikibase\Repo\RestApi\Domain\ReadModel\Labels;
use Wikibase\Repo\RestApi\Domain\ReadModel\Sitelink;
use Wikibase\Repo\RestApi\Domain\ReadModel\Sitelinks;
use Wikibase\Repo\RestApi\Domain\ReadModel\StatementList;
use Wikibase\Repo\RestApi\Domain\Services\ItemPartsRetriever;
use Wikibase\Repo\RestApi\Domain\Services\ItemRetriever;
use Wikibase\Repo\RestApi\Domain\Services\ItemStatementsRetriever;
use Wikibase\Repo\RestApi\Domain\Services\SitelinkRetriever;
use Wikibase\Repo\RestApi\Domain\Services\SitelinksRetriever;
use Wikibase\Repo\RestApi\Domain\Services\StatementReadModelConverter;
use Wikibase\Repo\RestApi\Infrastructure\SitelinksReadModelConverter;

/**
 * @license GPL-2.0-or-later
 */
class EntityRevisionLookupItemDataRetriever implements
	ItemRetriever,
	ItemPartsRetriever,
	ItemStatementsRetriever,
	SitelinkRetriever,
	SitelinksRetriever
{

	private EntityRevisionLookup $entityRevisionLookup;
	private StatementReadModelConverter $statementReadModelConverter;
	private SitelinksReadModelConverter $sitelinksReadModelConverter;

	public function __construct(
		EntityRevisionLookup $entityRevisionLookup,
		StatementReadModelConverter $statementReadModelConverter,
		SitelinksReadModelConverter $sitelinksReadModelConverter
	) {
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->statementReadModelConverter = $statementReadModelConverter;
		$this->sitelinksReadModelConverter = $sitelinksReadModelConverter;
	}

	public function getItem( ItemId $itemId ): ?Item {
		try {
			$entityRevision = $this->entityRevisionLookup->getEntityRevision( $itemId );
		} catch ( RevisionedUnresolvedRedirectException $e ) {
			return null;
		}

		if ( !$entityRevision ) {
			return null;
		}

		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $entityRevision->getEntity();
	}

	public function getItemParts( ItemId $itemId, array $fields ): ?ItemParts {
		$item = $this->getItem( $itemId );
		if ( $item === null ) {
			return null;
		}
		return $this->itemPartsFromRequestedFields( $fields, $item );
	}

	private function itemPartsFromRequestedFields( array $fields, Item $item ): ItemParts {
		$itemParts = ( new ItemPartsBuilder( $item->getId(), $fields ) );

		if ( in_array( ItemParts::FIELD_LABELS, $fields ) ) {
			$itemParts->setLabels( Labels::fromTermList( $item->getLabels() ) );
		}
		if ( in_array( ItemParts::FIELD_DESCRIPTIONS, $fields ) ) {
			$itemParts->setDescriptions( Descriptions::fromTermList( $item->getDescriptions() ) );
		}
		if ( in_array( ItemParts::FIELD_ALIASES, $fields ) ) {
			$itemParts->setAliases( Aliases::fromAliasGroupList( $item->getAliasGroups() ) );
		}
		if ( in_array( ItemParts::FIELD_STATEMENTS, $fields ) ) {
			$itemParts->setStatements( $this->convertDataModelStatementListToReadModel( $item->getStatements() ) );
		}
		if ( in_array( ItemParts::FIELD_SITELINKS, $fields ) ) {
			$itemParts->setSitelinks( $this->sitelinksReadModelConverter->convert( $item->getSiteLinkList() ) );
		}

		return $itemParts->build();
	}

	public function getStatements( ItemId $itemId, ?PropertyId $propertyId = null ): ?StatementList {
		$item = $this->getItem( $itemId );
		if ( $item === null ) {
			return null;
		}

		return $this->convertDataModelStatementListToReadModel(
			$propertyId ? $item->getStatements()->getByPropertyId( $propertyId ) : $item->getStatements()
		);
	}

	private function convertDataModelStatementListToReadModel( DataModelStatementList $list ): StatementList {
		return new StatementList( ...array_map(
			[ $this->statementReadModelConverter, 'convert' ],
			iterator_to_array( $list )
		) );
	}

	public function getSitelinks( ItemId $itemId ): Sitelinks {
		return $this->getItemParts( $itemId, [ ItemParts::FIELD_SITELINKS ] )->getSitelinks() ?? new Sitelinks();
	}

	public function getSitelink( ItemId $itemId, string $siteId ): ?Sitelink {
		$sitelinks = $this->getItemParts( $itemId, [ ItemParts::FIELD_SITELINKS ] )->getSitelinks();
		return $sitelinks[ $siteId ] ?? null;
	}

}
