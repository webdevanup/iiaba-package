import classNames from 'classnames';
import apiFetch from '@wordpress/api-fetch';
import { BaseControl, Button, Dashicon, Spinner, TextControl, Popover, Flex, FlexItem, FlexBlock } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { Icon, dragHandle } from '@wordpress/icons';
import { useDebounce } from '../hooks/use-debounce.js';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Sortable } from './sortable.js';

/**
 * PostControl
 *
 * - expects value to be an object of id, type
 * - onChange receives and object of id, type, and the full post object if applicable
 */
export function PostControl( {
	// prettier-ignore
	help,
	label,
	onChange,
	placeholder = 'Search...',
	postType,
	showPostType = false,
	value = null,
	multiple = false,
	...props
} ) {
	if ( multiple ) {
		return (
			<PostsControl
				{ ...props }
				help={ help }
				label={ label }
				onChange={ onChange }
				placeholder={ placeholder }
				postType={ postType }
				showPostType={ false }
				value={ Array.isArray( value ) ? value : [ value ] }
			/>
		);
	}

	return (
		<BaseControl
			help={ help }
			label={ label }
			className="post-control"
		>
			<div className="post-control__wrap">
				{ value?.id && value?.type ? (
					<PostResult
						id={ value.id }
						type={ value.type }
						showPostType={ showPostType }
						onRemove={ () => onChange( { id: 0, type: '' } ) }
					/>
				) : null }

				{ ! value?.id || ! value?.type ? (
					<PostSearch
						exclude={ value && value.id ? [ value.id ] : [] }
						postType={ postType }
						placeholder={ placeholder }
						onSelect={ ( post ) => onChange( { id: post.id, type: post.subtype || post.type }, post ) }
						showPostType={ showPostType }
					/>
				) : null }
			</div>
		</BaseControl>
	);
}

/**
 * PostsControl
 *
 * - value is the same as PostControl but an array of those objects
 */
export function PostsControl( {
	// prettier-ignore
	help,
	label,
	max = 0,
	onChange,
	placeholder = 'Search...',
	postType,
	showPostType = false,
	value = [],
} ) {
	return (
		<BaseControl
			help={ help }
			label={ label }
			className="post-control"
		>
			<div className="post-control__wrap">
				{ value && value.length ? (
					<Sortable
						value={ value }
						onSort={ onChange }
						handle=".post-control__handle"
					>
						{ value.map( ( val, index ) => (
							<Flex key={ val?.id || `resolving-${ index }` }>
								{ value.length > 1 && (
									<FlexItem>
										<Icon
											icon={ dragHandle }
											className="post-control__handle"
										/>
									</FlexItem>
								) }
								<FlexBlock>
									<PostResult
										id={ val.id }
										type={ val.type }
										showPostType={ showPostType }
										onRemove={ () => onChange( value.filter( ( obj ) => val !== obj ) ) }
									/>
								</FlexBlock>
							</Flex>
						) ) }
					</Sortable>
				) : null }

				{ ( ! value || max < 1 || ( max >= 1 && value.length <= max ) ) && (
					<PostSearch
						exclude={ value.map( ( v ) => v.id ) || [] }
						postType={ postType }
						placeholder={ placeholder }
						onSelect={ ( { id, type } ) => onChange( [ ...value, { id, type } ] ) }
						showPostType={ showPostType }
					/>
				) }
			</div>
		</BaseControl>
	);
}

export function PostSearchTitle( {
	// prettier-ignore
	post,
	showPostType = false,
} ) {
	const postType = useSelect( ( select ) => select( 'core' ).getPostType( post.subtype || post.type ) );

	return (
		<span className="post-control__result-title">
			{ showPostType && postType?.labels?.singular_name ? <span className="post-control__item-label">{ postType.labels.singular_name }</span> : null }
			{ decodeEntities( typeof post?.title?.rendered !== 'undefined' ? post.title.rendered : post.title ) } ( { post.id } )
		</span>
	);
}

export function RecentPosts( {
	//prettier-ignore
	onSelect,
	showPostType,
	postType,
	exclude = [],
} ) {
	const posts = useSelect( ( select ) => {
		let posts = [];

		switch ( true ) {
			case typeof postType === 'string':
				posts = select( 'core' ).getEntityRecords( 'postType', postType, { exclude } );
				break;
			case Array.isArray( postType ):
				if ( postType.length === 1 ) {
					posts = select( 'core' ).getEntityRecords( 'postType', postType[ 0 ], { exclude } );
				} else {
					// figure out a way to have multi post type recent records
					posts = [];
				}
				break;
		}

		return posts;
	} );

	if ( posts === null ) {
		return <Spinner />;
	}

	if ( posts && posts.length ) {
		return (
			<>
				<p style={ { margin: '1em 1em 0 1em' } }>
					<em>Recently Published</em>
				</p>

				<PostSearchResults
					posts={ posts }
					onSelect={ onSelect }
					showPostType={ showPostType }
				/>
			</>
		);
	}

	return null;
}

