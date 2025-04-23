import classnames from 'classnames';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { templateUri } from '@wdg/config';

const cache = {};

export function SVG( { icon, className, size, height, width } ) {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ frag, setFrag ] = useState( cache[ icon ] ?? undefined );
	const ref = useRef();

	if ( size && ! height && ! width ) {
		height = size;
		width = size;
	}

	useEffect( () => {
		if ( frag && ! cache[ icon ] ) {
			cache[ icon ] = frag;
		}
	}, [ frag ] );

	useEffect( () => {
		if ( typeof cache[ icon ] === 'undefined' ) {
			setIsLoading( true );

			let uri = icon;

			if ( ! uri.match( /^https?:\/\// ) ) {
				if ( uri.match( /\// ) ) {
					uri = `${ templateUri }/${ icon.replace( /^\//, '' ) }`;
				} else {
					if ( ! uri.match( /\.svg$/ ) ) {
						uri = `${ uri }.svg`;
					}

					uri = `${ templateUri }/${ uri }`;
				}
			}

			fetch( uri )
				.then( ( response ) => {
					if ( response.status !== 200 ) {
						throw new Error( 'Invalid file path' );
					}

					if ( 'image/svg+xml' !== response.headers.get( 'Content-Type' ) ) {
						throw new Error( 'Invalid content type' );
					}

					return response.text();
				} )
				.then( ( xml ) => {
					const parser = new DOMParser();
					const frag = parser.parseFromString( xml, 'image/svg+xml' );
					setFrag( frag.documentElement );
				} )
				.catch( ( error ) => {
					setFrag( '' );
					console.log( error );
				} )
				.finally( () => {
					setIsLoading( false );
				} );
		}
	}, [ icon ] );

	useEffect( () => {
		if ( ref.current && frag ) {
			while ( ref.current.firstChild ) {
				ref.current.removeChild( ref.current.firstChild );
			}

			const svg = frag.cloneNode( true );

			if ( height ) {
				svg.setAttribute( 'height', height );
			}

			if ( width ) {
				svg.setAttribute( 'width', width );
			}

			svg.querySelectorAll( '[fill]' ).forEach( ( node ) => node.setAttribute( 'fill', 'currentColor' ) );
			svg.querySelectorAll( '[stroke]' ).forEach( ( node ) => node.setAttribute( 'stroke', 'currentColor' ) );

			ref.current.appendChild( svg );
		}
	}, [ frag ] );

	return (
		<span
			className={ classnames( 'svg', `svg--${ icon }`, className ) }
			ref={ ref }
		>
			{ isLoading && <Spinner /> }
		</span>
	);
}

SVG.save = function ( { icon, height, width } ) {
	let props = {
		icon,
		height,
		width,
	};

	if ( size && ! height && ! width ) {
		props = {
			...props,
			height: size,
			width: size,
		};
	}

	return <icon { ...props } />;
};
