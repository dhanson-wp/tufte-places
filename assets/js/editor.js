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
		var holder = document.createElement( 'div' );
		holder.innerHTML = html;
		return ( holder.textContent || '' ).replace( / /g, ' ' ).trim();
	}

	function libraryCaption( media ) {
		if ( ! media || ! media.caption ) {
			return '';
		}
		var caption = media.caption;
		return normalize( caption.raw ? caption.raw : caption.rendered );
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

			var media = useSelect(
				function ( select ) {
					if ( ! attachmentId ) {
						return null;
					}
					return select( 'core' ).getMedia( attachmentId, { context: 'view' } );
				},
				[ attachmentId ]
			);

			var library = libraryCaption( media );
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

			var children = [
				el( BlockEdit, Object.assign( { key: 'edit' }, props ) ),
				el(
					InspectorControls,
					{ key: 'place' },
					el(
						PanelBody,
						{ title: __( 'Place', 'tufte-places' ), initialOpen: true },
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

			if ( isImage && attachmentId && library && ! blockCaption ) {
				children.push(
					el(
						'p',
						{ key: 'library-caption', className: 'tufte-places-library-caption', style: { opacity: 0.7, fontSize: '0.85em', margin: '0.5em 0' } },
						__( 'Library caption:', 'tufte-places' ) + ' ' + library
					)
				);
			}

			return el( wp.element.Fragment, null, children );
		};
	}, 'withPlaceControls' );

	addFilter( 'editor.BlockEdit', 'tufte-places/place-controls', withPlaceControls );
}() );
