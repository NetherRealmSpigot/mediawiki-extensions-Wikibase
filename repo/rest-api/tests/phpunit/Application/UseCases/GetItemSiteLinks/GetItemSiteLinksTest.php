<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Application\UseCases\GetItemSiteLinks;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\RestApi\Application\UseCases\GetItemSiteLinks\GetItemSiteLinks;
use Wikibase\Repo\RestApi\Application\UseCases\GetItemSiteLinks\GetItemSiteLinksRequest;
use Wikibase\Repo\RestApi\Application\UseCases\GetLatestItemRevisionMetadata;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Domain\ReadModel\SiteLink;
use Wikibase\Repo\RestApi\Domain\ReadModel\SiteLinks;
use Wikibase\Repo\RestApi\Domain\Services\SiteLinksRetriever;
use Wikibase\Repo\Tests\RestApi\Application\UseCaseRequestValidation\TestValidatingRequestDeserializer;

/**
 * @covers \Wikibase\Repo\RestApi\Application\UseCases\GetItemSiteLinks\GetItemSiteLinks
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class GetItemSiteLinksTest extends TestCase {

	private GetLatestItemRevisionMetadata $getRevisionMetadata;
	private SiteLinksRetriever $siteLinksRetriever;

	protected function setUp(): void {
		parent::setUp();

		$this->getRevisionMetadata = $this->createStub( GetLatestItemRevisionMetadata::class );
		$this->siteLinksRetriever = $this->createStub( SiteLinksRetriever::class );
	}

	public function testGetSiteLinks(): void {
		$itemId = new ItemId( 'Q123' );
		$lastModifiedTimestamp = '20201111070707';
		$revisionId = 42;

		$siteLinksReadModel = new SiteLinks(
			new SiteLink( 'arwiki', 'صفحة للاختبار', [], '' ),
			new SiteLink( 'enwiki', 'test page', [], '' )
		);

		$this->getRevisionMetadata = $this->createStub( GetLatestItemRevisionMetadata::class );
		$this->getRevisionMetadata->method( 'execute' )->willReturn( [ $revisionId, $lastModifiedTimestamp ] );

		$this->siteLinksRetriever = $this->createMock( SiteLinksRetriever::class );
		$this->siteLinksRetriever->method( 'getSiteLinks' )
			->with( $itemId )
			->willReturn( $siteLinksReadModel );

		$response = $this->newUseCase()->execute( new GetItemSiteLinksRequest( "$itemId" ) );

		$this->assertEquals( $response->getSiteLinks(), $siteLinksReadModel );
		$this->assertSame( $response->getLastModified(), $lastModifiedTimestamp );
		$this->assertSame( $response->getRevisionId(), $revisionId );
	}

	public function testGetSiteLinks_returnsEmptyObject(): void {
		$itemId = new ItemId( 'Q123' );

		$this->getRevisionMetadata = $this->createStub( GetLatestItemRevisionMetadata::class );
		$this->getRevisionMetadata->method( 'execute' )->willReturn( [ 42, '20201111070707' ] );

		$this->siteLinksRetriever = $this->createMock( SiteLinksRetriever::class );
		$this->siteLinksRetriever->method( 'getSiteLinks' )
			->with( $itemId )
			->willReturn( new SiteLinks() );

		$response = $this->newUseCase()->execute( new GetItemSiteLinksRequest( "$itemId" ) );

		$this->assertEquals( $response->getSiteLinks(), new SiteLinks() );
	}

	public function testGivenInvalidItemId_throws(): void {
		try {
			$this->newUseCase()->execute(
				new GetItemSiteLinksRequest( 'X321' )
			);

			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( UseCaseError::INVALID_ITEM_ID, $e->getErrorCode() );
			$this->assertSame( 'Not a valid item ID: X321', $e->getErrorMessage() );
			$this->assertSame( [], $e->getErrorContext() );
		}
	}

	private function newUseCase(): GetItemSiteLinks {
		return new GetItemSiteLinks(
			new TestValidatingRequestDeserializer(),
			$this->getRevisionMetadata,
			$this->siteLinksRetriever
		);
	}

}
