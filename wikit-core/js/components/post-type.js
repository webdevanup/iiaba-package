import classNames from 'classnames';
import { useSelect } from '@wordpress/data';
import { BaseControl, ToggleControl, Spinner, RadioControl } from '@wordpress/components';

export function PostTypeControl( {
	help = 'Select post types.',
	label = 'Post Types',
	className = '',
	onChange,
	allowed = [],
	denied = [],
	viewable = true,
	value = [],
	multiple = true,
} ) {
	const postTypes = useSelect( ( select ) => {
		let postTypes = select( 'core' ).getPostTypes( { per_page: -1 } );

		if ( postTypes ) {
			if ( allowed.length ) {
				postTypes = postTypes.filter( ( postType ) => allowed.includes( postType.slug ) );
			} else if ( viewable ) {
				postTypes = postTypes.filter(
					( postType ) => postType.viewable && ! [ 'attachment' ].includes( postType.slug )
				);
			}

			if ( denied.length ) {
				postTypes = postTypes.filter( ( postType ) => ! denied.includes( postType.slug ) );
			}
		}

		return postTypes;
	} );

	if ( multiple && ! Array.isArray( value ) ) {
		value = [ value ];
	} else if ( ! multiple && Array.isArray( value ) ) {
		value = value[ 0 ];
	}

	if ( postTypes === null ) {
		return <Spinner />;
	}

	if ( Array.isArray( postTypes ) && ! postTypes.length ) {
		console.error( 'There are no post types to select', { allowed, denied, viewable } );

		return 'There are no post types to select - check the browser console';
	}

	return (
		<BaseControl
			help={ help }
			label={ label }
			className={ classNames( 'wdg-post-type-control', className ) }
			style={ { margin: '0' } }
		>
			{ multiple ? (
				postTypes.map( ( postType ) => (
					<ToggleControl
						label={ postType.name }
						key={ postType.slug }
						checked={ value.includes( postType.slug ) }
						onChange={ ( checked ) => {
							let checkedPostTypes = Array.from( value );

							if ( checked && ! checkedPostTypes.includes( postType.slug ) ) {
								checkedPostTypes.push( postType.slug );
							} else if ( ! checked && checkedPostTypes.includes( postType.slug ) ) {
								checkedPostTypes = checkedPostTypes.filter( ( pt ) => pt !== postType.slug );
							}

							onChange( checkedPostTypes );
						} }
					/>
				) )
			) : (
				<RadioControl
					selected={ value }
					options={ postTypes.map( ( postType ) => {
						return {
							label: postType.name,
							value: postType.slug,
						};
					} ) }
					onChange={ onChange }
				/>
			) }
		</BaseControl>
	);
}
