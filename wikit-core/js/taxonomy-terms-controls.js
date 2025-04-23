import { CheckboxControl, Flex, FlexBlock, FlexItem, Notice, RadioControl, Spinner, TextControl, Button } from '@wordpress/components';
import { useEntityProp, useEntityRecords } from '@wordpress/core-data';
import { useSelect, select } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { decodeEntities } from '@wordpress/html-entities';
import { ReactSortable as Sortable } from 'react-sortablejs';
import { Icon, dragHandle } from '@wordpress/icons';

export function BaseTermListSearch(
	{
		slug,
		parent,
		render,
		className = '',
		perPage = -1,
		debounce = 500,
	}
) {
	const taxonomy              = select( 'core' ).getTaxonomy( slug );
	const postType              = select( 'core/editor' ).getCurrentPostType();
	const [ value, setValue ]   = useEntityProp( 'postType', postType, taxonomy.rest_base );
	const [ input, setInput ]   = useState( '' );
	const [ search, setSearch ] = useState( '' );

	const records = useSelect( select => {
		if ( search ) {
			return select( 'core' ).getEntityRecords(
				'taxonomy',
				taxonomy.rest_base,
				{
					per_page: perPage,
					parent: typeof parent === 'number' ? parent : 0,
					exclude: value,
					search,
				}
			);
		}
	}, [ search, value, perPage, parent ] );

	const isResolving = records === null;

	const valueRecords = useSelect( select => {
		return value.map( id => select( 'core' ).getEntityRecord( 'taxonomy', taxonomy.rest_base, id ) );
	}, [ value, taxonomy ] );

	useEffect( () => {
		const to = window.setTimeout( () => setSearch( input.toLowerCase() ), debounce );

		return () => window.clearTimeout( to );
	}, [ input ] );

	if ( ! taxonomy ) {
		return null;
	}

	return (
		<div className={ `wdg-terms-list ${ className }`.trim() }>
			<TextControl
				label={ `Search ${ taxonomy.labels.name }` }
				placeholder={ `${ taxonomy.labels.singular_name }...` }
				value={ input }
				onChange={ setInput }
				className="wdg-terms-list__filter"
				autoComplete="off"
			/>
			<div className="wdg-terms-list__terms">
				{ render( {
					taxonomy,
					value,
					setValue,
					records,
					valueRecords,
				} ) }
			</div>
			{ isResolving ? (
				<Spinner />
			) : ( search && ! records?.length ) && (
				<>
					<br />
					<Notice
						status="info"
						className="wdg-terms-list__empty"
						isDismissible={ false }
					>
						{ search ? 'No additional matches found' : `No matching ${ taxonomy.labels.name } found.` }
					</Notice>
				</>
			) }
		</div>
	);
}

export function BaseTermList( { slug, parent, render, className = '' } ) {
	const taxonomyArgs = {
		per_page: -1,
		parent: typeof parent === 'number' ? parent : 0,
	};

	const taxonomy = select( 'core' ).getTaxonomy( slug );
	const terms = useEntityRecords( 'taxonomy', taxonomy.rest_base, taxonomyArgs );
	const [ value, setValue ] = useEntityProp( 'postType', select( 'core/editor' ).getCurrentPostType(), taxonomy.rest_base );
	const [ search, setSearch ] = useState( '' );
	const [ activeSearch, setActiveSearch ] = useState( '' );
	const [ records, setRecords ] = useState( [] );

	useEffect( () => {
		if ( terms.records ) {
			let filteredRecords = [ ...terms.records ];

			if ( activeSearch ) {
				filteredRecords = filteredRecords.filter( ( record ) => value.includes( record.id ) || record.name.toLowerCase().includes( activeSearch ) );

				filteredRecords.sort( ( a, b ) => {
					if ( value.includes( b.id ) ) {
						return 1;
					}

					return a.name.localeCompare( b.name );
				} );
			}

			setRecords( filteredRecords );
		}
	}, [ activeSearch, terms.records, value ] );

	useEffect( () => {
		const to = window.setTimeout( () => setActiveSearch( search.toLowerCase() ), 500 );

		return () => window.clearTimeout( to );
	}, [ search ] );

	if ( ! taxonomy ) {
		return null;
	}

	if ( terms.isResolving ) {
		return <Spinner />;
	}

	return terms.records && terms.records.length ? (
		<div className={ `wdg-terms-list ${ className }`.trim() }>
			<TextControl
				label={ `Filter ${ taxonomy.labels.name }` }
				placeholder={ `${ taxonomy.labels.singular_name }...` }
				value={ search }
				onChange={ setSearch }
				className="wdg-terms-list__filter"
				autoComplete="off"
			/>
			<div className="wdg-terms-list__terms">{ render( { records, value, setValue, taxonomy } ) }</div>
		</div>
	) : (
		<p className="wdg-terms-list__empty">There are no { taxonomy.labels.name } to select.</p>
	);
}

