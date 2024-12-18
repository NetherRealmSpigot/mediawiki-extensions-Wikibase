<?php

namespace Wikibase\Repo\Tests\Rdf;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Rdf\DedupeBag;
use Wikibase\Repo\Rdf\EntityMentionListener;
use Wikibase\Repo\Rdf\FullStatementRdfBuilder;
use Wikibase\Repo\Rdf\HashDedupeBag;
use Wikibase\Repo\Rdf\NullDedupeBag;
use Wikibase\Repo\Rdf\RdfProducer;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\Rdf\SnakRdfBuilder;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers \Wikibase\Repo\Rdf\FullStatementRdfBuilder
 *
 * @group Wikibase
 * @group WikibaseRdf
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class FullStatementRdfBuilderTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @var NTriplesRdfTestHelper
	 */
	private $helper;

	protected function setUp(): void {
		parent::setUp();

		$rdfBuilderTestData = new RdfBuilderTestData(
			__DIR__ . '/../../data/rdf/entities',
			__DIR__ . '/../../data/rdf/RdfBuilder'
		);
		$this->helper = new NTriplesRdfTestHelper( $rdfBuilderTestData );

		$this->setService( 'WikibaseRepo.PropertyInfoLookup', $rdfBuilderTestData->getPropertyInfoLookup() );

		$this->helper->setAllBlanksEqual( false );
	}

	/**
	 * Initialize repository data
	 *
	 * @return RdfBuilderTestData
	 */
	private function getTestData() {
		return $this->helper->getTestData();
	}

	/**
	 * @param RdfWriter $writer
	 * @param int $flavor Bitmap for the output flavor, use RdfProducer::PRODUCE_XXX constants.
	 * @param EntityId[] &$mentioned Receives any entity IDs being mentioned.
	 * @param RdfVocabulary|null $vocabulary
	 * @param DedupeBag|null $dedupe A bag of reference hashes that should be considered "already seen".
	 *
	 * @return FullStatementRdfBuilder
	 */
	private function newBuilder(
		RdfWriter $writer,
		$flavor,
		array &$mentioned = [],
		?RdfVocabulary $vocabulary = null,
		?DedupeBag $dedupe = null
	) {
		if ( $vocabulary === null ) {
			$vocabulary = $this->getTestData()->getVocabulary();
		}

		$mentionTracker = $this->createMock( EntityMentionListener::class );
		$mentionTracker->method( 'propertyMentioned' )
			->willReturnCallback( function( EntityId $id ) use ( &$mentioned ) {
				$key = $id->getSerialization();
				$mentioned[$key] = $id;
			} );

		// Note: using the actual factory here makes this an integration test!
		$valueBuilderFactory = WikibaseRepo::getValueSnakRdfBuilderFactory();

		if ( $flavor & RdfProducer::PRODUCE_FULL_VALUES ) {
			$valueWriter = $writer->sub();
		} else {
			$valueWriter = $writer;
		}

		$statementValueBuilder = $valueBuilderFactory->getValueSnakRdfBuilder(
			$flavor,
			$vocabulary,
			$valueWriter,
			$mentionTracker,
			new HashDedupeBag()
		);

		$snakRdfBuilder = new SnakRdfBuilder( $vocabulary, $statementValueBuilder, $this->getTestData()->getMockRepository() );
		$statementBuilder = new FullStatementRdfBuilder( $vocabulary, $writer, $snakRdfBuilder );
		$statementBuilder->setDedupeBag( $dedupe ?: new NullDedupeBag() );

		if ( $flavor & RdfProducer::PRODUCE_PROPERTIES ) {
			$snakRdfBuilder->setEntityMentionListener( $mentionTracker );
		}

		$statementBuilder->setProduceQualifiers( $flavor & RdfProducer::PRODUCE_QUALIFIERS );
		$statementBuilder->setProduceReferences( $flavor & RdfProducer::PRODUCE_REFERENCES );

		return $statementBuilder;
	}

	/**
	 * @param string|string[] $dataSetNames
	 * @param RdfWriter $writer
	 */
	private function assertTriples( $dataSetNames, RdfWriter $writer ) {
		$actual = $writer->drain();
		$this->helper->assertNTriplesEqualsDataset( $dataSetNames, $actual );
	}

	public function provideAddEntity() {
		$props = array_map(
			function ( $data ) {
				/** @var PropertyId $propertyId */
				$propertyId = $data[0];
				return $propertyId->getSerialization();
			},
			RdfBuilderTestData::getTestProperties()
		);

		$q4_minimal = [ 'Q4_statements' ];
		$q4_all = [ 'Q4_statements', 'Q4_values' ];
		$q4_statements = [ 'Q4_statements' ];
		$q4_values = [ 'Q4_statements', 'Q4_values' ];
		$q6_no_qualifiers = [ 'Q6_statements' ];
		$q6_qualifiers = [ 'Q6_statements', 'Q6_qualifiers' ];
		$q7_no_refs = [ 'Q7_statements' ];
		$q7_refs = [
			'Q7_statements',
			'Q7_reference_refs',
			'Q7_references',
		];

		return [
			[ 'Q4', 0, $q4_minimal, [] ],
			[ 'Q4', RdfProducer::PRODUCE_ALL, $q4_all, $props ],
			[ 'Q4', RdfProducer::PRODUCE_ALL_STATEMENTS, $q4_statements, [] ],
			[ 'Q6', RdfProducer::PRODUCE_ALL_STATEMENTS, $q6_no_qualifiers, [] ],
			[ 'Q6', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_QUALIFIERS, $q6_qualifiers, [] ],
			[ 'Q7', RdfProducer::PRODUCE_ALL_STATEMENTS, $q7_no_refs, [] ],
			[ 'Q7', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_REFERENCES, $q7_refs, [] ],
			[ 'Q4', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_PROPERTIES, $q4_minimal, $props ],
			[ 'Q4', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_FULL_VALUES, $q4_values, [] ],
		];
	}

	/**
	 * @dataProvider provideAddEntity
	 */
	public function testAddEntity( $entityName, $flavor, $dataSetNames, array $expectedMentions ) {
		$entity = $this->getTestData()->getEntity( $entityName );

		$writer = $this->getTestData()->getNTriplesWriter();
		$mentioned = [];
		$this->newBuilder( $writer, $flavor, $mentioned )->addEntity( $entity );

		$this->assertTriples( $dataSetNames, $writer );
		$this->assertEquals( $expectedMentions, array_keys( $mentioned ), 'Entities mentioned' );
	}

	public function provideAddEntityTestCasesWhenPropertiesFromOtherWikibase() {
		$props = array_map(
			function ( $data ) {
				/** @var PropertyId $propertyId */
				$propertyId = $data[0];
				return $propertyId->getSerialization();
			},
			RdfBuilderTestData::getTestProperties()
		);

		$q4_minimal = [ 'Q4_statements_foreignsource_properties' ];
		$q4_all = [ 'Q4_statements_foreignsource_properties', 'Q4_values_foreignsource_properties' ];
		$q4_statements = [ 'Q4_statements_foreignsource_properties' ];
		$q4_values = [ 'Q4_statements_foreignsource_properties', 'Q4_values_foreignsource_properties' ];
		$q6_no_qualifiers = [ 'Q6_statements_foreignsource_properties' ];
		$q6_qualifiers = [ 'Q6_statements_foreignsource_properties', 'Q6_qualifiers_foreignsource_properties' ];
		$q7_no_refs = [ 'Q7_statements_foreignsource_properties' ];
		$q7_refs = [
			'Q7_statements_foreignsource_properties',
			'Q7_reference_refs_foreignsource_properties',
			'Q7_references_foreignsource_properties',
		];

		yield [ 'Q4', 0, $q4_minimal, [] ];
		yield [ 'Q4', RdfProducer::PRODUCE_ALL, $q4_all, $props ];
		yield [ 'Q4', RdfProducer::PRODUCE_ALL_STATEMENTS, $q4_statements, [] ];
		yield [ 'Q6', RdfProducer::PRODUCE_ALL_STATEMENTS, $q6_no_qualifiers, [] ];
		yield [ 'Q6', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_QUALIFIERS, $q6_qualifiers, [] ];
		yield [ 'Q7', RdfProducer::PRODUCE_ALL_STATEMENTS, $q7_no_refs, [] ];
		yield [ 'Q7', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_REFERENCES, $q7_refs, [] ];
		yield [ 'Q4', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_PROPERTIES, $q4_minimal, $props ];
		yield [ 'Q4', RdfProducer::PRODUCE_ALL_STATEMENTS | RdfProducer::PRODUCE_FULL_VALUES, $q4_values, [] ];
	}

	/**
	 * @dataProvider provideAddEntityTestCasesWhenPropertiesFromOtherWikibase
	 */
	public function testAddEntity_whenPropertiesFromOtherWikibase(
		$entityName,
		$flavor,
		$dataSetNames,
		array $expectedMentions
	) {
		$entity = $this->getTestData()->getEntity( $entityName );

		$writer = $this->getTestData()->getNTriplesWriterForPropertiesFromOtherWikibase();
		$mentioned = [];
		$this->newBuilder(
			$writer,
			$flavor,
			$mentioned,
			$this->getTestData()->getVocabularyForPropertiesFromOtherWikibase()
		)->addEntity( $entity );

		$this->assertTriples( $dataSetNames, $writer );
		$this->assertEquals( $expectedMentions, array_keys( $mentioned ), 'Entities mentioned' );
	}

	public function testAddEntity_seen() {
		$entity = $this->getTestData()->getEntity( 'Q7' );

		$dedupe = new HashDedupeBag();

		$dedupe->alreadySeen( 'd2412760c57cacd8c8f24d9afde3b20c87161cca', 'R' );

		$writer = $this->getTestData()->getNTriplesWriter();
		$mentioned = [];
		$this->newBuilder( $writer, RdfProducer::PRODUCE_ALL, $mentioned, null, $dedupe )
			->addEntity( $entity );

		$this->assertTriples( [ 'Q7_statements', 'Q7_reference_refs' ], $writer );
	}

	public function testAddStatements() {
		$entity = $this->getTestData()->getEntity( 'Q4' );

		$writer = $this->getTestData()->getNTriplesWriter();
		$this->newBuilder( $writer, RdfProducer::PRODUCE_ALL )
			->addStatements( $entity->getId(), $entity->getStatements() );

		$this->assertTriples( [ 'Q4_statements', 'Q4_values' ], $writer );
	}

	public function testAddStatements_whenPropertiesFromOtherWikibase() {
		$entity = $this->getTestData()->getEntity( 'Q4' );

		$mentioned = [];
		$writer = $this->getTestData()->getNTriplesWriterForPropertiesFromOtherWikibase();
		$this->newBuilder(
			$writer,
			RdfProducer::PRODUCE_ALL,
			$mentioned,
			$this->getTestData()->getVocabularyForPropertiesFromOtherWikibase()
		)
			->addStatements( $entity->getId(), $entity->getStatements() );

		$this->assertTriples( [ 'Q4_statements_foreignsource_properties', 'Q4_values_foreignsource_properties' ], $writer );
	}

}
