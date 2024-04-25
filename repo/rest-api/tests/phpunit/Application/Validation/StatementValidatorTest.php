<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Application\Validation;

use Generator;
use LogicException;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\InvalidFieldException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\MissingFieldException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\SerializationException;
use Wikibase\Repo\RestApi\Application\Serialization\StatementDeserializer;
use Wikibase\Repo\RestApi\Application\Validation\StatementValidator;
use Wikibase\Repo\RestApi\Application\Validation\ValidationError;

/**
 * @covers \Wikibase\Repo\RestApi\Application\Validation\StatementValidator
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class StatementValidatorTest extends TestCase {

	private StatementDeserializer $deserializer;

	protected function setUp(): void {
		parent::setUp();

		$this->deserializer = $this->createStub( StatementDeserializer::class );
	}

	/**
	 * @dataProvider deserializationErrorProvider
	 */
	public function testGivenInvalidStatementSerialization_validateReturnsValidationError(
		SerializationException $exception,
		string $expectedErrorCode,
		array $expectedContext
	): void {
		$this->deserializer->method( 'deserialize' )->willThrowException( $exception );

		$error = $this->newValidator()->validate( [ 'invalid' => 'serialization' ] );

		$this->assertInstanceOf( ValidationError::class, $error );
		$this->assertSame( $expectedErrorCode, $error->getCode() );
		$this->assertSame( $expectedContext, $error->getContext() );
	}

	public static function deserializationErrorProvider(): Generator {
		yield 'invalid field exception' => [
			new InvalidFieldException( 'some-field', 'some-value' ),
			StatementValidator::CODE_INVALID_FIELD,
			[ 'field' => 'some-field', 'value' => 'some-value' ],
		];

		yield 'missing field exception' => [
			new MissingFieldException( 'property' ),
			StatementValidator::CODE_MISSING_FIELD,
			[ 'field' => 'property' ],
		];
	}

	public function testGetValidatedStatement_calledAfterValidate(): void {
		$serialization = [
			'property' => [ 'id' => 'P123' ],
			'value' => [ 'type' => 'novalue' ],
		];
		$deserializedStatement = $this->createStub( Statement::class );
		$this->deserializer = $this->createMock( StatementDeserializer::class );
		$this->deserializer->method( 'deserialize' )->with( $serialization )->willReturn( $deserializedStatement );

		$validator = $this->newValidator();
		$this->assertNull( $validator->validate( $serialization ) );
		$this->assertSame( $deserializedStatement, $validator->getValidatedStatement() );
	}

	public function testGetValidatedStatement_calledBeforeValidate(): void {
		$this->expectException( LogicException::class );

		$this->newValidator()->getValidatedStatement();
	}

	public function testGivenSyntacticallyValidSerializationButInvalidValueType_validateReturnsValidationError(): void {
		// The data type <-> value type mismatch isn't really tested here since we don't need to test
		// StatementDeserializer internals. This sort of error happens if e.g. P321 is a string Property,
		// but we're giving it an Item ID as a value.
		$serialization = [
			'property' => [ 'id' => 'P321' ],
			'value' => [
				'type' => 'value',
				'content' => [ 'id' => 'Q123' ],
			],
		];

		$this->deserializer = $this->createStub( StatementDeserializer::class );
		$this->deserializer->method( 'deserialize' )->willThrowException( new InvalidFieldException( 'some-field', null ) );

		$validator = $this->newValidator();
		$error = $validator->validate( $serialization );

		$this->assertInstanceOf( ValidationError::class, $error );
		$this->assertSame( StatementValidator::CODE_INVALID_FIELD, $error->getCode() );

		$this->expectException( LogicException::class );
		$validator->getValidatedStatement();
	}

	private function newValidator(): StatementValidator {
		return new StatementValidator( $this->deserializer );
	}

}
