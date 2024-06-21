'use strict';

const { assert } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const {
	createUniqueStringProperty,
	newLegacyStatementWithRandomStringValue,
	createEntity,
	getLatestEditMetadata
} = require( '../helpers/entityHelper' );
const { newGetPropertyStatementsRequestBuilder } = require( '../helpers/RequestBuilderFactory' );
const { makeEtag } = require( '../helpers/httpHelper' );
const { assertValidError } = require( '../helpers/responseValidator' );

describe( newGetPropertyStatementsRequestBuilder().getRouteDescription(), () => {

	let propertyId;

	let testStatementPropertyId1;
	let testStatementPropertyId2;

	let testStatements;

	let testModified;
	let testRevisionId;

	before( async () => {
		testStatementPropertyId1 = ( await createUniqueStringProperty() ).entity.id;
		testStatementPropertyId2 = ( await createUniqueStringProperty() ).entity.id;

		testStatements = [
			newLegacyStatementWithRandomStringValue( testStatementPropertyId1 ),
			newLegacyStatementWithRandomStringValue( testStatementPropertyId1 ),
			newLegacyStatementWithRandomStringValue( testStatementPropertyId2 )
		];

		testStatements.forEach( ( statement ) => {
			statement.type = 'statement';
		} );

		const property = await createEntity( 'property', {
			claims: testStatements,
			datatype: 'string'
		} );

		propertyId = property.entity.id;

		const testPropertyCreationMetadata = await getLatestEditMetadata( propertyId );
		testModified = testPropertyCreationMetadata.timestamp;
		testRevisionId = testPropertyCreationMetadata.revid;
	} );

	it( 'can GET statements of a property with metadata', async () => {
		const response = await newGetPropertyStatementsRequestBuilder( propertyId )
			.assertValidRequest()
			.makeRequest();

		expect( response ).to.have.status( 200 );
		assert.exists( response.body[ testStatementPropertyId1 ] );
		assert.equal(
			response.body[ testStatementPropertyId1 ][ 0 ].value.content,
			testStatements[ 0 ].mainsnak.datavalue.value
		);
		assert.equal(
			response.body[ testStatementPropertyId1 ][ 1 ].value.content,
			testStatements[ 1 ].mainsnak.datavalue.value
		);
		assert.equal( response.header[ 'last-modified' ], testModified );
		assert.equal( response.header.etag, makeEtag( testRevisionId ) );

	} );

	it( 'can filter statements by property', async () => {
		const response = await newGetPropertyStatementsRequestBuilder( propertyId )
			.withQueryParam( 'property', testStatementPropertyId1 )
			.assertValidRequest()
			.makeRequest();

		expect( response ).to.have.status( 200 );
		assert.deepEqual( Object.keys( response.body ), [ testStatementPropertyId1 ] );
		assert.strictEqual( response.body[ testStatementPropertyId1 ].length, 2 );
	} );

	it( 'can GET empty statements list', async () => {
		const createPropertyResponse = await createUniqueStringProperty();
		const response = await newGetPropertyStatementsRequestBuilder( createPropertyResponse.entity.id )
			.assertValidRequest()
			.makeRequest();

		expect( response ).to.have.status( 200 );
		assert.empty( response.body );
	} );

	it( '404 error - property not found', async () => {
		const response = await newGetPropertyStatementsRequestBuilder( 'P999999' )
			.assertValidRequest()
			.makeRequest();

		assertValidError( response, 404, 'property-not-found' );
		assert.include( response.body.message, 'P999999' );
	} );

	it( '400 error - bad request, invalid subject property ID', async () => {
		const response = await newGetPropertyStatementsRequestBuilder( 'X123' )
			.assertInvalidRequest()
			.makeRequest();

		assertValidError(
			response,
			400,
			'invalid-path-parameter',
			{ parameter: 'property_id' }
		);
	} );

	it( '400 error - bad request, invalid filter property ID', async () => {
		const filterPropertyId = 'X123';
		const response = await newGetPropertyStatementsRequestBuilder( propertyId )
			.withQueryParam( 'property', filterPropertyId )
			.assertInvalidRequest()
			.makeRequest();

		assertValidError( response, 400, 'invalid-property-id', { 'property-id': filterPropertyId } );
		assert.include( response.body.message, filterPropertyId );
	} );

} );
