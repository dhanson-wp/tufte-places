( function () {
	var el = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;

	var MAP_BLOCKS = [ 'core/image', 'core/gallery' ];

	// Mirrors tufte_places_normalize_caption() in includes/render.php.
	function normalize( value ) {
		var html = value ? String( value ) : '';
		if ( ! html ) {
			return '';
		}
		var text = new DOMParser().parseFromString( html, 'text/html' ).body.textContent;
		return ( text || '' ).replace( /\u00A0/g, ' ' ).trim();
	}

	// Prefer the raw caption (edit context). Fall back to the rendered caption
	// (view context) once the edit request has finished without a record.
	function libraryCaption( lookup ) {
		if ( ! lookup ) {
			return '';
		}
		var edit = lookup.edit;
		if ( edit && edit.caption && typeof edit.caption.raw === 'string' ) {
			return normalize( edit.caption.raw );
		}
		if ( ! lookup.editDone ) {
			return '';
		}
		var view = lookup.view;
		return view && view.caption ? normalize( view.caption.rendered ) : '';
	}

	addFilter(
		'blocks.registerBlockType',
		'tufte-places/attributes',
		function ( settings, name ) {
			if ( MAP_BLOCKS.indexOf( name ) === -1 ) {
				return settings;
			}
			settings.attributes = Object.assign( {}, settings.attributes, {
				tufteHideFromMap: { type: 'boolean', default: false },
			} );
			return settings;
		}
	);

	var withPlaceControls = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var isMapBlock = MAP_BLOCKS.indexOf( props.name ) !== -1;
			var isImage = props.name === 'core/image';
			var attachmentId = isImage ? props.attributes.id : 0;
			var handled = useRef( 0 );

			var lookup = useSelect(
				function ( select ) {
					if ( ! attachmentId ) {
						return null;
					}
					var core = select( 'core' );
					var editArgs = [ attachmentId, { context: 'edit' } ];
					return {
						edit: core.getMedia( editArgs[ 0 ], editArgs[ 1 ] ),
						editDone: core.hasFinishedResolution( 'getMedia', editArgs ),
						view: core.getMedia( attachmentId, { context: 'view' } ),
					};
				},
				[ attachmentId ]
			);

			var library = libraryCaption( lookup );
			var blockCaption = isImage ? normalize( props.attributes.caption ) : '';

			useEffect(
				function () {
					if ( ! attachmentId || ! library || handled.current === attachmentId ) {
						return;
					}
					handled.current = attachmentId;
					if ( blockCaption && blockCaption === library ) {
						props.setAttributes( { caption: '' } );
					}
				},
				[ attachmentId, library, blockCaption ]
			);

			if ( ! isMapBlock ) {
				return el( BlockEdit, props );
			}

			var showLibraryCaption = isImage && attachmentId && library && ! blockCaption;

			var children = [
				el( BlockEdit, Object.assign( { key: 'edit' }, props ) ),
				el(
					InspectorControls,
					{ key: 'place' },
					el(
						PanelBody,
						{ title: __( 'Place', 'tufte-places' ), initialOpen: true },
						showLibraryCaption &&
							el(
								'p',
								{ className: 'tufte-places-library-caption' },
								__( 'Library caption:', 'tufte-places' ) + ' ' + library
							),
						el( ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Keep off the map', 'tufte-places' ),
							help: __( 'Still shows its place on the page.', 'tufte-places' ),
							checked: !! props.attributes.tufteHideFromMap,
							onChange: function ( value ) {
								props.setAttributes( { tufteHideFromMap: !! value } );
							},
						} )
					)
				),
			];

			return el( wp.element.Fragment, null, children );
		};
	}, 'withPlaceControls' );

	addFilter( 'editor.BlockEdit', 'tufte-places/place-controls', withPlaceControls );
}() );
