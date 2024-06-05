<?php declare( strict_types = 1 );

namespace Wikibase\Repo\RestApi\Infrastructure;

use LogicException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\FormatableSummary;
use Wikibase\Lib\Summary;
use Wikibase\Repo\RestApi\Domain\Model\AliasesEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\AliasesInLanguageEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\DescriptionEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\DescriptionsEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\EditSummary;
use Wikibase\Repo\RestApi\Domain\Model\ItemEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\LabelEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\LabelsEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\PropertyEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\SitelinkEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\SitelinksEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\StatementEditSummary;
use Wikibase\Repo\SummaryFormatter;

/**
 * @license GPL-2.0-or-later
 */
class EditSummaryFormatter {

	private SummaryFormatter $summaryFormatter;
	private TermsEditSummaryToFormattableSummaryConverter $termsEditSummaryConverter;
	private FullEntityEditSummaryToFormattableSummaryConverter $fullEntityEditSummaryConverter;

	public function __construct(
		SummaryFormatter $summaryFormatter,
		TermsEditSummaryToFormattableSummaryConverter $termsEditSummaryConverter,
		FullEntityEditSummaryToFormattableSummaryConverter $fullEntityEditSummaryConverter
	) {
		$this->summaryFormatter = $summaryFormatter;
		$this->termsEditSummaryConverter = $termsEditSummaryConverter;
		$this->fullEntityEditSummaryConverter = $fullEntityEditSummaryConverter;
	}

	public function format( EditSummary $summary ): string {
		return $this->summaryFormatter->formatSummary(
			$this->convertToFormattableSummary( $summary )
		);
	}

	// phpcs:ignore Generic.Metrics.CyclomaticComplexity
	private function convertToFormattableSummary( EditSummary $editSummary ): FormatableSummary {
		if ( $editSummary instanceof PropertyEditSummary ) {
			return $this->fullEntityEditSummaryConverter->newSummaryForPropertyPatch( $editSummary );
		} elseif ( $editSummary instanceof LabelsEditSummary ) {
			return $this->termsEditSummaryConverter->convertLabelsEditSummary( $editSummary );
		} elseif ( $editSummary instanceof LabelEditSummary ) {
			switch ( $editSummary->getEditAction() ) {
				case EditSummary::ADD_ACTION:
					return $this->newSummaryForLabelEdit( $editSummary, 'add' );
				case EditSummary::REPLACE_ACTION:
					return $this->newSummaryForLabelEdit( $editSummary, 'set' );
				case EditSummary::REMOVE_ACTION:
					return $this->newSummaryForLabelEdit( $editSummary, 'remove' );
			}
		} elseif ( $editSummary instanceof DescriptionsEditSummary ) {
			return $this->termsEditSummaryConverter->convertDescriptionsEditSummary( $editSummary );
		} elseif ( $editSummary instanceof DescriptionEditSummary ) {
			switch ( $editSummary->getEditAction() ) {
				case EditSummary::ADD_ACTION:
					return $this->newSummaryForDescriptionEdit( $editSummary, 'add' );
				case EditSummary::REPLACE_ACTION:
					return $this->newSummaryForDescriptionEdit( $editSummary, 'set' );
				case EditSummary::REMOVE_ACTION:
					return $this->newSummaryForDescriptionEdit( $editSummary, 'remove' );
			}
		} elseif ( $editSummary instanceof AliasesEditSummary ) {
			return $this->termsEditSummaryConverter->convertAliasesEditSummary( $editSummary );
		} elseif ( $editSummary instanceof AliasesInLanguageEditSummary ) {
			return $this->newSummaryForAliasesInLanguageEdit( $editSummary );
		} elseif ( $editSummary instanceof StatementEditSummary ) {
			switch ( $editSummary->getEditAction() ) {
				case EditSummary::ADD_ACTION:
					return $this->newSummaryForStatementEdit( $editSummary, 'wbsetclaim', 'create', 1 );
				case EditSummary::REMOVE_ACTION:
					return $this->newSummaryForStatementEdit( $editSummary, 'wbremoveclaims', 'remove' );
				case EditSummary::REPLACE_ACTION:
				case EditSummary::PATCH_ACTION:
					return $this->newSummaryForStatementEdit( $editSummary, 'wbsetclaim', 'update', 1 );
			}
		} elseif ( $editSummary instanceof SitelinkEditSummary ) {
			switch ( $editSummary->getEditAction() ) {
				case EditSummary::ADD_ACTION:
					$actionName = $editSummary->hasBadges() ? 'add-both' : 'add';
					return $this->newSummaryForSitelinkEdit( $editSummary, $actionName );
				case EditSummary::REPLACE_ACTION:
					$actionName = 'set';
					if ( $editSummary->hasBadges() ) {
						$actionName = $editSummary->isBadgesOnly() ? 'set-badges' : 'set-both';
					}
					return $this->newSummaryForSitelinkEdit( $editSummary, $actionName );
				case EditSummary::REMOVE_ACTION:
					$summary = new Summary(
						'wbsetsitelink',
						'remove',
						$editSummary->getSitelink()->getSiteId(),
						[],
						[ $editSummary->getSitelink()->getPageName() ]
					);
					$summary->setUserSummary( $editSummary->getUserComment() );

					return $summary;
			}
		} elseif ( $editSummary instanceof SitelinksEditSummary ) {
			$summary = new Summary( 'wbeditentity', 'update' );
			$summary->setUserSummary( $editSummary->getUserComment() );
			return $summary;
		} elseif ( $editSummary instanceof ItemEditSummary ) {
			switch ( $editSummary->getEditAction() ) {
				case EditSummary::ADD_ACTION:
					return $this->fullEntityEditSummaryConverter->newSummaryForItemCreate( $editSummary );
				case EditSummary::PATCH_ACTION:
					return $this->fullEntityEditSummaryConverter->newSummaryForItemPatch( $editSummary );
			}
		}

		throw new LogicException( "Unknown summary type '{$editSummary->getEditAction()}' " . get_class( $editSummary ) );
	}

