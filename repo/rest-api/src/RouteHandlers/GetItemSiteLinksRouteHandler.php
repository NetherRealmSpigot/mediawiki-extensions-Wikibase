<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\RouteHandlers;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use Wikibase\Repo\RestApi\Application\Serialization\SiteLinksSerializer;
use Wikibase\Repo\RestApi\Application\UseCases\GetItemSiteLinks\GetItemSiteLinks;
use Wikibase\Repo\RestApi\Application\UseCases\GetItemSiteLinks\GetItemSiteLinksRequest;
use Wikibase\Repo\RestApi\Application\UseCases\GetItemSiteLinks\GetItemSiteLinksResponse;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\WbRestApi;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class GetItemSiteLinksRouteHandler extends SimpleHandler {
	private const ITEM_ID_PATH_PARAM = 'item_id';

	private GetItemSiteLinks $getItemSiteLinks;
	private SiteLinksSerializer $siteLinksSerializer;
	private ResponseFactory $responseFactory;

	public function __construct(
		GetItemSiteLinks $getItemSiteLinks,
		SiteLinksSerializer $siteLinksSerializer,
		ResponseFactory $responseFactory
	) {
		$this->getItemSiteLinks = $getItemSiteLinks;
		$this->siteLinksSerializer = $siteLinksSerializer;
		$this->responseFactory = $responseFactory;
	}

	public static function factory(): Handler {
		return new self(
			new GetItemSiteLinks(
				WbRestApi::getValidatingRequestDeserializer(),
				WbRestApi::getGetLatestItemRevisionMetadata(),
				WbRestApi::getSiteLinksRetriever()
			),
			new SiteLinksSerializer(),
			new ResponseFactory()
		);
	}

	public function run( string $id ): Response {
		try {
			return $this->newSuccessHttpResponse(
				$this->getItemSiteLinks->execute( new GetItemSiteLinksRequest( $id ) )
			);
		} catch ( UseCaseError $e ) {
			return $this->responseFactory->newErrorResponseFromException( $e );
		}
	}

	private function setEtagFromRevId( Response $response, int $revId ): void {
		$response->setHeader( 'ETag', "\"$revId\"" );
	}

	public function getParamSettings(): array {
		return [
			self::ITEM_ID_PATH_PARAM => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	private function newSuccessHttpResponse( GetItemSiteLinksResponse $useCaseResponse ): Response {
		$httpResponse = $this->getResponseFactory()->create();
		$httpResponse->setHeader( 'Content-Type', 'application/json' );
		$httpResponse->setHeader( 'Last-Modified', wfTimestamp( TS_RFC2822, $useCaseResponse->getLastModified() ) );
		$this->setEtagFromRevId( $httpResponse, $useCaseResponse->getRevisionId() );
		$httpResponse->setBody( new StringStream(
			json_encode( $this->siteLinksSerializer->serialize( $useCaseResponse->getSiteLinks() ), JSON_UNESCAPED_SLASHES )
		) );

		return $httpResponse;
	}

}
