<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\RemovePropertyLabel;

use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\EditMetadataRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\LabelLanguageCodeRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\PropertyIdRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\UseCaseRequest;

/**
 * @license GPL-2.0-or-later
 */
class RemovePropertyLabelRequest implements UseCaseRequest, PropertyIdRequest, LabelLanguageCodeRequest, EditMetadataRequest {

	private string $propertyId;
	private string $languageCode;
	private array $editTags;
	private bool $isBot;
	private ?string $comment;
	private ?string $username;

	public function __construct(
		string $propertyId,
		string $languageCode,
		array $editTags,
		bool $isBot,
		?string $comment,
		?string $username
	) {
		$this->propertyId = $propertyId;
		$this->languageCode = $languageCode;
		$this->editTags = $editTags;
		$this->isBot = $isBot;
		$this->comment = $comment;
		$this->username = $username;
	}

	public function getPropertyId(): string {
		return $this->propertyId;
	}

	public function getLanguageCode(): string {
		return $this->languageCode;
	}

	public function getEditTags(): array {
		return $this->editTags;
	}

	public function isBot(): bool {
		return $this->isBot;
	}

	public function getComment(): ?string {
		return $this->comment;
	}

	public function getUsername(): ?string {
		return $this->username;
	}

}
