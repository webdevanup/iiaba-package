import classnames from 'classnames';
import { BaseControl, Button, Dashicon, Spinner } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck, MediaPlaceholder } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * Wraps MediaUpload and MediaPlaceholder in a single control with sensible defaults
 */
export function MediaControl( {
	accept = 'image/*', // pass empty string to accept all types ( false can still be used )
	addToGallery = false,
	allowedTypes = [ 'image' ], // pass null to allow all types
	help,
	label,
	labels = { title: 'Select Media', instructions: '' },
	onSelect,
	removeMediaLabel = 'Remove',
	value,
	multiple = false,
	preview = true,
} ) {
	return (
		<BaseControl
			label={ label }
			help={ help }
		>
			<div className="media-control">
				{ value ? (
					<MediaUploadCheck fallback={ () => <MediaPreview id={ value } /> }>
						<MediaUpload
							allowedTypes={ allowedTypes }
							value={ value }
							onSelect={ onSelect }
							render={ ( { open } ) => (
								<>
									{ preview ? (
										<>
											<Button
												variant="link"
												onClick={ open }
											>
												<MediaPreview
													id={ value }
													style={ { display: 'block' } }
												/>
											</Button>
											<br />
										</>
									) : null }
									<Button
										isDestructive
										onClick={ () => onSelect( 0, null ) }
									>
										<Dashicon
											icon="no"
											style={ { textDecoration: 'none' } }
										/>
										{ removeMediaLabel }
									</Button>
								</>
							) }
						/>
					</MediaUploadCheck>
				) : (
					<MediaUploadCheck fallback="You do not have the upload media capability.">
						<MediaPlaceholder
							accept={ accept === false ? '' : accept }
							addToGallery={ addToGallery }
							allowedTypes={ allowedTypes }
							labels={ labels }
							onSelect={ onSelect }
							multiple={ multiple }
						/>
					</MediaUploadCheck>
				) }
			</div>
		</BaseControl>
	);
}

/**
 * Get normalized image attributes from a media object
 *
 * @param {object} media - either a backbone or rest api media model
 * @param {string} size - the image size if applicable
 * @returns object
 */
export function getImageAttributes( media, size = 'large' ) {
	if ( ! media ) {
		return null;
	}

	const data = {
		id: 0,
		src: '',
		height: '',
		width: '',
		alt: '',
	};

	if ( media ) {
		const sizeData = media?.sizes?.[ size ];

		data.id = media.id;
		data.src = sizeData?.url || sizeData?.source_url || media.url;
		data.height = media?.sizes?.[ size ]?.height || media?.media_details?.sizes?.[ size ]?.height || media.height;
		data.width = media?.sizes?.[ size ]?.width || media?.media_details?.sizes?.[ size ]?.width || media.width;
		data.alt = media.alt || '';
	}

	return data;
}

const basename = ( str, sep = '/' ) => {
	return str.substr( str.lastIndexOf( sep ) + 1 );
};

/**
 * Preview a media item by it's id
 */
export function MediaPreview( {
	// prettier-ignore
	id,
	size = 'medium',
	className,
	tagName = 'div',
	...props
} ) {
	const media = useSelect( ( select ) => select( 'core' ).getMedia( id ) );
	const Tag = tagName;

	if ( ! id ) {
		return null;
	}

	if ( ! media ) {
		return <Spinner />;
	}

	if ( media.mime_type.match( /^image\// ) ) {
		let model = {
			alt_text: media.alt_text,
		};

		if ( media.media_details.sizes && media.media_details.sizes[ size ] ) {
			model.source_url = media.media_details.sizes[ size ].source_url;
			model.height = media.media_details.sizes[ size ].height;
			model.width = media.media_details.sizes[ size ].width;
		} else {
			model.source_url = media.source_url;
			model.height = media.media_details.height;
			model.width = media.media_details.width;
		}

		if ( ! model.height && ! model.width && [ 'image/svg+xml' ].includes( media.mime_type ) ) {
			// model.style = Object.assign( { minHeight: '125px' }, style );
		}

		return (
			<Tag className={ classnames( 'wdg-media-preview', 'wdg-media-preview--image', className ) }>
				<img
					src={ model.source_url }
					alt={ model.alt_text }
					height={ model.height }
					width={ model.width }
					{ ...props }
				/>
			</Tag>
		);
	}

	if ( media.mime_type.match( /^video\// ) ) {
		return (
			<Tag
				className={ classnames( 'wdg-media-preview', 'wdg-media-preview--video', className ) }
				style={ { marginBottom: 10 } }
			>
				<video
					src={ media.source_url }
					controls
					muted
					style={ { width: '100%', height: 'auto' } }
					{ ...props }
				/>
				<div style={ { marginTop: 5 } }>{ basename( media.source_url ) }</div>
			</Tag>
		);
	}

	let iconMap = {
		audio: 'media-audio',
		video: 'media-video',
		attachment: 'media-document',
	};

	let icon = iconMap[ media.media_type ] ? iconMap[ media.media_type ] : 'media-default';

	return (
		<Tag
			className={ classnames( 'wdg-media-preview', 'wdg-media-preview--file', className ) }
			{ ...props }
		>
			<Dashicon
				icon={ icon }
				size="32"
			/>
			{ basename( media.source_url ) }
		</Tag>
	);
}
