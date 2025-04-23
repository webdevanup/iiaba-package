import SortableJS from 'sortablejs';
import { useRef, useEffect, useState, cloneElement } from '@wordpress/element';

/**
 * Sortable component using the Sortablejs library
 *
 * - auto applies sortable values to children
 * - value prop is required as array
 *
 * @see https://github.com/SortableJS/Sortable#readme
 */
export function Sortable( {
	// prettier-ignore
	tagName = 'div',
	value = [],
	className = '',
	children,
	animation = 150,
	swap = true,
	handle = undefined,
	onSort,
	sortableOpts = {},
	...props
} ) {
	const [ sortable, setSortable ] = useState( null );
	const Tag = tagName;
	const ref = useRef( null );

	useEffect( () => {
		if ( ref.current ) {
			setSortable(
				new SortableJS( ref.current, {
					sort: true,
					animation,
					swap,
					handle,
					onSort: function () {
						const sorted = this.toArray().map( ( val ) => {
							if ( 'string' === typeof val ) {
								try {
									return JSON.parse( val );
								} catch ( fault ) {}
							}

							return val;
						} );

						onSort( sorted, this );
					},
					...sortableOpts,
					dataIdAttr: 'data-sortable-value',
				} )
			);
		}
	}, [ ref.current ] );

	useEffect( () => {
		if ( sortable ) {
			sortable.sort( value );
		}
	}, [ value ] );

	return (
		<Tag
			className={ className }
			ref={ ref }
			{ ...props }
		>
			{ children.map( ( elem, index ) => {
				const sortableValue = typeof value[ index ] === 'object' ? JSON.stringify( value[ index ] ) : value[ index ];

				return cloneElement( elem, { 'data-sortable-value': sortableValue } );
			} ) }
		</Tag>
	);
}
