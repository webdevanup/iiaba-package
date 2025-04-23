import classNames from 'classnames';
import apiFetch from '@wordpress/api-fetch';
import { BaseControl, Button, Dashicon, Flex, FlexBlock, FlexItem, Popover, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useCallback, useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

export function TaxonomyLabel( { taxonomy, className, ...props } ) {
	const taxonomyObject = useSelect( ( select ) => ( taxonomy ? select( 'core' ).getTaxonomy( taxonomy ) : null ), [ taxonomy ] );

	return taxonomyObject ? (
		<span
			className={ classNames( 'term-control__selected-item-label', className ) }
			{ ...props }
		>
			{ taxonomyObject.labels.singular_name }
		</span>
	) : null;
}

export function TermResult( { id, taxonomy, onRemove } ) {
	const term = useSelect( ( select ) => select( 'core' ).getEntityRecord( 'taxonomy', taxonomy, id ), [ id, taxonomy ] );

	return (
		<Flex className="term-control__selected-item">
			<FlexBlock>
				<TaxonomyLabel taxonomy={ taxonomy } />
				<span className="term-control__selected-item-title">{ term ? decodeEntities( term.name ) : <Spinner /> }</span>
			</FlexBlock>
			<FlexItem>
				<Button
					isDestructive
					variant="tertiary"
					type="button"
					onClick={ () => onRemove( { id, taxonomy } ) }
				>
					<Dashicon icon="remove" />
				</Button>
			</FlexItem>
		</Flex>
	);
}

export function TermSearchResults( { terms = [], onSelect, noResults = 'There are no matching terms. Please adjust your search.' } ) {
	return (
		<div className="term-search-control__results">
			{ terms && terms.length ? (
				terms.map( ( term ) => (
					<Button
						className="term-search-control__result"
						onClick={ () => onSelect( term ) }
						key={ term.id }
					>
						<Dashicon icon="insert" />

						<span className="term-search-control__title">{ decodeEntities( term.name ) }</span>
					</Button>
				) )
			) : (
				<div className="term-search-control__result term-search-control__result--empty">{ noResults }</div>
			) }
		</div>
	);
}

export function TermSearch( {
	defaultTaxonomy = 'category',
	exclude = [],
	help,
	label = 'Search Terms',
	noResults = 'There are no available terms. Change the taxonomy or add terms.',
	noSearchResults,
	onSelect,
	placeholder = 'Search...',
	resultsLabel = '',
	searchResultsLabel = 'Search Results',
	taxonomy,
	order = 'desc',
	orderby = 'count',
	perPage = 100,
} ) {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ filteredTerms, setFilteredTerms ] = useState( [] );
	const [ popoverRef, setPopoverRef ] = useState();
	const [ anchorRef, setAnchorRef ] = useState();
	const [ isOpen, setIsOpen ] = useState( false );
	const [ currentTaxonomy, setCurrentTaxonomy ] = useState(
		Array.isArray( taxonomy ) && taxonomy.length ? taxonomy[ 0 ] : typeof taxonomy === 'string' ? taxonomy : defaultTaxonomy
	);

	const { terms, taxonomyData } = useSelect( ( select ) => {
		let terms, taxonomyData;

		if ( taxonomy ) {
			taxonomyData = select( 'core' ).getTaxonomy( currentTaxonomy );
		}

		if ( taxonomyData ) {
			terms = select( 'core' ).getEntityRecords( 'taxonomy', taxonomyData.slug, {
				per_page: perPage,
				exclude,
				order,
				orderby,
			} );
		}

		return { terms, taxonomyData };
	} );

	const taxonomies = useSelect( ( select ) => {
		const core = select( 'core' );
		let taxonomies = null;

		if ( Array.isArray( taxonomy ) ) {
			if ( taxonomy.length ) {
				taxonomies = taxonomy.map( ( tax ) => core.getTaxonomy( tax ) );
			} else {
				taxonomies = core.getTaxonomies( { per_page: -1 } );
			}
		} else if ( typeof taxonomy === 'string' ) {
			taxonomies = [ core.getTaxonomy( taxonomy ) ];
		}

		return taxonomies;
	}, [] );

	const fetchTerms = useCallback( () => {
		if ( taxonomyData ) {
			setIsLoading( true );

			const params = {
				per_page: 25,
				orderby: 'name',
				order: 'asc',
				search: search,
			};

			if ( exclude && exclude.length ) {
				params.exclude = exclude;
			}

			apiFetch( {
				path: `/wp/v2/${ taxonomyData.rest_base }?${ new URLSearchParams( params ).toString() }`,
			} )
				.then( ( results ) => {
					setFilteredTerms( results );
				} )
				.catch( ( error ) => {
					console.error( error );
				} )
				.finally( () => {
					setIsLoading( false );
				} );
		}
	}, [ search ] );

	useEffect( () => {
		fetchTerms();
		return fetchTerms.cancel;
	}, [ search, fetchTerms ] );

	useEffect( () => {
		if ( anchorRef && popoverRef ) {
			const content = popoverRef.querySelector( '.components-popover__content' );

			if ( content ) {
				content.style.minWidth = `${ anchorRef.offsetWidth }px`;
			}
		}
	}, [ popoverRef, anchorRef ] );

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

	return (
		<BaseControl
			help={ help }
			className="term-search-control"
		>
			{ taxonomies && taxonomies.length > 1 && (
				<SelectControl
					label="Taxonomy"
					help="Select a taxonomy to select a term from"
					value={ currentTaxonomy }
					options={ taxonomies.map( ( taxonomy ) => {
						return {
							label: taxonomy.name,
							value: taxonomy.slug,
						};
					} ) }
					onChange={ ( taxonomy ) => setCurrentTaxonomy( taxonomy ) }
				/>
			) }

			<div
				className="term-control__search-ref"
				ref={ setAnchorRef }
			>
				<TextControl
					label={ label }
					onChange={ ( search ) => setSearch( search ) }
					value={ search }
					placeholder={ placeholder }
					onFocus={ () => setIsOpen( true ) }
					onBlur={ () => setTimeout( () => setIsOpen( isFocused() ), 50 ) }
				/>

				{ isOpen && (
					<Popover
						headerTitle="Results"
						focusOnMount={ false }
						className="term-control__popover"
						ref={ setPopoverRef }
						onFocusOutside={ () => setIsOpen( false ) }
					>
						{ isLoading ? (
							<Spinner />
						) : terms && terms.length ? (
							<TermSearchResults
								label={ search ? searchResultsLabel : resultsLabel }
								terms={ search ? filteredTerms : terms }
								onSelect={ ( ...args ) => {
									setIsOpen( false );
									onSelect( ...args );
								} }
								noResults={ noSearchResults }
							/>
						) : (
							<div className="term-search-control__results term-search-control__results--empty">{ noResults }</div>
						) }
					</Popover>
				) }
			</div>
		</BaseControl>
	);
}