	private function newSummaryForLabelEdit( LabelEditSummary $editSummary, string $actionName ): Summary {
		$summary = new Summary( 'wbsetlabel', $actionName );
		$summary->setLanguage( $editSummary->getLabel()->getLanguageCode() );
		$summary->addAutoSummaryArgs( [ $editSummary->getLabel()->getText() ] );
		$summary->setUserSummary( $editSummary->getUserComment() );

		return $summary;
	}

	private function newSummaryForDescriptionEdit( DescriptionEditSummary $editSummary, string $actionName ): Summary {
		$summary = new Summary( 'wbsetdescription', $actionName );
		$summary->setLanguage( $editSummary->getDescription()->getLanguageCode() );
		$summary->addAutoSummaryArgs( [ $editSummary->getDescription()->getText() ] );
		$summary->setUserSummary( $editSummary->getUserComment() );

		return $summary;
	}

	private function newSummaryForStatementEdit(
		StatementEditSummary $editSummary,
		string $moduleName,
		string $actionName,
		int $autoCommentArgs = null
	): Summary {
		$statement = $editSummary->getStatement();

		$summary = new Summary( $moduleName, $actionName );
		$summary->setUserSummary( $editSummary->getUserComment() );
		$summary->addAutoSummaryArgs( [
			[ $statement->getPropertyId()->getSerialization() => $statement->getMainSnak() ],
		] );
		if ( $autoCommentArgs !== null ) {
			// the number of edited statements in wbsetclaim-related messages
			$summary->addAutoCommentArgs( $autoCommentArgs );
		}

		return $summary;
	}

	private function newSummaryForAliasesInLanguageEdit( AliasesInLanguageEditSummary $editSummary ): Summary {
		$summary = new Summary( 'wbsetaliases', 'add' );
		$summary->setLanguage( $editSummary->getAliases()->getLanguageCode() );
		$summary->addAutoSummaryArgs( $editSummary->getAliases()->getAliases() );
		$summary->setUserSummary( $editSummary->getUserComment() );

		return $summary;
	}

	private function newSummaryForSitelinkEdit( SitelinkEditSummary $editSummary, string $actionName ): Summary {
		$summary = new Summary( 'wbsetsitelink', $actionName );
		$summary->setLanguage( $editSummary->getSitelink()->getSiteId() );

		$formattedBadges = implode( ', ', array_map(
			fn( ItemId $itemId ) => $itemId->getSerialization(),
			$editSummary->getSitelink()->getBadges()
		) );

		if ( $editSummary->hasBadges() ) {
			$summaryArgs = $editSummary->isBadgesOnly() ?
				[ $formattedBadges ] :
				[ $editSummary->getSitelink()->getPageName(), $formattedBadges ];
		} else {
			$summaryArgs = [ $editSummary->getSitelink()->getPageName() ];
		}

		$summary->addAutoSummaryArgs( $summaryArgs );
		$summary->setUserSummary( $editSummary->getUserComment() );

		return $summary;
	}

}
