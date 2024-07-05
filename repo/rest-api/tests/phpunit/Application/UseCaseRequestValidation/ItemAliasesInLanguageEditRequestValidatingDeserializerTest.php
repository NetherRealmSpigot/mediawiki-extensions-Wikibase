<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Application\UseCaseRequestValidation;

use Generator;
use PHPUnit\Framework\TestCase;
use Wikibase\Repo\RestApi\Application\Serialization\AliasesDeserializer;
use Wikibase\Repo\RestApi\Application\UseCaseRequestValidation\ItemAliasesInLanguageEditRequest;
use Wikibase\Repo\RestApi\Application\UseCaseRequestValidation\ItemAliasesInLanguageEditRequestValidatingDeserializer;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Application\Validation\AliasesInLanguageValidator;
use Wikibase\Repo\RestApi\Application\Validation\ValidationError;
use Wikibase\Repo\RestApi\Domain\ReadModel\AliasesInLanguage;
use Wikibase\Repo\RestApi\Domain\Services\ItemAliasesInLanguageRetriever;

/**
 * @covers \Wikibase\Repo\RestApi\Application\UseCaseRequestValidation\ItemAliasesInLanguageEditRequestValidatingDeserializer
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ItemAliasesInLanguageEditRequestValidatingDeserializerTest extends TestCase {

	public function testGivenValidRequest_returnsAliases(): void {
		$request = $this->createStub( ItemAliasesInLanguageEditRequest::class );
		$request->method( 'getItemId' )->willReturn( 'Q123' );
		$request->method( 'getLanguageCode' )->willReturn( 'en' );
		$request->method( 'getAliasesInLanguage' )->willReturn( [ 'first alias', 'second alias' ] );

		$this->assertEquals(
			[ 'first alias', 'second alias' ],
			( new ItemAliasesInLanguageEditRequestValidatingDeserializer(
				$this->createStub( AliasesInLanguageValidator::class ),
				new AliasesDeserializer(),
				$this->newStubItemAliasesInLanguageRetriever()
			) )->validateAndDeserialize( $request )
		);
	}

	/**
	 * @dataProvider invalidAliasesProvider
	 */
	public function testWithInvalidAliases(
		array $aliases,
		?ValidationError $validationError,
		string $expectedErrorCode,
		string $expectedErrorMessage,
		array $expectedContext = [],
		array $existingAliases = []
	): void {
		$request = $this->createStub( ItemAliasesInLanguageEditRequest::class );
		$request->method( 'getItemId' )->willReturn( 'Q123' );
		$request->method( 'getLanguageCode' )->willReturn( 'en' );
		$request->method( 'getAliasesInLanguage' )->willReturn( $aliases );

		$aliasesValidator = $this->createStub( AliasesInLanguageValidator::class );
		$aliasesValidator->method( 'validate' )->willReturn( $validationError );

		try {
			( new ItemAliasesInLanguageEditRequestValidatingDeserializer(
				$aliasesValidator,
				new AliasesDeserializer(),
				$this->newStubItemAliasesInLanguageRetriever( $existingAliases )
			) )
				->validateAndDeserialize( $request );
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $error ) {
			$this->assertSame( $expectedErrorCode, $error->getErrorCode() );
			$this->assertSame( $expectedErrorMessage, $error->getErrorMessage() );
			$this->assertSame( $expectedContext, $error->getErrorContext() );
		}
	}

	public static function invalidAliasesProvider(): Generator {
		yield 'alias on pos 0 is empty' => [
			[ '' ],
			null,
			UseCaseError::INVALID_VALUE,
			"Invalid value at '/aliases/0'",
			[ UseCaseError::CONTEXT_PATH => '/aliases/0' ],
		];

		yield 'alias on pos 1 is empty' => [
			[ 'aka', '' ],
			null,
			UseCaseError::INVALID_VALUE,
			"Invalid value at '/aliases/1'",
			[ UseCaseError::CONTEXT_PATH => '/aliases/1' ],
		];

		yield 'alias list is empty' => [
			[],
			null,
			UseCaseError::ALIAS_LIST_EMPTY,
			'Alias list must not be empty',
		];

		$alias = 'alias that is too long...';
		$limit = 40;
		yield 'alias too long' => [
			[ $alias ],
			new ValidationError(
				AliasesInLanguageValidator::CODE_TOO_LONG,
				[
					AliasesInLanguageValidator::CONTEXT_VALUE => $alias,
					AliasesInLanguageValidator::CONTEXT_LIMIT => $limit,
				]
			),
			UseCaseError::ALIAS_TOO_LONG,
			'Alias must be no more than 40 characters long',
			[
				UseCaseError::CONTEXT_VALUE => $alias,
				UseCaseError::CONTEXT_CHARACTER_LIMIT => $limit,
			],
		];

		$invalidAlias = "tab characters \t not allowed";
		yield 'alias invalid' => [
			[ $invalidAlias ],
			new ValidationError(
				AliasesInLanguageValidator::CODE_INVALID,
				[ AliasesInLanguageValidator::CONTEXT_VALUE => $invalidAlias ]
			),
			UseCaseError::INVALID_ALIAS,
			"Not a valid alias: $invalidAlias",
			[ UseCaseError::CONTEXT_ALIAS => $invalidAlias ],
		];

		$duplicateAlias = 'foo';
		yield 'alias duplicate in the request' => [
			[ $duplicateAlias, 'bar', $duplicateAlias ],
			null,
			UseCaseError::ALIAS_DUPLICATE,
			"Alias list contains a duplicate alias: '{$duplicateAlias}'",
			[ UseCaseError::CONTEXT_ALIAS => $duplicateAlias ],
		];

		$duplicateAlias = 'foo';
		yield 'alias already exists' => [
			[ $duplicateAlias, 'bar' ],
			null,
			UseCaseError::ALIAS_DUPLICATE,
			"Alias list contains a duplicate alias: '{$duplicateAlias}'",
			[ UseCaseError::CONTEXT_ALIAS => $duplicateAlias ],
			[ $duplicateAlias, 'baz' ],
		];
	}

	private function newStubItemAliasesInLanguageRetriever( array $enAliasesToReturn = [] ): ItemAliasesInLanguageRetriever {
		$retriever = $this->createStub( ItemAliasesInLanguageRetriever::class );
		$retriever->method( 'getAliasesInLanguage' )->willReturn( new AliasesInLanguage( 'en', $enAliasesToReturn ) );

		return $retriever;
	}

}
