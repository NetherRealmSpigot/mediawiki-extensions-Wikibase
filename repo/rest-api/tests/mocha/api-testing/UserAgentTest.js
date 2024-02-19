'use strict';

const { assert, utils } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const {
	createEntityWithStatements,
	createUniqueStringProperty,
	newLegacyStatementWithRandomStringValue,
	createLocalSitelink,
	getLocalSiteId
} = require( '../helpers/entityHelper' );
const {
	editRequestsOnItem,
	editRequestsOnProperty,
	getRequestsOnItem,
	getRequestsOnProperty
} = require( '../helpers/happyPathRequestBuilders' );

function assertValid400Response( response ) {
	expect( response ).to.have.status( 400 );
	assert.strictEqual( response.body.code, 'missing-user-agent' );
	assert.include( response.body.message, 'User-Agent' );
}

describe( 'User-Agent requests', () => {

	const itemRequestInputs = {};
	const propertyRequestInputs = {};

	before( async () => {
		const statementPropertyId = ( await createUniqueStringProperty() ).entity.id;
		const linkedArticle = utils.title( 'Article-linked-to-test-item' );

		const createItemResponse = await createEntityWithStatements(
			[ newLegacyStatementWithRandomStringValue( statementPropertyId ) ],
			'item'
		);
		itemRequestInputs.itemId = createItemResponse.entity.id;
		itemRequestInputs.statementId = createItemResponse.entity.claims[ statementPropertyId ][ 0 ].id;
		itemRequestInputs.statementPropertyId = statementPropertyId;
		itemRequestInputs.linkedArticle = linkedArticle;

		await createLocalSitelink( createItemResponse.entity.id, linkedArticle );
		itemRequestInputs.siteId = await getLocalSiteId();

		const createPropertyResponse = await createEntityWithStatements(
			[ newLegacyStatementWithRandomStringValue( statementPropertyId ) ],
			'property'
		);
		propertyRequestInputs.propertyId = createPropertyResponse.entity.id;
		propertyRequestInputs.statementId = createPropertyResponse.entity.claims[ statementPropertyId ][ 0 ].id;
		propertyRequestInputs.statementPropertyId = statementPropertyId;
	} );

	const useRequestInputs = ( requestInputs ) => ( newReqBuilder ) => () => newReqBuilder( requestInputs );

	[
		...editRequestsOnItem.map( useRequestInputs( itemRequestInputs ) ),
		...editRequestsOnProperty.map( useRequestInputs( propertyRequestInputs ) ),
		...getRequestsOnItem.map( useRequestInputs( itemRequestInputs ) ),
		...getRequestsOnProperty.map( useRequestInputs( propertyRequestInputs ) )
	].forEach( ( newRequestBuilder ) => {
		describe( newRequestBuilder().getRouteDescription(), () => {

			it( 'No User-Agent header provided', async () => {
				const requestBuilder = newRequestBuilder();
				delete requestBuilder.headers[ 'user-agent' ];
				const response = await requestBuilder
					.assertValidRequest()
					.makeRequest();

				assertValid400Response( response );
			} );

			it( 'Empty User-Agent header provided', async () => {
				const response = await newRequestBuilder()
					.withHeader( 'user-agent', '' )
					.assertValidRequest()
					.makeRequest();

				assertValid400Response( response );
			} );

		} );
	} );

} );
