<?php

declare( strict_types  = 1 );

namespace Wikibase\Repo\Tests\Api;

use MediaWiki\Api\ApiMain;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWikiIntegrationTestCase;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataAccess\EntitySourceLookup;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\ConfigurableTermSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Lib\Store\EntityArticleIdLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\EntityTitleTextLookup;
use Wikibase\Lib\Store\EntityUrlLookup;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\CombinedEntitySearchHelper;
use Wikibase\Repo\Api\EntityIdSearchHelper;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\Api\EntityTermSearchHelper;
use Wikibase\Repo\Api\SearchEntities;

/**
 * @covers \Wikibase\Repo\Api\EntityTermSearchHelper
 * @covers \Wikibase\Repo\Api\SearchEntities
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class SearchEntitiesIntegrationTest extends MediaWikiIntegrationTestCase {

	private EntityIdParser $idParser;

	protected function setUp(): void {
		parent::setUp();

		$this->idParser = new BasicEntityIdParser();
	}

	public static function provideQueriesForEntityIds() {
		return [
			'Exact item ID' => [
				'Q1',
				[ 'Q1' ],
			],
			'Lower case item ID' => [
				'q2',
				[ 'Q2' ],
			],

			'Exact property ID' => [
				'P1',
				[ 'P1' ],
			],
			'Lower case property ID' => [
				'p2',
				[ 'P2' ],
			],

			'Copy paste with brackets' => [
				'(Q3)',
				[ 'Q3' ],
			],
			'Copy pasted concept URI' => [
				'http://www.wikidata.org/entity/Q4',
				[ 'Q4' ],
			],
			'Copy pasted page URL' => [
				'https://www.wikidata.org/wiki/Q5',
				[ 'Q5' ],
			],
		];
	}

	/**
	 * @dataProvider provideQueriesForEntityIds
	 */
	public function testTermTableIntegration( string $query, array $expectedIds ) {
		$searchHelper = new CombinedEntitySearchHelper(
			[
				new EntityIdSearchHelper(
					$this->newEntityLookup(),
					$this->idParser,
					$this->createMock( LabelDescriptionLookup::class ),
					[ 'item' ]
				),
				new EntityTermSearchHelper(
					$this->newConfigurableTermSearchInteractor()
				),
			]
		);

		$resultData = $this->executeApiModule( $searchHelper, $query );
		$this->assertSameSearchResults( $resultData, $expectedIds );
	}

	/**
	 * @param array[] $resultData
	 */
	private function assertSameSearchResults( array $resultData, array $expectedIds ) {
		$this->assertSameSize( $expectedIds, $resultData['search'] );

		foreach ( $expectedIds as $index => $expectedId ) {
			$this->assertSame( $expectedId, $resultData['search'][$index]['id'] );
		}
	}

	private function executeApiModule( EntitySearchHelper $entitySearchTermIndex, string $query ): array {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( [
			'language' => 'en',
			'search' => $query,
		] ) );

		$entitySourceDefinitions = new EntitySourceDefinitions( [
			new DatabaseEntitySource(
				'test',
				'testdb',
				[
					'item' => [ 'namespaceId' => 123, 'slot' => SlotRecord::MAIN ],
					'property' => [ 'namespaceId' => 321, 'slot' => SlotRecord::MAIN ],
				],
				'conceptBaseUri:',
				'',
				'',
				''
			),
		], new SubEntityTypesMapper( [] ) );
		$apiModule = new SearchEntities(
			new ApiMain( $context ),
			'',
			$this->createMock( LinkBatchFactory::class ),
			$entitySearchTermIndex,
			new StaticContentLanguages( [ 'en' ] ),
			new EntitySourceLookup( $entitySourceDefinitions, new SubEntityTypesMapper( [] ) ),
			$this->createMock( EntityTitleLookup::class ),
			$this->createMock( EntityTitleTextLookup::class ),
			$this->createMock( EntityUrlLookup::class ),
			$this->createMock( EntityArticleIdLookup::class ),
			$this->createMock( ApiErrorReporter::class ),
			[ 'item', 'property' ],
			[ 'default' => null ]
		);

		$apiModule->execute();

		return $apiModule->getResult()->getResultData( null, [ 'Strip' => 'all' ] );
	}

	private function newConfigurableTermSearchInteractor(): ConfigurableTermSearchInteractor {
		$interactor = $this->createMock( ConfigurableTermSearchInteractor::class );
		$interactor->method( 'searchForEntities' )->willReturnCallback(
			function ( $text, $languageCode, $entityType, array $termTypes ) {
				try {
					$entityId = $this->idParser->parse( $text );
				} catch ( EntityIdParsingException $ex ) {
					return [];
				}

				return [ new TermSearchResult( new Term( $languageCode, $text ), '', $entityId ) ];
			}
		);

		return $interactor;
	}

	private function newEntityLookup(): EntityLookup {
		$lookup = $this->createMock( EntityLookup::class );
		$lookup->method( 'hasEntity' )->willReturn( true );

		return $lookup;
	}

}