export function PostSearch( {
	// prettier-ignore
	className = '',
	exclude,
	help,
	label = 'Search',
	onSelect,
	placeholder,
	showPostType = false,
	postType,
} ) {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isOpen, setIsOpen ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ searchResults, setSearchResults ] = useState( [] );
	const [ exactResults, setExactResults ] = useState( [] );
	const [ exactId, setExactId ] = useState( null );
	const [ anchorRef, setAnchorRef ] = useState();
	const [ popoverRef, setPopoverRef ] = useState();

	function reset() {
		setIsLoading( false );
		setExactResults( [] );
		setSearchResults( [] );
		setSearch( '' );
		setIsOpen( false );
	}

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

	function onSelectPost( post ) {
		onSelect( {
			id: post.id,
			type: post.subtype || post.type,
		} );

		reset();
	}

	useEffect( () => {
		if ( exactId ) {
			apiFetch( {
				path: `/wp/v2/search?${ new URLSearchParams( {
					per_page: 10,
					search: '',
					type: 'post',
					subtype: 'any',
					include: [ search ],
					exclude: exclude,
				} ).toString() }`,
			} )
				.then( ( exactResults ) => {
					setIsOpen( true );
					setExactResults( exactResults );
				} )
				.catch( ( error ) => {
					console.error( error );
					setExactResults( [] );
				} );
		} else {
			setExactResults( [] );
		}
	}, [ exactId ] );

	useEffect( () => {
		if ( ! search ) {
			setExactResults( [] );
			setSearchResults( [] );
		}
	}, [ search ] );

	useDebounce(
		() => {
			if ( search ) {
				if ( search.match( /^[\d]+$/ ) ) {
					setExactId( search );
				} else {
					setExactId();
				}

				setIsLoading( true );

				apiFetch( {
					path: `/wp/v2/search?${ new URLSearchParams( {
						per_page: 100,
						search: search,
						type: 'post',
						subtype: postType || 'any',
						lang: '',
						exclude,
					} ).toString() }`,
				} )
					.then( ( searchResults ) => {
						setIsOpen( true );
						setSearchResults( searchResults );
					} )
					.catch( ( error ) => {
						console.error( error );
						setSearchResults( [] );
					} )
					.finally( () => {
						setIsLoading( false );
					} );
			}
		},
		[ search ],
		300
	);

	useEffect( () => {
		if ( anchorRef && popoverRef ) {
			const content = popoverRef.querySelector( '.components-popover__content' );

			if ( content ) {
				content.style.minWidth = `${ anchorRef.offsetWidth }px`;
			}
		}
	}, [ popoverRef, anchorRef ] );

	const exactIds = exactResults.map( ( p ) => p.id );

	const results = [ ...exactResults, ...searchResults.filter( ( result ) => ! exactIds.includes( result.id ) ) ];

	return (
		<BaseControl
			label={ label }
			help={ help }
			className={ classNames( 'post-control__search', className, {
				'post-control__search--loading': isLoading,
			} ) }
		>
			<div
				className="post-control__search-ref"
				ref={ setAnchorRef }
			>
				<TextControl
					onChange={ setSearch }
					value={ search }
					placeholder={ placeholder }
					onFocus={ () => setIsOpen( !! postType && ! search ) }
					onBlur={ () => setTimeout( () => setIsOpen( isFocused() ), 50 ) }
				/>
				{ isOpen ? (
					<Popover
						headerTitle="Results"
						focusOnMount={ false }
						className="post-control__popover"
						ref={ setPopoverRef }
						onFocusOutside={ () => setIsOpen( false ) }
					>
						{ isLoading && <Spinner /> }

						{ results && results.length ? (
							<PostSearchResults
								posts={ [ ...( exactResults || [] ), ...( results || [] ) ] }
								onSelect={ onSelectPost }
								showPostType={ showPostType }
							/>
						) : !! postType && ! search ? (
							<RecentPosts
								postType={ postType }
								onSelect={ onSelectPost }
								showPostType={ showPostType }
								exclude={ exclude }
							/>
						) : null }
					</Popover>
				) : null }
			</div>
		</BaseControl>
	);
}

function PostSearchResults( {
	// prettier-ignore
	posts,
	onSelect,
	showPostType,
} ) {
	const ids = posts.map( ( p ) => p.id );

	posts = posts.filter( ( post, index ) => ids.indexOf( post.id ) === index );

	return (
		<div className="post-control__results">
			{ posts.map( ( post ) => (
				<Button
					className="post-control__result"
					onClick={ () => onSelect( post ) }
					key={ post.id }
				>
					<Dashicon icon="insert" />
					<PostSearchTitle
						post={ post }
						showPostType={ showPostType }
					/>
				</Button>
			) ) }
		</div>
	);
}

export function PostResult( {
	// prettier-ignore
	id,
	type,
	showPostType = false,
	onRemove,
} ) {
	const post = useSelect( ( select ) => select( 'core' ).getEntityRecord( 'postType', type, id ) );
	const postTypeObj = useSelect( ( select ) => ( showPostType ? select( 'core' ).getPostType( type ) : null ) );

	return (
		<div className="post-control__item">
			{ showPostType && post && postTypeObj && <span className="post-control__item-label">{ postTypeObj.labels.singular_name }</span> }

			<span className="post-control__item-title">{ post ? `${ decodeEntities( post.title.rendered ) }( ${ id } )` : <Spinner /> }</span>

			<Button
				isDestructive
				onClick={ () => onRemove( { id, type } ) }
				className="post-control__item-remove"
				variant="tertiary"
			>
				<Dashicon icon="remove" />
			</Button>
		</div>
	);
}
