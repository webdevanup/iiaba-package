import { FocalPointPicker } from "@wordpress/components";

( () => {
	/**
	 * Render a focal point picker via an HTMLElement
	 *
	 * @param {HTMLElement} node
	 * @param {string} props.url url
	 * @param {object} props.value the focal point object with x and y
	 * @param {function} props.onChange - callback for when the react component re-renders
	 */
	function render( node, { url, value, onChange } ) {
		if ( ! node ) {
			return;
		}

		const root = wp.element.createRoot( node );

		const props = {
			url,
			value,
			onChange: ( value ) => {
				root.render( <FocalPointPicker {...props} value={ value} /> );
				onChange( value );
			},
		};

		root.render( <FocalPointPicker {...props} /> );
	}

	/**
	 * Extends a backbone view with a focal point component if the mime is image
	 *
	 * @param {object} view
	 * @param {string} template
	 * @returns {string|array}
	 */
	function focalPointExtendView( view, template = 'attachment-details' ) {
		const html = wp.media.template( template )( view ); // the template to extend

		if ( ! view.mime || ! view.mime.match( /^image\// ) ) {
			return html;
		}

		const dom = document.createElement( 'div' );
		dom.innerHTML = html;

		const compat = dom.querySelector( '.attachment-compat' );

		const setting = document.createElement( 'span' );
		setting.classList.add( 'setting', 'focal-point' );
		setting.dataset.setting = 'focal_point';

		// try to insert as the last setting before the compat section
		if ( compat ) {
			compat.parentNode.insertBefore( setting, compat );
		} else {
			dom.appendChild( setting );
		}

		const label = document.createElement( 'label' );
		label.classList.add( 'focal-point__label', 'name' );
		label.appendChild( document.createTextNode( 'Focal Point' ) );
		setting.appendChild( label );

		const control = document.createElement( 'span' );
		control.classList.add( 'focal-point__control' );
		setting.appendChild( control );

		render( control, {
			url: view?.sizes?.medium?.url ?? view.url,
			value: view.focal_point,
			onChange: ( value ) => {
				this.save( 'focal_point', value );
			},
		} );

		return dom.children;
	}

	/**
	 * Render the meta box on the attachment edit screen
	 *
	 * @param {string|HTMLElement} node the meta box reference
	 * @returns void
	 */
	function renderMetaBox( node = '#attachment-focal-point' ) {
		node = typeof node === 'string' ? document.querySelector( node ) : node;

		if ( ! node ) {
			return;
		}

		const x = node.querySelector( 'input[name="focal_point[x]"]' );
		const y = node.querySelector( 'input[name="focal_point[y]"]' );
		const slot = node.querySelector( '#attachment-focal-point-slot' );

		render( slot, {
			url: slot.dataset.src,
			value: {
				x: x.value,
				y: y.value,
			},
			onChange: ( value ) => {
				x.value = value.x;
				y.value = value.y;
			},
		} );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		// select mode
		if ( wp?.media?.view?.Attachment?.Details ) {
			wp.media.view.Attachment.Details = wp.media.view.Attachment.Details.extend( {
				template: function ( view ) {
					return focalPointExtendView.call( this, view, 'attachment-details' );
				},
			} );
		}

		// details mode
		if ( wp?.media?.view?.Attachment?.Details?.TwoColumn ) {
			wp.media.view.Attachment.Details.TwoColumn = wp.media.view.Attachment.Details.TwoColumn.extend( {
				template: function ( view ) {
					return focalPointExtendView.call( this, view, 'attachment-details-two-column' );
				},
			} );
		}

		renderMetaBox();
	} );
} )();
