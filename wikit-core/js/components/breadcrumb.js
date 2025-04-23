import classNames from 'classnames';
import { useSelect } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

export function BreadcrumbPost( { id, type } ) {
	const post = useSelect( ( select ) => select( 'core' ).getEntityRecord( 'postType', type, id ) );

	if ( post ) {
		return (
			<>
				{ post.parent > 0 && (
					<BreadcrumbPost
						id={ post.parent }
						type={ type }
					/>
				) }
				<BreadcrumbItem title={ post.title.rendered } />
			</>
		);
	}

	if ( id ) {
		return <Spinner />;
	}

	return null;
}

export function BreadcrumbItem( { title } ) {
	return (
		<li className="breadcrumb__item">
			<a className="breadcrumb__link">{ decodeEntities( title ) }</a>
		</li>
	);
}

export function Breadcrumb( { className, wrap = 'nav', ...props } ) {
	const { type, parent, postType } = useSelect( ( select ) => {
		const core = select( 'core' );
		const editor = select( 'core/editor' );
		const type = editor.getCurrentPostType();

		return {
			type,
			parent: editor.getEditedPostAttribute( 'parent' ),
			postType: core.getPostType( type ),
		};
	} );

	const Items = ( props ) => {
		return (
			<ol
				className="breadcrumb__items"
				{ ...props }
			>
				{ postType?.breadcrumb?.length || parent ? (
					<>
						{ postType &&
							postType.breadcrumb &&
							postType.breadcrumb.map( ( item ) => (
								<BreadcrumbItem
									key={ item.link }
									{ ...item }
								/>
							) ) }
						{ parent && parent > 0 ? (
							<BreadcrumbPost
								id={ parent }
								type={ type }
							/>
						) : null }
					</>
				) : (
					<li className="breadcrumb__item">No breadcrumb available</li>
				) }
			</ol>
		);
	};

	if ( wrap ) {
		const Wrap = wrap;

		return (
			<Wrap className={ classNames( 'breadcrumb', className ) }>
				<Items { ...props } />
			</Wrap>
		);
	}

	return <Items { ...props } />;
}
