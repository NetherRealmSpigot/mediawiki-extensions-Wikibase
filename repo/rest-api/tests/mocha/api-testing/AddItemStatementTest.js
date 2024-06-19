'use strict';

const { assert, action, utils } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const entityHelper = require( '../helpers/entityHelper' );
const { formatStatementEditSummary } = require( '../helpers/formatEditSummaries' );
const { newAddItemStatementRequestBuilder } = require( '../helpers/RequestBuilderFactory' );
const { makeEtag } = require( '../helpers/httpHelper' );
const { assertValidError } = require( '../helpers/responseValidator' );

describe( newAddItemStatementRequestBuilder().getRouteDescription(), () => {
	let testItemId;
	let originalLastModified;
	let originalRevisionId;
	let testStatement;

	function assertValid201Response( response, propertyId = null, content = null ) {
		expect( response ).to.have.status( 201 );
		assert.strictEqual( response.header[ 'content-type' ], 'application/json' );
		assert.isAbove( new Date( response.header[ 'last-modified' ] ), originalLastModified );
		assert.notStrictEqual( response.header.etag, makeEtag( originalRevisionId ) );
		assert.header( response, 'Location', response.request.url + '/' + encodeURIComponent( response.body.id ) );
		assert.strictEqual( response.body.property.id, propertyId || testStatement.property.id );
		assert.deepStrictEqual( response.body.value.content, content || testStatement.value.content );
	}

	before( async () => {
		const createEntityResponse = await entityHelper.createEntity( 'item', {} );
		testItemId = createEntityResponse.entity.id;

		const testItemCreationMetadata = await entityHelper.getLatestEditMetadata( testItemId );
		originalLastModified = new Date( testItemCreationMetadata.timestamp );
		originalRevisionId = testItemCreationMetadata.revid;

		testStatement = entityHelper.newStatementWithRandomStringValue(
			( await entityHelper.createUniqueStringProperty() ).entity.id
		);

		// wait 1s before next test to ensure the last-modified timestamps are different
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 1000 );
		} );
	} );

	describe( '201 success response ', () => {
		it( 'can add a statement to an item with edit metadata omitted', async () => {
			const response = await newAddItemStatementRequestBuilder( testItemId, testStatement )
				.assertValidRequest()
				.makeRequest();

			assertValid201Response( response );

			const { comment } = await entityHelper.getLatestEditMetadata( testItemId );
			assert.strictEqual(
				comment,
				formatStatementEditSummary(
					'wbsetclaim',
					'create',
					testStatement.property.id,
					testStatement.value.content
				)
			);
		} );
		it( 'can add a statement to an item with edit metadata provided', async () => {
			const user = await action.robby(); // robby is a bot
			const tag = await action.makeTag( 'e2e test tag', 'Created during e2e test' );
			const editSummary = 'omg look i made an edit';
			const response = await newAddItemStatementRequestBuilder( testItemId, testStatement )
				.withJsonBodyParam( 'tags', [ tag ] )
				.withJsonBodyParam( 'bot', true )
				.withJsonBodyParam( 'comment', editSummary )
				.withUser( user )
				.assertValidRequest()
				.makeRequest();

			assertValid201Response( response );

			const editMetadata = await entityHelper.getLatestEditMetadata( testItemId );
			assert.deepEqual( editMetadata.tags, [ tag ] );
			assert.property( editMetadata, 'bot' );
			assert.strictEqual(
				editMetadata.comment,
				formatStatementEditSummary(
					'wbsetclaim',
					'create',
					testStatement.property.id,
					testStatement.value.content,
					editSummary
				)
			);
			assert.strictEqual( editMetadata.user, user.username );
		} );
		it( 'can add a statement with a globecoordinate value in new format', async () => {
			const createPropertyResponse = await entityHelper.createEntity( 'property', {
				labels: { en: { language: 'en', value: `globe-coordinate-property-${utils.uniq()}` } },
				datatype: 'globe-coordinate'
			} );
			const propertyId = createPropertyResponse.entity.id;
			const globecoordinate = {
				latitude: 100,
				longitude: 100,
				precision: 1,
				globe: 'http://www.wikidata.org/entity/Q2'
			};
			const statement = {
				property: { id: propertyId },
				value: { type: 'value', content: globecoordinate }
			};

			const response = await newAddItemStatementRequestBuilder( testItemId, statement )
				.assertValidRequest()
				.makeRequest();

			assertValid201Response( response, propertyId, globecoordinate );
		} );
		it( 'can add a statement with a time value in new format', async () => {
			const createPropertyResponse = await entityHelper.createEntity( 'property', {
				labels: { en: { language: 'en', value: `time-property-${utils.uniq()}` } },
				datatype: 'time'
			} );
			const propertyId = createPropertyResponse.entity.id;
			const time = {
				time: '+0001-00-00T00:00:00Z',
				precision: 9,
				calendarmodel: 'http://www.wikidata.org/entity/Q1985727'
			};
			const statement = {
				property: { id: propertyId },
				value: { type: 'value', content: time }
			};

			const response = await newAddItemStatementRequestBuilder( testItemId, statement )
				.assertValidRequest()
				.makeRequest();

			assertValid201Response( response, propertyId, time );
		} );
		it( 'can add a statement with a wikibase-entityid value in new format', async () => {
			const createPropertyResponse = await entityHelper.createEntity( 'property', {
				labels: { en: { language: 'en', value: `wikibase-item-property-${utils.uniq()}` } },
				datatype: 'wikibase-item'
			} );
			const propertyId = createPropertyResponse.entity.id;
			const statement = {
				property: { id: propertyId },
				value: { type: 'value', content: testItemId }
			};

			const response = await newAddItemStatementRequestBuilder( testItemId, statement )
				.assertValidRequest()
				.makeRequest();

			assertValid201Response( response, propertyId, testItemId );
		} );
	} );

	describe( '400 error response', () => {
		it( 'invalid Item ID', async () => {
			const itemId = 'X123';
			const response = await newAddItemStatementRequestBuilder( itemId, testStatement )
				.assertInvalidRequest()
				.makeRequest();

			assertValidError( response, 400, 'invalid-item-id' );
			assert.include( response.body.message, itemId );
		} );
		it( 'comment too long', async () => {
			const comment = 'x'.repeat( 501 );
			const response = await newAddItemStatementRequestBuilder( testItemId, testStatement )
				.withJsonBodyParam( 'comment', comment )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'comment-too-long' );
			assert.include( response.body.message, '500' );
		} );

		it( 'invalid edit tag', async () => {
			const invalidEditTag = 'invalid tag';
			const response = await newAddItemStatementRequestBuilder( testItemId, testStatement )
				.withJsonBodyParam( 'tags', [ invalidEditTag ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'invalid-edit-tag' );
			assert.include( response.body.message, invalidEditTag );
		} );

		it( 'invalid bot flag', async () => {
			const response = await newAddItemStatementRequestBuilder( testItemId, testStatement )
				.withJsonBodyParam( 'bot', 'should be a boolean' )
				.assertInvalidRequest()
				.makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.error, 'parameter-validation-failed' );
			assert.strictEqual( response.body.name, 'bot' );
			assert.strictEqual( response.body.failureCode, 'badbool' );
		} );

		it( 'invalid comment', async () => {
			const response = await newAddItemStatementRequestBuilder( testItemId, testStatement )
				.withJsonBodyParam( 'comment', [ 'array', 'not', 'string' ] )
				.assertInvalidRequest()
				.makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.error, 'parameter-validation-failed' );
			assert.strictEqual( response.body.name, 'comment' );
			assert.strictEqual( response.body.failureCode, 'badvalue' );
		} );

		it( 'invalid statement field', async () => {
			const invalidField = 'rank';
			const invalidValue = 'not-a-valid-rank';
			const invalidStatement = { ...testStatement };
			invalidStatement[ invalidField ] = invalidValue;

			const response = await newAddItemStatementRequestBuilder( testItemId, invalidStatement )
				.assertInvalidRequest()
				.makeRequest();

			const context = { path: invalidField, value: invalidValue };
			assertValidError( response, 400, 'statement-data-invalid-field', context );
			assert.include( response.body.message, invalidField );
		} );

		it( 'missing statement field', async () => {
			const missingField = 'value';
			const invalidStatement = { ...testStatement };
			delete invalidStatement[ missingField ];

			const response = await newAddItemStatementRequestBuilder( testItemId, invalidStatement )
				.assertInvalidRequest()
				.makeRequest();

			assertValidError( response, 400, 'statement-data-missing-field', { path: missingField } );
			assert.include( response.body.message, missingField );
		} );
	} );

	describe( '404 error response', () => {
		it( 'item not found', async () => {
			const itemId = 'Q999999';
			const response = await newAddItemStatementRequestBuilder( itemId, testStatement )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 404, 'item-not-found' );
			assert.include( response.body.message, itemId );
		} );
	} );

	describe( '409 error response', () => {
		it( 'item is a redirect', async () => {
			const redirectTarget = testItemId;
			const redirectSource = await entityHelper.createRedirectForItem( redirectTarget );

			const response = await newAddItemStatementRequestBuilder( redirectSource, testStatement ).makeRequest();

			assertValidError( response, 409, 'redirected-item' );
			assert.include( response.body.message, redirectSource );
			assert.include( response.body.message, redirectTarget );
		} );
	} );
} );
