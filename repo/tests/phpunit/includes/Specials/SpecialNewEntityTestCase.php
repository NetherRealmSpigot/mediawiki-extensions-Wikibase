<?php

namespace Wikibase\Repo\Tests\Specials;

use ChangeTags;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\Tests\Unit\MockBlockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use SpecialPageTestBase;
use UserBlockedError;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Specials\SpecialNewEntity
 *
 * @license GPL-2.0-or-later
 */
abstract class SpecialNewEntityTestCase extends SpecialPageTestBase {
	use HtmlAssertionHelpers;
	use MockBlockTrait;

	protected const TAGS = [ 'mw-replace' ];

	/**
	 * @var SpecialPageCopyrightView|MockObject
	 */
	protected $copyrightView;

	protected function setUp(): void {
		parent::setUp();
		$this->setUserLang( 'qqx' );

		$this->copyrightView = $this->createMock( SpecialPageCopyrightView::class );
	}

	//TODO: Add test checking copyright rendering

	/**
	 * @dataProvider provideValidEntityCreationRequests
	 */
	public function testEntityIsBeingCreated_WhenValidInputIsGiven( array $formData ) {
		$formData['wpEditToken'] = RequestContext::getMain()->getUser()->getEditToken();
		$request = new FauxRequest( $formData, true );

		/** @var FauxResponse $webResponse */
		[ , $webResponse ] = $this->executeSpecialPage( '', $request );

		$entityId = $this->extractEntityIdFromUrl( $webResponse->getHeader( 'location' ) );
		$title = WikibaseRepo::getEntityTitleStoreLookup()->getTitleForId( $entityId );
		$tags = ChangeTags::getTags( $this->db, null, $title->getLatestRevID() );
		$this->assertArrayEquals( self::TAGS, $tags );

		$entity = WikibaseRepo::getEntityLookup()->getEntity( $entityId );
		$this->assertEntityMatchesFormData( $formData, $entity );
	}

	public function testExceptionWhenUserBlockedOnNamespace() {
		$user = $this->getTestUser()->getUser();
		$block = $this->createMock( AbstractBlock::class );
		$block->expects( $this->once() )
			->method( 'appliesToNamespace' )
			->with( $this->isType( 'integer' ) )
			->willReturn( true );
		$block->method( 'isSitewide' )
			->willReturn( false );
		$block->method( 'getReasonComment' )
			->willReturn( CommentStoreComment::newUnsavedComment( '' ) );
		$this->installMockBlockManager( $block, $user );
		$this->overrideUserPermissions(
			$user,
			[ 'createpage', 'property-create' ]
		);

		$this->expectException( UserBlockedError::class );
		$this->executeSpecialPage( '', null, null, $user );
	}

	/**
	 * Data provider method
	 *
	 * @return array[]
	 */
	abstract public function provideValidEntityCreationRequests();

	/**
	 * @param string $url
	 *
	 * @return EntityId
	 */
	abstract protected function extractEntityIdFromUrl( $url );

	/**
	 * @param array $form
	 * @param EntityDocument $entity
	 *
	 * @return void
	 * @throws \Exception
	 */
	abstract protected function assertEntityMatchesFormData( array $form, EntityDocument $entity );

	/**
	 * @dataProvider provideInvalidEntityCreationRequests
	 * @param array $formData
	 * @param string $errorMessageText
	 */
	public function testErrorBeingDisplayed_WhenInvalidInputIsGiven(
		array $formData,
		$errorMessageText
	) {
		$formData['wpEditToken'] = RequestContext::getMain()->getUser()->getEditToken();
		$request = new FauxRequest( $formData, true );

		/** @var FauxResponse $webResponse */
		[ $html ] = $this->executeSpecialPage( '', $request );

		$this->assertHtmlContainsErrorMessage( $html, $errorMessageText );
	}

	/**
	 * Data provider method
	 *
	 * @return array[]
	 */
	abstract public function provideInvalidEntityCreationRequests();

}
