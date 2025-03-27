/**
 * @license GPL-2.0-or-later
 * @author H. Snater < mediawiki@snater.com >
 */
( function () {
	'use strict';

	var datamodel = require( 'wikibase.datamodel' );

	/**
	 *  @return {datamodel.Fingerprint}
	 */
	function createFingerprint() {
		return new datamodel.Fingerprint(
			new datamodel.TermMap( {
				de: new datamodel.Term( 'de', 'de-label' ),
				en: new datamodel.Term( 'en', 'en-label' ),
				it: new datamodel.Term( 'it', 'it-label' ),
				fa: new datamodel.Term( 'fa', 'fa-label' )
			} ),
			new datamodel.TermMap( {
				de: new datamodel.Term( 'de', 'de-description' ),
				en: new datamodel.Term( 'en', 'en-description' ),
				it: new datamodel.Term( 'it', 'it-description' ),
				fa: new datamodel.Term( 'fa', 'fa-description' ),
				nl: new datamodel.Term( 'nl', 'nl-description' )
			} ),
			new datamodel.MultiTermMap( {
				de: new datamodel.MultiTerm( 'de', [ 'de-alias' ] ),
				en: new datamodel.MultiTerm( 'en', [ 'en-alias' ] ),
				it: new datamodel.MultiTerm( 'it', [ 'it-alias' ] ),
				fa: new datamodel.MultiTerm( 'fa', [ 'fa-alias' ] )
			} )
		);
	}

	/**
	 * @param {Object} [options]
	 * @return {jQuery}
	 */
	function createEntitytermsforlanguagelistview( options ) {
		options = Object.assign( {
			value: createFingerprint(),
			userLanguages: [ 'de', 'en' ]
		}, options || {} );

		return $( '<table>' )
			.appendTo( document.body )
			.addClass( 'test_entitytermsforlanguagelistview' )
			.entitytermsforlanguagelistview( options );
	}

	QUnit.module( 'jquery.wikibase.entitytermsforlanguagelistview', QUnit.newMwEnvironment( {
		afterEach: function () {
			$( '.test_entitytermsforlanguagelistview' ).each( function () {
				var $entitytermsforlanguagelistview = $( this ),
					entitytermsforlanguagelistview
						= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

				if ( entitytermsforlanguagelistview ) {
					entitytermsforlanguagelistview.destroy();
				}

				$entitytermsforlanguagelistview.remove();
			} );
		}
	} ) );

	QUnit.test( 'Create & destroy (with mul disabled)', ( assert ) => {
		assert.throws(
			() => {
				createEntitytermsforlanguagelistview( { value: null } );
			},
			'Throwing error when trying to initialize widget without a value.'
		);
		mw.config.set( { wbEnableMulLanguageCode: false } );

		var $entitytermsforlanguagelistview = createEntitytermsforlanguagelistview(),
			entitytermsforlanguagelistview
				= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		assert.notStrictEqual(
			entitytermsforlanguagelistview,
			undefined,
			'Created widget.'
		);
		assert.deepEqual(
			entitytermsforlanguagelistview._defaultLanguages,
			[ 'de', 'en' ],
			'Default languages without "mul".'
		);

		entitytermsforlanguagelistview.destroy();

		assert.strictEqual(
			$entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' ),
			undefined,
			'Destroyed widget.'
		);
	} );

	QUnit.test( 'setError()', ( assert ) => {
		var done = assert.async();

		var $entitytermsforlanguagelistview = createEntitytermsforlanguagelistview(),
			entitytermsforlanguagelistview
				= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		$entitytermsforlanguagelistview
		.on( 'entitytermsforlanguagelistviewtoggleerror', ( event, error ) => {
			assert.true(
				true,
				'Triggered "toggleerror" event.'
			);
			done();
		} );

		entitytermsforlanguagelistview.setError();
	} );

	QUnit.test( 'value()', ( assert ) => {
		var $entitytermsforlanguagelistview = createEntitytermsforlanguagelistview(),
			entitytermsforlanguagelistview
				= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		assert.strictEqual(
			entitytermsforlanguagelistview.value().equals( createFingerprint() ),
			true,
			'Retrieved value.'
		);

		assert.throws(
			() => {
				entitytermsforlanguagelistview.value( [] );
			},
			'Throwing error when trying to set a new value.'
		);
	} );

	QUnit.test( '_getMoreLanguages()', ( assert ) => {
		var $entitytermsforlanguagelistview = createEntitytermsforlanguagelistview(),
			entitytermsforlanguagelistview
				= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		assert.deepEqual(
			entitytermsforlanguagelistview._getMoreLanguages(),
			{ fa: 'fa', it: 'it', nl: 'nl' }
		);
	} );

	QUnit.test( '_hasMoreLanguages()', ( assert ) => {
		var $entitytermsforlanguagelistview = createEntitytermsforlanguagelistview(),
			entitytermsforlanguagelistview
				= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		assert.strictEqual( entitytermsforlanguagelistview._hasMoreLanguages(), true );

		$entitytermsforlanguagelistview = createEntitytermsforlanguagelistview( {
			userLanguages: [ 'de', 'en', 'fa', 'it', 'nl' ]
		} );
		entitytermsforlanguagelistview
			= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		assert.strictEqual( !entitytermsforlanguagelistview._hasMoreLanguages(), true );
	} );

	QUnit.test( 'mul handling ', ( assert ) => {
		var fingerprint = createFingerprint();
		fingerprint.setLabel( 'mul', new datamodel.Term( 'mul', 'mul-label' ) );
		mw.config.set( { wbEnableMulLanguageCode: true } );

		var $entitytermsforlanguagelistview = createEntitytermsforlanguagelistview(),
			entitytermsforlanguagelistview
				= $entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );

		assert.deepEqual(
			entitytermsforlanguagelistview._defaultLanguages,
			[ 'mul', 'de', 'en' ],
			'"mul" should always be added to the default languages, even if it has no term.'
		);
	} );

}() );
