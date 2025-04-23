import gatedBlock from './blocks/gated-block.json';
import gatedIfBlock from './blocks/gated-if-block.json';
import gatedElseBlock from './blocks/gated-else-block.json';
import { status_key, restrictions_key, statuses, restrictions } from '@wdg/config/authentication';
import { InspectorControls, InnerBlocks, useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { PanelBody, Dashicon, RadioControl, ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect, select, dispatch } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { addFilter } from '@wordpress/hooks';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Control that outputs the configured authentication status and restrictions
 */
function AuthenticationControl( {
	status = '',
	filterStatus = null,
	onStatusChange,
	restriction = [],
	onRestrictionChange,
} ) {
	let options = Object.keys( statuses ).map( ( value ) => ( { value, label: statuses[ value ] } ) );

	if ( typeof filterStatus === 'function' ) {
		options = options.filter( filterStatus );
	}

	return (
		<div className="wdg-authentication-control">
			<RadioControl
				label="Authentication Status"
				className="wdg-authentication-control__status"
				selected={ status }
				options={ options }
				onChange={ onStatusChange }
			/>

			<br />
			<small className="wdg-authentication-control__label">Authentication Restrictions</small>

			{ Object.keys( restrictions ).map( key => (
				<ToggleControl
					label={ restrictions[ key ].label }
					className="wdg-authentication-control__restriction"
					key={ key }
					disabled={ status !== 'authenticated' }
					checked={ restriction.includes( key ) }
					onChange={ checked => {
						if ( checked && ! restriction.includes( key ) ) {
							onRestrictionChange( [ ...restriction, key ] );
						} else if ( ! checked && restriction.includes( key ) ) {
							onRestrictionChange( restriction.filter( val => val !== key ) );
						}
					} }
				/>
			) ) }
		</div>
	);
}

/**
 * Save function for all authentication blocks
 */
function save() {
	return <InnerBlocks.Content />;
}

/**
 * Get the parent block and a function for setAttributes
 */
function useSetParentAttributes( clientId ) {
	const parents = select('core/block-editor').getBlockParents( clientId );

	if ( parents.length ) {
		const parent = select('core/block-editor').getBlock( parents[ parents.length - 1 ] );

		return function( attributes ) {
			dispatch( 'core/block-editor' ).updateBlock(
				parent.clientId,
				{
					attributes: {
						...parent.attributes,
						...attributes,
					}
				}
			);
		}
	}

	return null;
}

/**
 * wdg/gated
 */
registerBlockType(
	gatedBlock.name,
	{
		...gatedBlock,
		save,
		edit: ( { attributes, setAttributes } ) => (
			<>
				<InspectorControls>
					<PanelBody>
						<AuthenticationControl
							status={ attributes.status }
							filterStatus={ status => status.value }
							onStatusChange={ status => setAttributes( { status } ) }
							restriction={ attributes.restrictions }
							onRestrictionChange={ restrictions => setAttributes( { restrictions } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...useInnerBlocksProps(
					useBlockProps( { className: 'wdg-core-gated-block' } ),
					{
						templateLock: 'all',
						template: [
							[ 'wdg/gated-if' ],
							[ 'wdg/gated-else' ],
						]
					}
				) } />
			</>
		),
	}
);

/**
 * wdg/gated-if
 */
registerBlockType(
	gatedIfBlock.name,
	{
		...gatedIfBlock,
		save,
		edit: ( { context, clientId } ) => {
			const status              = context['wdg/gated/status'];
			const restrictions        = context['wdg/gated/restrictions'];
			const setParentAttributes = useSetParentAttributes( clientId );

			return (
				<>
					<InspectorControls>
						<PanelBody>
							<AuthenticationControl
								status={ status }
								filterStatus={ status => status.value }
								onStatusChange={ status => setParentAttributes( { status } ) }
								restriction={ restrictions }
								onRestrictionChange={ restrictions => setParentAttributes( { restrictions } ) }
							/>
						</PanelBody>
					</InspectorControls>
					<div {...useBlockProps( { className: "wdg-core-gated-block__logic wdg-core-gated-block__logic--if" } ) }>
						<div className="wdg-core-gated-block__header">
							<Dashicon icon="unlock" />
							<span className="wdg-core-gated-block__label">If</span>
							<span className="wdg-core-gated-block__pill wdg-core-gated-block__pill--status">
								{ status ? status : 'Public' }
							</span>
							{ 'authenticated' === status && restrictions && restrictions.map( ( restriction ) => (
								<span
									key={ restriction }
									className="wdg-core-gated-block__pill wdg-core-gated-block__pill--restriction"
								>
									{ restriction }
								</span>
							) ) }
						</div>

						<div { ...useInnerBlocksProps(
							{
								className: 'wdg-core-gated-block__content',
							},
							{
								templateLock: false,
								template: [ [ 'core/paragraph' ] ]
							}
						) } />
					</div>
				</>
			);
		},
	}
);

/**
 * wdg/gated-else
 */
registerBlockType(
	gatedElseBlock.name,
	{
		...gatedElseBlock,
		save,
		edit: ( { context, clientId } ) => {
			const status              = context['wdg/gated/status'];
			const restrictions        = context['wdg/gated/restrictions'];
			const setParentAttributes = useSetParentAttributes( clientId );

			return (
				<>
					<InspectorControls>
						<PanelBody>
							<AuthenticationControl
								status={ status }
								filterStatus={ status => status.value }
								onStatusChange={ status => setParentAttributes( { status } ) }
								restriction={ restrictions }
								onRestrictionChange={ restrictions => setParentAttributes( { restrictions } ) }
							/>
						</PanelBody>
					</InspectorControls>
					<div {...useBlockProps( { className: "wdg-core-gated-block__logic wdg-core-gated-block__logic--else" } ) }>
						<div className="wdg-core-gated-block__header">
							<Dashicon icon="lock" />
							<span className="wdg-core-gated-block__label">Else</span>
						</div>
						<div { ...useInnerBlocksProps(
							{
								className: 'wdg-core-gated-block__content',
							},
							{
								templateLock: false,
								template: [ [ 'core/paragraph' ] ]
							}
						) } />
					</div>
				</>
			);
		}
	}
);

/**
 * Register a plugin to edit the post level authentication settings
 */
registerPlugin(
	'wdg-core-authentication',
	{
		render: function AuthenticationPlugin() {
			const currentPostType   = useSelect( select => select('core/editor').getCurrentPostType() );
			const [ meta, setMeta ] = useEntityProp( 'postType', currentPostType, 'meta' );

			if ( ! meta ) {
				return null;
			}

			return (
				<PluginDocumentSettingPanel
					name="wdg-authentication"
					className="wdg-authentication"
					title="Authentication"
					icon="lock"
					initialOpen={ meta[ status_key ] }
				>
					<AuthenticationControl
						status={ meta[ status_key ] }
						restriction={ meta[ restrictions_key ] }
						onStatusChange={ status => setMeta( { ...meta, [ status_key ]: status } ) }
						onRestrictionChange={ restrictions => setMeta( { ...meta, [ restrictions_key ]: restrictions } ) }
					/>
				</PluginDocumentSettingPanel>
			);
		}
	}
);

/**
 * Filter block attributes to support authentication attributes and context
 */
addFilter(
	'blocks.registerBlockType',
	'wdg/authentication/register',
	function registerBlockTypeAuthentication( settings, name ) {
		if ( typeof settings?.supports?.authentication === 'undefined' ) {
			settings.supports = {
				...settings.supports,
				authentication: true
			}
		}

		if ( settings?.supports?.authentication ) {
			settings.attributes = {
				...settings.attributes,
				[ status_key ]: {
					type: "string",
					default: '',
					enum: Object.keys( statuses ),
				},
				[ restrictions_key ]: {
					type: "array",
					items: {
						type: "string",
						enum: Object.keys( restrictions ),
					},
					default: [],
				},
			};
		}

		settings.usesContext = [
			...settings.usesContext ?? [],
			"wdg/gated/context",
			"wdg/gated/restrictions",
		];

		return settings;
	}
);

/**
 * Add additional block controls to all blocks that supports authentication and don't have a parent authentication context
 */
addFilter(
	'editor.BlockEdit',
	'wdg/authentication/edit',
	createHigherOrderComponent(
		function( BlockEdit ) {
			return function( block ) {
				const { attributes, setAttributes, isSelected, context } = block;
				const supportsAuth = useSelect( ( select ) => select( 'core/blocks' ).getBlockSupport( block.name, 'authentication' ) );

				return (
					<>
						<BlockEdit {...block} />

						{ supportsAuth && ! context['wdg/gated/context'] && isSelected && (
							<InspectorControls>
								<PanelBody title="Block Authentication" icon="lock" initialOpen={ attributes[ status_key ] }>
									<AuthenticationControl
										status={ attributes[ status_key ] }
										restriction={ attributes[ restrictions_key ] }
										onStatusChange={ status => setAttributes( { [ status_key ]: status } ) }
										onRestrictionChange={ restrictions => setAttributes( { [ restrictions_key ]: restrictions } ) }
									/>
								</PanelBody>
							</InspectorControls>
						) }
					</>
				);
			}
		},
		'wdg/authentication/edit'
	),
	1
);