/**
 * A replacement term selection control that allows sorting, but not term creation
 */
export function CheckboxTermList( props ) {
	return (
		<BaseTermList
			{ ...props }
			className="wdg-terms-list--checkbox"
			render={ ( { records, value, setValue } ) => {
				const checked      = value.map( ( val ) => records.find( ( term ) => term.id === val ) ).filter( val => val );
				const unchecked    = records.filter( ( term ) => ! value.includes( term.id ) );
				const hasChecked   = checked && checked.length > 0;
				const hasUnchecked = unchecked && unchecked.length > 0;

				return (
					<>
						{ hasChecked && (
							<Sortable
								list={ checked }
								setList={ ( sorted ) => setValue( sorted.map( ( { id } ) => id ) ) }
								value={ value }
								className="wdg-terms-list__checked"
							>
								{ checked.map( ( term, index ) => (
									<div
										className="wdg-terms-list__item"
										key={ term?.id || `resolving-${ index }` }
									>
										<Icon
											icon={ dragHandle }
											className="wdg-terms-list__handle"
										/>
										{ term ? (
											<CheckboxControl
												value={ term.id }
												label={ decodeEntities( term.name ) }
												checked
												className="wdg-terms-list__term wdg-terms-list__term--checkbox wdg-terms-list__term--checked"
												onChange={ () => setValue( value.filter( ( t ) => t !== term.id ) ) }
											/>
										) : (
											<Spinner />
										) }
									</div>
								) ) }
							</Sortable>
						) }

						{ hasChecked && hasUnchecked && <hr /> }

						{ hasUnchecked > 0 && (
							<div className="wdg-terms-list__unchecked">
								{ unchecked.map( ( term ) => (
									<div className="wdg-terms-list__item" key={ term.id }>
										<CheckboxControl
											value={ term.id }
											label={ decodeEntities( term.name ) }
											className="wdg-terms-list__term wdg-terms-list__term--checkbox wdg-terms-list__term--unchecked"
											onChange={ () => setValue( [ ...value, term.id ] ) }
										/>
									</div>
								) ) }
							</div>
						) }
					</>
				);
			} }
		/>
	);
}