export function TermControl( {
	help,
	label,
	onChange,
	placeholder = 'Search...',
	value = null,
	taxonomy = [],
	noTermsMessage = 'There are no terms selected',
	resultsLabel = 'Terms',
	defaultTaxonomy = 'category',
} ) {
	return (
		<BaseControl
			help={ help }
			label={ label }
			className="term-control"
		>
			<div
				className={ classNames( {
					'term-control__selected-items': true,
					'term-control__selected-items--empty': ! value || ! value.length,
				} ) }
			>
				{ value && value.id && value.taxonomy ? (
					<TermResult
						{ ...value }
						key={ value.id }
						onRemove={ () => onChange( null ) }
					/>
				) : (
					<em>{ noTermsMessage }</em>
				) }
			</div>

			{ ! value && (
				<TermSearch
					placeholder={ placeholder }
					taxonomy={ taxonomy }
					defaultTaxonomy={ defaultTaxonomy }
					onSelect={ ( { id, taxonomy } ) => onChange( { id, taxonomy } ) }
					resultsLabel={ resultsLabel }
					exclude={ value && value.id ? [ value.id ] : [] }
				/>
			) }
		</BaseControl>
	);
}

export function TermsControl( {
	help,
	label,
	onChange,
	placeholder = 'Search...',
	value = [],
	taxonomy = [],
	noTermsMessage = 'There are no terms selected',
	resultsLabel = 'Terms',
	defaultTaxonomy = 'category',
	max = 0,
} ) {
	return (
		<BaseControl
			help={ help }
			label={ label }
			className="term-control"
		>
			<div
				className={ classNames( {
					'term-control__selected-items': true,
					'term-control__selected-items--empty': ! value || ! value.length,
				} ) }
			>
				{ value && value.length ? (
					value.map( ( term ) => (
						<TermResult
							{ ...term }
							key={ term.id }
							onRemove={ () => onChange( value.filter( ( val ) => val !== term ) ) }
						/>
					) )
				) : (
					<em>{ noTermsMessage }</em>
				) }
			</div>

			{ ( ! value || ! value.length || max === 0 || ( max > 0 && value.length < max ) ) && (
				<TermSearch
					placeholder={ placeholder }
					taxonomy={ taxonomy }
					defaultTaxonomy={ defaultTaxonomy }
					onSelect={ ( { id, taxonomy } ) => onChange( [ ...value, { id, taxonomy } ] ) }
					resultsLabel={ resultsLabel }
					exclude={ value ? value.map( ( v ) => v.id ) : [] }
				/>
			) }
		</BaseControl>
	);
}
