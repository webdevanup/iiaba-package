import { useRef, useEffect } from '@wordpress/element';

export function useDebounce( func, deps = [], timeout = 200 ) {
	const isntRendered = useRef( true );

	useEffect(
		() => {
			if ( isntRendered.current ) {
				isntRendered.current = false;
				return () => {};
			}

			let timer = setTimeout( func, timeout );
			return () => clearTimeout( timer );
		},
		deps
	);
}
