'use strict';

const { action, utils } = require( 'api-testing' );

async function makeEditEntityRequest( params, entity ) {
	return action.getAnon().action( 'wbeditentity', {
		token: '+\\',
		data: JSON.stringify( entity ),
		...params
	}, 'POST' );
}

async function createEntity( type, entity ) {
	return makeEditEntityRequest( { new: type }, entity );
}

async function editEntity( id, entityData ) {
	return makeEditEntityRequest( { id }, entityData );
}

async function deleteProperty( propertyId ) {
	const admin = await action.mindy();
	return admin.action( 'delete', {
		title: `Property:${propertyId}`,
		token: await admin.token()
	}, 'POST' );
}

async function createUniqueStringProperty() {
	return await createEntity( 'property', {
		labels: { en: { language: 'en', value: `string-property-${utils.uniq()}` } },
		datatype: 'string'
	} );
}

/**
 * @param {Array} statements
 * @param {string} entityType
 */
async function createEntityWithStatements( statements, entityType ) {
	statements.forEach( ( statement ) => {
		statement.type = 'statement';
	} );

	const entity = { claims: statements };
	if ( entityType === 'property' ) {
		entity.datatype = 'string';
	}

	return await createEntity( entityType, entity );
}

/**
 * @param {Array} statements
 */
async function createItemWithStatements( statements ) {
	return await createEntityWithStatements( statements, 'item' );
}

/**
 * @param {Array} statements
 */
async function createPropertyWithStatements( statements ) {
	return await createEntityWithStatements( statements, 'property' );
}

/**
 * @param {string} redirectTarget - the id of the item to redirect to (target)
 * @return {Promise<string>} - the id of the item to redirect from (source)
 */
async function createRedirectForItem( redirectTarget ) {
	const redirectSource = ( await createEntity( 'item', {} ) ).entity.id;
	await action.getAnon().action( 'wbcreateredirect', {
		from: redirectSource,
		to: redirectTarget,
		token: '+\\'
	}, true );

	return redirectSource;
}

async function getLatestEditMetadata( entityId ) {
	const entityTitle = ( entityId.charAt( 0 ) === 'P' ) ? `Property:${entityId}` : `Item:${entityId}`;
	const editMetadata = ( await action.getAnon().action( 'query', {
		list: 'recentchanges',
		rctitle: entityTitle,
		rclimit: 1,
		rcprop: 'tags|flags|comment|ids|timestamp|user'
	} ) ).query.recentchanges[ 0 ];

	return {
		...editMetadata,
		timestamp: new Date( editMetadata.timestamp ).toUTCString()
	};
}

async function changeEntityProtectionStatus( entityId, allowedUserGroup ) {
	const mindy = await action.mindy();
	const pageNamespace = entityId.startsWith( 'Q' ) ? 'Item' : 'Property';
	await mindy.action( 'protect', {
		title: `${pageNamespace}:${entityId}`,
		token: await mindy.token(),
		protections: `edit=${allowedUserGroup}`,
		expiry: 'infinite'
	}, 'POST' );
}

/**
 * @param {string} propertyId
 * @return {{mainsnak: {datavalue: {type: string, value: string}, property: string, snaktype: string}}}
 */
function newLegacyStatementWithRandomStringValue( propertyId ) {
	return {
		mainsnak: {
			snaktype: 'value',
			datavalue: {
				type: 'string',
				value: 'random-string-value-' + utils.uniq()
			},
			property: propertyId
		},
		type: 'statement'
	};
}

/**
 * @param {string} propertyId
 * @return {{property: {id: string}, value: {type: string, content: string}}}
 */
function newStatementWithRandomStringValue( propertyId ) {
	return {
		property: {
			id: propertyId
		},
		value: {
			type: 'value',
			content: 'random-string-value-' + utils.uniq()
		}
	};
}

async function getLocalSiteId() {
	return ( await action.getAnon().meta(
		'wikibase',
		{ wbprop: 'siteid' }
	) ).siteid;
}

async function createLocalSitelink( itemId, articleTitle, badges = [] ) {
	const anon = action.getAnon();
	anon.req.set( 'X-Wikibase-CI-Badges', badges.join( ', ' ) );

	await anon.edit( articleTitle, { text: 'sitelink test' } );
	await anon.action( 'wbsetsitelink', {
		id: itemId,
		linksite: await getLocalSiteId(),
		linktitle: articleTitle,
		badges: badges.join( ', ' ),
		token: '+\\'
	}, true );
}

module.exports = {
	createEntity,
	editEntity,
	deleteProperty,
	createEntityWithStatements,
	createItemWithStatements,
	createPropertyWithStatements,
	createUniqueStringProperty,
	createRedirectForItem,
	getLatestEditMetadata,
	changeEntityProtectionStatus,
	newStatementWithRandomStringValue,
	newLegacyStatementWithRandomStringValue,
	getLocalSiteId,
	createLocalSitelink
};
