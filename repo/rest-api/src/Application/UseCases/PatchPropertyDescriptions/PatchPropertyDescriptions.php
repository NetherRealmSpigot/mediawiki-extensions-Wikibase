<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\PatchPropertyDescriptions;

use Wikibase\Repo\RestApi\Application\Serialization\DescriptionsSerializer;
use Wikibase\Repo\RestApi\Application\UseCases\AssertPropertyExists;
use Wikibase\Repo\RestApi\Application\UseCases\AssertUserIsAuthorized;
use Wikibase\Repo\RestApi\Application\UseCases\PatchJson;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Domain\Model\DescriptionsEditSummary;
use Wikibase\Repo\RestApi\Domain\Model\EditMetadata;
use Wikibase\Repo\RestApi\Domain\Services\PropertyDescriptionsRetriever;
use Wikibase\Repo\RestApi\Domain\Services\PropertyRetriever;
use Wikibase\Repo\RestApi\Domain\Services\PropertyUpdater;

/**
 * @license GPL-2.0-or-later
 */
class PatchPropertyDescriptions {

	private PatchPropertyDescriptionsValidator $useCaseValidator;
	private AssertPropertyExists $assertPropertyExists;
	private AssertUserIsAuthorized $assertUserIsAuthorized;
	private PropertyDescriptionsRetriever $descriptionsRetriever;
	private DescriptionsSerializer $descriptionsSerializer;
	private PatchJson $patcher;
	private PropertyRetriever $propertyRetriever;
	private PatchedPropertyDescriptionsValidator $patchedDescriptionsValidator;
	private PropertyUpdater $propertyUpdater;

	public function __construct(
		PatchPropertyDescriptionsValidator $useCaseValidator,
		AssertPropertyExists $assertPropertyExists,
		AssertUserIsAuthorized $assertUserIsAuthorized,
		PropertyDescriptionsRetriever $descriptionsRetriever,
		DescriptionsSerializer $descriptionsSerializer,
		PatchJson $patcher,
		PropertyRetriever $propertyRetriever,
		PatchedPropertyDescriptionsValidator $patchedDescriptionsValidator,
		PropertyUpdater $propertyUpdater
	) {
		$this->useCaseValidator = $useCaseValidator;
		$this->assertPropertyExists = $assertPropertyExists;
		$this->assertUserIsAuthorized = $assertUserIsAuthorized;
		$this->descriptionsRetriever = $descriptionsRetriever;
		$this->descriptionsSerializer = $descriptionsSerializer;
		$this->patcher = $patcher;
		$this->propertyRetriever = $propertyRetriever;
		$this->patchedDescriptionsValidator = $patchedDescriptionsValidator;
		$this->propertyUpdater = $propertyUpdater;
	}

	/**
	 * @throws UseCaseError
	 */
	public function execute( PatchPropertyDescriptionsRequest $request ): PatchPropertyDescriptionsResponse {
		$deserializedRequest = $this->useCaseValidator->validateAndDeserialize( $request );
		$propertyId = $deserializedRequest->getPropertyId();

		$this->assertPropertyExists->execute( $propertyId );

		$this->assertUserIsAuthorized->checkEditPermissions( $propertyId, $deserializedRequest->getEditMetadata()->getUser() );

		$modifiedDescriptions = $this->patcher->execute(
			iterator_to_array(
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$this->descriptionsSerializer->serialize( $this->descriptionsRetriever->getDescriptions( $propertyId ) )
			),
			$deserializedRequest->getPatch()
		);

		$property = $this->propertyRetriever->getProperty( $propertyId );
		$originalDescriptions = $property->getDescriptions();

		$modifiedDescriptionsAsTermList = $this->patchedDescriptionsValidator->validateAndDeserialize(
			$propertyId,
			$originalDescriptions,
			$modifiedDescriptions
		);
		$property->getFingerprint()->setDescriptions( $modifiedDescriptionsAsTermList );

		$editMetadata = new EditMetadata(
			$deserializedRequest->getEditMetadata()->getTags(),
			$deserializedRequest->getEditMetadata()->isBot(),
			DescriptionsEditSummary::newPatchSummary(
				$deserializedRequest->getEditMetadata()->getComment(),
				$originalDescriptions,
				$modifiedDescriptionsAsTermList
			)
		);

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$revision = $this->propertyUpdater->update( $property, $editMetadata );

		return new PatchPropertyDescriptionsResponse(
			$revision->getProperty()->getDescriptions(),
			$revision->getLastModified(),
			$revision->getRevisionId()
		);
	}

}
