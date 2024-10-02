'use strict';

const { utils } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const { createEntity, createRedirectForItem } = require( '../helpers/entityHelper' );
const { newGetItemAliasesRequestBuilder } = require( '../helpers/RequestBuilderFactory' );

describe( newGetItemAliasesRequestBuilder().getRouteDescription(), () => {

	let testItemId;
	let lastRevisionId;

	before( async () => {
		const createItemResponse = await createEntity( 'item', {
			aliases: {
				de: [
					{ language: 'de', value: 'a-German-alias-' + utils.uniq() },
					{ language: 'de', value: 'another-German-alias-' + utils.uniq() }
				],
				en: [
					{ language: 'en', value: 'an-English-alias-' + utils.uniq() },
					{ language: 'en', value: 'another-English-alias-' + utils.uniq() }
				]
			}
		} );

		testItemId = createItemResponse.entity.id;
		lastRevisionId = createItemResponse.entity.lastrevid;
	} );

	it( '200 OK response is valid for an Item with two aliases', async () => {
		const response = await newGetItemAliasesRequestBuilder( testItemId ).makeRequest();

		expect( response ).to.have.status( 200 );
		expect( response ).to.satisfyApiSpec;
	} );

	it( '304 Not Modified response is valid', async () => {
		const response = await newGetItemAliasesRequestBuilder( testItemId )
			.withHeader( 'If-None-Match', `"${lastRevisionId}"` )
			.makeRequest();

		expect( response ).to.have.status( 304 );
		expect( response ).to.satisfyApiSpec;
	} );

	it( '308 Permanent Redirect response is valid for a redirected item', async () => {
		const redirectSourceId = await createRedirectForItem( testItemId );

		const response = await newGetItemAliasesRequestBuilder( redirectSourceId ).makeRequest();

		expect( response ).to.have.status( 308 );
		expect( response ).to.satisfyApiSpec;
	} );

	it( '400 Bad Request response is valid for an invalid item ID', async () => {
		const response = await newGetItemAliasesRequestBuilder( 'X123' ).makeRequest();

		expect( response ).to.have.status( 400 );
		expect( response ).to.satisfyApiSpec;
	} );

	it( '404 Not Found response is valid for a non-existing item', async () => {
		const response = await newGetItemAliasesRequestBuilder( 'Q99999' ).makeRequest();

		expect( response ).to.have.status( 404 );
		expect( response ).to.satisfyApiSpec;
	} );

} );
