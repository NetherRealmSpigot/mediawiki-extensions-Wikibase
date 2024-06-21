'use strict';

const { assert, action, utils } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const entityHelper = require( '../helpers/entityHelper' );
const {
	newRemovePropertyLabelRequestBuilder,
	newSetPropertyLabelRequestBuilder,
	newGetPropertyLabelRequestBuilder
} = require( '../helpers/RequestBuilderFactory' );
const { formatTermEditSummary } = require( '../helpers/formatEditSummaries' );
const { assertValidError } = require( '../helpers/responseValidator' );

describe( newRemovePropertyLabelRequestBuilder().getRouteDescription(), () => {

	let testPropertyId;

	before( async () => {
		testPropertyId = ( await entityHelper.createEntity( 'property', { datatype: 'string' } ) ).entity.id;
	} );

	describe( '200 success response', () => {
		it( 'can remove a label without edit metadata', async () => {
			const languageCode = 'en';
			const label = 'english label ' + utils.uniq();
			await newSetPropertyLabelRequestBuilder( testPropertyId, languageCode, label ).makeRequest();

			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, languageCode )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 200 );
			assert.strictEqual( response.body, 'Label deleted' );
			assert.header( response, 'Content-Language', languageCode );

			const verifyDeleted = await newGetPropertyLabelRequestBuilder( testPropertyId, languageCode ).makeRequest();
			expect( verifyDeleted ).to.have.status( 404 );
		} );

		it( 'can remove a label with metadata', async () => {
			const languageCode = 'en';
			const label = 'english label ' + utils.uniq();
			await newSetPropertyLabelRequestBuilder( testPropertyId, languageCode, label ).makeRequest();

			const user = await action.robby(); // robby is a bot
			const tag = await action.makeTag( 'e2e test tag', 'Created during e2e test' );
			const comment = 'remove english label';

			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, languageCode )
				.withJsonBodyParam( 'tags', [ tag ] )
				.withJsonBodyParam( 'bot', true )
				.withJsonBodyParam( 'comment', comment )
				.withUser( user )
				.assertValidRequest()
				.makeRequest();

			expect( response ).to.have.status( 200 );
			assert.strictEqual( response.body, 'Label deleted' );
			assert.header( response, 'Content-Language', languageCode );

			const verifyDeleted = await newGetPropertyLabelRequestBuilder( testPropertyId, languageCode ).makeRequest();
			expect( verifyDeleted ).to.have.status( 404 );

			const editMetadata = await entityHelper.getLatestEditMetadata( testPropertyId );
			assert.include( editMetadata.tags, tag );
			assert.property( editMetadata, 'bot' );
			assert.strictEqual(
				editMetadata.comment,
				formatTermEditSummary( 'wbsetlabel', 'remove', languageCode, label, comment )
			);
		} );
	} );

	describe( '400 error response', () => {
		it( 'invalid property id', async () => {
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId.replace( 'P', 'Q' ), 'en' )
				.assertInvalidRequest().makeRequest();

			assertValidError(
				response,
				400,
				'invalid-path-parameter',
				{ parameter: 'property_id' }
			);
		} );

		it( 'invalid language code', async () => {
			const invalidLanguageCode = 'xyz';
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, invalidLanguageCode )
				.assertValidRequest().makeRequest();

			assertValidError( response, 400, 'invalid-language-code' );
			assert.include( response.body.message, invalidLanguageCode );
		} );

		it( 'invalid edit tag', async () => {
			const invalidEditTag = 'invalid tag';
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, 'en' )
				.withJsonBodyParam( 'tags', [ invalidEditTag ] ).assertValidRequest().makeRequest();

			assertValidError( response, 400, 'invalid-edit-tag' );
			assert.include( response.body.message, invalidEditTag );
		} );

		it( 'invalid edit tag type', async () => {
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, 'en' )
				.withJsonBodyParam( 'tags', 'not an array' ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-request-body' );
			assert.strictEqual( response.body.fieldName, 'tags' );
			assert.strictEqual( response.body.expectedType, 'array' );
		} );

		it( 'invalid bot flag type', async () => {
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, 'en' )
				.withJsonBodyParam( 'bot', 'not boolean' ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-request-body' );
			assert.strictEqual( response.body.fieldName, 'bot' );
			assert.strictEqual( response.body.expectedType, 'boolean' );
		} );

		it( 'comment too long', async () => {
			const comment = 'x'.repeat( 501 );
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, 'en' )
				.withJsonBodyParam( 'comment', comment ).assertValidRequest().makeRequest();

			assertValidError( response, 400, 'comment-too-long' );
			assert.include( response.body.message, '500' );
		} );

		it( 'invalid comment type', async () => {
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, 'en' )
				.withJsonBodyParam( 'comment', 1234 ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-request-body' );
			assert.strictEqual( response.body.fieldName, 'comment' );
			assert.strictEqual( response.body.expectedType, 'string' );
		} );
	} );

	describe( '404 error response', () => {
		it( 'property not found', async () => {
			const propertyId = 'P999999';
			const response = await newRemovePropertyLabelRequestBuilder( propertyId, 'en' )
				.assertValidRequest().makeRequest();

			assertValidError( response, 404, 'property-not-found' );
			assert.include( response.body.message, propertyId );
		} );

		it( 'label in the language specified does not exist', async () => {
			const languageCode = 'ar';
			const response = await newRemovePropertyLabelRequestBuilder( testPropertyId, languageCode )
				.assertValidRequest().makeRequest();

			assertValidError( response, 404, 'label-not-defined' );
			assert.include( response.body.message, testPropertyId );
			assert.include( response.body.message, languageCode );
		} );
	} );
} );
