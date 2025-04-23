import { Button, Spinner, TextControl, BaseControl, Flex, FlexBlock, FlexItem, Popover } from '@wordpress/components';
import { Icon, dragHandle } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';
import { Sortable } from './sortable.js';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Multi-user search control
 */
export function UsersControl( {
	help,
	label = 'Users',
	searchLabel = 'Search Users',
	max = 0,
	onChange,
	sortable = true,
	value = [],
	roles,
	searchColumns = null,
	disabled = false,
} ) {
	const selected = useSelect( select => value.map( select('core').getUser ) );
	const ItemsTag = sortable ? Sortable : 'ul';
	const [ search, setSearch ] = useState( '' );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isOpen, setIsOpen ] = useState( false );
	const [ popoverRef, setPopoverRef ] = useState();
	const [ anchorRef, setAnchorRef ] = useState();

	function isFocused() {
		let node = document.activeElement;

		while ( node && node.parentNode ) {
			if ( [ popoverRef, anchorRef ].includes( node ) ) {
				return true;
			}

			node = node.parentNode;
		}

		return false;
	}

	useEffect( () => {
		const timeout = setTimeout( () => {
			setSearch( searchInput );
		}, 500 );

		return () => {
			if ( timeout ) {
				clearTimeout( timeout );
			}
		}
	}, [ searchInput ] );

	useEffect( () => {
		if ( search ) {
			const params = new URLSearchParams( {
				per_page: 100,
				search,
				order: 'asc',
				orderby: 'name',
				exclude: value,
			} );

			if ( searchColumns ) {
				params.append( 'search_columns', searchColumns );
			}

			setIsLoading( true );
			setIsOpen( true );
			apiFetch( {
				path: `/wp/v2/users?${ params.toString() }`,
				per_page: -1,
			} ).then( ( response ) => {
				setResults( response )
			} ).catch( ( error ) => {
				console.error( error );
				setResults( null );
			} ).finally( () => {
				setIsLoading( false );
			} );
		} else {
			setResults( null );
		}
	}, [ search, value, roles, searchColumns ] )

	const itemsProps = {
		value,
		className: 'wdg-users-control',
		style: {
			marginTop: '0',
		}
	};

	if ( sortable ) {
		itemsProps.onSort = (v) => onChange( v.map( Number ) );
		itemsProps.tagName = 'ul';
		itemsProps.handle = '.wdg-users-control__handle';
	}

	return (
		<BaseControl help={ help } label={ label } className="wdg-users-control">
			{ ( selected && selected.length > 0 ) && (
				<ItemsTag {...itemsProps}>
					{ selected.map( ( user, index ) => (
						<li key={ value[ index ] }>
							<Flex align="center">
								{ sortable && (
									<FlexItem>
										<Icon className="wdg-users-control__handle" icon={ dragHandle } />
									</FlexItem>
								) }
								<FlexBlock>
									{ user ? (
										`${user.name} (${user.id})`
									) : (
										<Spinner />
									) }
								</FlexBlock>
								<FlexItem>
									<Button
										variant="link"
										isDestructive
										icon="no"
										onClick={ () => onChange( value.filter( v => v !== user.id ) ) }
										style={ { textDecoration: 'none' } }
									/>
								</FlexItem>
							</Flex>
						</li>
					) ) }
				</ItemsTag>
			) }
			{ ( max === 0 || value.length < max ) && (
				<div ref={ setAnchorRef }>
					<TextControl
						label={ searchLabel }
						onChange={ setSearchInput }
						value={ searchInput }
						onFocus={ () => setIsOpen( true ) }
						onBlur={ () => setTimeout( () => setIsOpen( isFocused() ), 50 ) }
						autoComplete="off"
						disabled={ disabled }
					/>
					{ ( isOpen ) && (
						<Popover
							headerTitle="Results"
							focusOnMount={ false }
							className="wdg-users-control__popover"
							onFocusOutside={ () => setIsOpen( false ) }
							ref={ setPopoverRef }
							resize
							variant="toolbar"
						>
							{ isLoading && <Spinner /> }
							{ results && results.length > 0 ? (
								<ol>
									{ results.length > 1 && (
										<li
											style={ { padding: '0.5rem' } }
										>
											<Button
												variant="link"
												onClick={ () => onChange( [ ...value, ...results.map( ( { id } ) => id ) ] ) }
												children="Select All"
											/>
										</li>
									) }
									{ results.map( ( user ) => (
										<li
											key={ user.id }
											style={ { padding: '0.5rem' } }
										>
											<Button
												variant="link"
												onClick={ () => onChange( [ ...value, user.id ] ) }
												children={ `${user.name} (${ user.id })` }
											/>
										</li>
									) ) }
								</ol>
							) : (
								'No Match'
							) }
						</Popover>
					) }
				</div>
			) }

			{ max > 0 && value.length >= max ? <mark>Maximum number of users reached</mark> : null }
		</BaseControl>
	);
}

/**
 * Single user select component
 */
export function UserControl( {
	value = 0,
	onChange,
	...props
} ) {
	return (
		<UsersControl
			{ ...props }
			value={ [ value ] }
			onChange={ ( users ) => onChange( users && users.length > 1 ? users[0] : null ) }
			max={ 1 }
		/>
	);
}
