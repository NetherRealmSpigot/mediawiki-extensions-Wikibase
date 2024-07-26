<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Application\Serialization;

use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Throwable;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Tests\NewStatement;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\InvalidFieldException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\InvalidFieldTypeException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\MissingFieldException;
use Wikibase\Repo\RestApi\Application\Serialization\PropertyValuePairDeserializer;
use Wikibase\Repo\RestApi\Application\Serialization\ReferenceDeserializer;
use Wikibase\Repo\RestApi\Application\Serialization\StatementDeserializer;

/**
 * @covers \Wikibase\Repo\RestApi\Application\Serialization\StatementDeserializer
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class StatementDeserializerTest extends TestCase {

	private const STATEMENT_ID = 'Q42$AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';

	/**
	 * @dataProvider serializationProvider
	 */
	public function testDeserialize( Statement $expectedStatement, array $serialization ): void {
		$this->assertTrue(
			$this->newDeserializer()->deserialize( $serialization )
				->equals( $expectedStatement )
		);
	}

	public static function serializationProvider(): Generator {
		yield 'without id' => [
			NewStatement::someValueFor( 'P123' )->build(),
			[
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
		];

		yield 'with id' => [
			NewStatement::someValueFor( 'P234' )
				->withGuid( self::STATEMENT_ID )
				->build(),
			[
				'property' => [ 'id' => 'P234' ],
				'value' => [ 'type' => 'somevalue' ],
				'id' => self::STATEMENT_ID,
			],
		];

		$statementWithQualifiers = NewStatement::someValueFor( 'P666' )->build();
		$statementWithQualifiers->setQualifiers( new SnakList( [
			new PropertySomeValueSnak( new NumericPropertyId( 'P777' ) ),
			new PropertySomeValueSnak( new NumericPropertyId( 'P888' ) ),
		] ) );
		yield 'with qualifiers' => [
			$statementWithQualifiers,
			[
				'property' => [ 'id' => 'P666' ],
				'value' => [ 'type' => 'somevalue' ],
				'qualifiers' => [
					[
						'property' => [ 'id' => 'P777' ],
						'value' => [ 'type' => 'somevalue' ],
					],
					[
						'property' => [ 'id' => 'P888' ],
						'value' => [ 'type' => 'somevalue' ],
					],
				],
			],
		];

		yield 'with preferred rank' => [
			NewStatement::someValueFor( 'P23' )
				->withPreferredRank()
				->build(),
			[
				'property' => [ 'id' => 'P23' ],
				'value' => [ 'type' => 'somevalue' ],
				'rank' => 'preferred',
			],
		];

		yield 'with references' => [
			NewStatement::someValueFor( 'P23' )
				->withReference( new Reference( [
					new PropertySomeValueSnak( new NumericPropertyId( 'P234' ) ),
				] ) )
				->withReference( new Reference( [
					new PropertySomeValueSnak( new NumericPropertyId( 'P345' ) ),
					new PropertySomeValueSnak( new NumericPropertyId( 'P456' ) ),
				] ) )
				->build(),
			[
				'property' => [ 'id' => 'P23' ],
				'value' => [ 'type' => 'somevalue' ],
				'references' => [
					[
						'parts' => [
							[
								'property' => [ 'id' => 'P234' ],
								'value' => [ 'type' => 'somevalue' ],
							],
						],
					],
					[
						'parts' => [
							[
								'property' => [ 'id' => 'P345' ],
								'value' => [ 'type' => 'somevalue' ],
							],
							[
								'property' => [ 'id' => 'P456' ],
								'value' => [ 'type' => 'somevalue' ],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider invalidSerializationProvider
	 */
	public function testDeserializationErrors( Exception $expectedException, array $serialization, string $basePath = '' ): void {
		try {
			$this->newDeserializer()->deserialize( $serialization, $basePath );
			$this->fail( 'Expected exception was not thrown.' );
		} catch ( Throwable $e ) {
			$this->assertEquals( $expectedException, $e );
		}
	}

	public static function invalidSerializationProvider(): Generator {
		yield 'statement is not associative array' => [
			new InvalidFieldTypeException( '/statements/P789' ),
			[
				[ 'id' => 'P123' ],
				[ 'type' => 'somevalue' ],
			],
			'/statements/P789',
		];

		yield 'invalid id field type' => [
			new InvalidFieldException( 'id', [ 'invalid' ], '/id' ),
			[
				'id' => [ 'invalid' ],
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
		];

		yield 'invalid rank' => [
			new InvalidFieldException( 'rank', 'bad', '/statements/P789/rank' ),
			[
				'rank' => 'bad',
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
			'/statements/P789',
		];

		yield 'invalid qualifiers field type' => [
			new InvalidFieldException( 'qualifiers', 'invalid', '/qualifiers' ),
			[
				'qualifiers' => 'invalid',
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
		];

		yield 'invalid qualifier type' => [
			new InvalidFieldException( '0', 'invalid', '/qualifiers/0' ),
			[
				'qualifiers' => [ 'invalid' ],
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
		];

		yield 'invalid references field type' => [
			new InvalidFieldException( 'references', 'invalid', '/references' ),
			[
				'references' => 'invalid',
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
		];

		yield 'invalid reference item type' => [
			new InvalidFieldException( '0', 'invalid', '/references/0' ),
			[
				'references' => [ 'invalid' ],
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
		];

		yield 'invalid reference item type with path provided' => [
			new InvalidFieldException( '0', 'invalid', '/abc/references/0' ),
			[
				'references' => [ 'invalid' ],
				'property' => [ 'id' => 'P123' ],
				'value' => [ 'type' => 'somevalue' ],
			],
			'/abc',
		];
	}

	public function testDeserializationErrorFromPropertyValuePair(): void {
		$expectedException = new MissingFieldException( 'value' );
		$propValPairDeserializer = $this->createStub( PropertyValuePairDeserializer::class );
		$propValPairDeserializer->method( 'deserialize' )
			->willThrowException( $expectedException );

		$deserializer = new StatementDeserializer(
			$propValPairDeserializer,
			$this->createStub( ReferenceDeserializer::class )
		);

		try {
			$deserializer->deserialize( [ 'property' => [ 'id' => 'P123' ] ] );
			$this->fail( 'Expected exception was not thrown.' );
		} catch ( Throwable $e ) {
			$this->assertEquals( $expectedException, $e );
		}
	}

	private function newDeserializer(): StatementDeserializer {
		$newSomeValueSnakFromSerialization = fn( array $p ) => new PropertySomeValueSnak( new NumericPropertyId( $p['property']['id'] ) );

		$propValPairDeserializer = $this->createStub( PropertyValuePairDeserializer::class );
		$propValPairDeserializer->method( 'deserialize' )
			->willReturnCallback( $newSomeValueSnakFromSerialization );

		$referenceDeserializer = $this->createStub( ReferenceDeserializer::class );
		$referenceDeserializer->method( 'deserialize' )
			->willReturnCallback( fn( array $ref ) => new Reference(
				array_map( $newSomeValueSnakFromSerialization, $ref['parts'] )
			) );

		return new StatementDeserializer( $propValPairDeserializer, $referenceDeserializer );
	}

}