export function CheckboxTermSearch( props ) {
	return (
		<BaseTermListSearch
			{ ...props }
			className={ 'wdg-terms-list--checkbox' }
			render={ ( {
				records,
				value,
				setValue,
				valueRecords
			} ) => {
				const hasChecked   = ( valueRecords?.length ?? 0 ) > 0;
				const hasUnchecked = ( records?.length ?? 0 ) > 0;
				const hasSeparator = hasChecked && hasUnchecked;

				return (
					<>
						{ valueRecords && (
							<Sortable
								list={ valueRecords }
								setList={ ( sorted ) => setValue( sorted.map( ( { id } ) => id ) ) }
								value={ value }
								className="wdg-terms-list__checked"
							>
								{ valueRecords.map( ( term, index ) => (
									<div
										className="wdg-terms-list__item"
										key={ term?.id || `resolving-${ index }` }
									>
										<Icon
											icon={ dragHandle }
											className="wdg-terms-list__handle"
										/>
										{ term?.id ? (
											<CheckboxControl
												value={ term.id }
												label={ decodeEntities( term.name ) }
												checked
												className="wdg-terms-list__term wdg-terms-list__term--checkbox wdg-terms-list__term--checked"
												onChange={ () => setValue( value.filter( ( t ) => t !== term.id ) ) }
											/>
										) : (
											<Spinner />
										) }
									</div>
								) ) }
							</Sortable>
						) }

						{ hasSeparator && <hr /> }

						{ ( records?.length > 0 ) && (
							<div className="wdg-terms-list__unchecked">
								{ records.map( ( record ) => (
									<div className="wdg-terms-list__item" key={ record.id }>
										<CheckboxControl
											value={ record.id }
											label={ decodeEntities( record.name ) }
											className="wdg-terms-list__term wdg-terms-list__term--checkbox wdg-terms-list__term--unchecked"
											onChange={ () => setValue( [ ...value, record.id ] ) }
										/>
									</div>
								) ) }
							</div>
						) }
					</>
				);
			} }
		/>
	)
}

export function RadioTermList( props ) {
	return (
		<BaseTermList
			{ ...props }
			className="wdg-terms-list--radio"
			render={ ( { records, value, setValue, taxonomy } ) => (
				<RadioControl
					label={ taxonomy.label }
					selected={ value ? value[ 0 ] : null }
					options={ records.map( ( term ) => ( {
						value: term.id,
						label: decodeEntities( term.name ),
					} ) ) }
					onChange={ ( termId ) => setValue( [ Number( termId ) ] ) }
					className="wdg-terms-list__term wdg-terms-list__term--radio"
				/>
			) }
		/>
	);
}

export function RadioTermSearch( props ) {
	return (
		<BaseTermListSearch
			{ ...props }
			className={ 'wdg-terms-list--radio' }
			render={ ( {
				records,
				value,
				setValue,
				valueRecords
			} ) => {
				const hasChecked   = ( valueRecords?.length ?? 0 ) > 0;
				const hasUnchecked = ( records?.length ?? 0 ) > 0;
				const hasSeparator = hasChecked && hasUnchecked;

				return (
					<>
						{ valueRecords.map( ( term ) => (
							<Flex>
								<FlexBlock>
									{ decodeEntities( term.name ) }
								</FlexBlock>
								<FlexItem>
									<Button
										icon="dismiss"
										onClick={ () => setValue( [] ) }
									/>
								</FlexItem>
							</Flex>
						) ) }

						{ hasSeparator && <hr /> }

						{ ( records?.length > 0 ) && (
							<RadioControl
								selected={ value ? value[ 0 ] : null }
								options={ records.map( ( term ) => ( {
									value: term.id,
									label: decodeEntities( term.name ),
								} ) ) }
								onChange={ ( termId ) => setValue( [ Number( termId ) ] ) }
								className="wdg-terms-list__term wdg-terms-list__term--radio"
							/>
						) }
					</>
				);
			} }
		/>
	)
}

function blockEditorControl(Component) {
	return function (props) {
		const taxonomy = useSelect( ( select ) => select( "core" ).getTaxonomy( props.slug ) );

		if ( taxonomy && taxonomy.block_editor_control ) {
			if ( "checkbox_search" === taxonomy.block_editor_control ) {
				return <CheckboxTermSearch { ...props } />
			}

			if ( "checkbox" === taxonomy.block_editor_control ) {
				return <CheckboxTermList { ...props } />
			}

			if ( "radio_search" === taxonomy.block_editor_control ) {
				return <RadioTermSearch { ...props } />
			}

			if ( "radio" === taxonomy.block_editor_control ) {
				return <RadioTermList { ...props } />
			}
		}

		return <Component { ...props } />
	};
}

addFilter(
	"editor.PostTaxonomyType",
	"wdg/core/blockEditorControl",
	blockEditorControl
);
