<?php
namespace WDG\Core;

/**
 * This class is a swiss army knife for media
 *
 * - designed to implement protected media, moving files to the private directory or back to the public directory
 * - Uses the attachment permalink to stream the file to the browser if the media is protected
 * - Changes media previews in the admin for embeddable mime types (pdf mostly) to an iframe preview
 * - Rebuilds video and audio shortcodes for protected media
 * - Redirect the public url of a protected media item to the attachment permalink
 *
 * If the protected features of this class are used with nginx, add the following location rule to the nginx server block:
 * -- Replace private if using a custom upload directory
 *
 * ```
 * location ^~ /wp-content/uploads/private/ {
 *     return 403;
 * }
 * ```
 */
class Media {

	/**
	 * The meta key that stores the protected status
	 *
	 * Note that this key can not start with an underscore (_) or wordpress will not render the checkbox control
	 *
	 * @var string
	 * @access protected
	 */
	protected $meta_key = 'protected';

	/**
	 * The base transient key that stores notices ( suffixed with current user id )
	 *
	 * @var string
	 * @access protected
	 */
	protected $transient_key = '_media_protected_transient_';

	/**
	 * The name of the private uploads directory
	 *
	 * @var string
	 * @access protected
	 */
	protected $private_dir = 'private';

	/**
	 * The rewrite endpoint to use for attachment thumbnails
	 *
	 * @var string
	 * @access protected
	 */
	protected $rewrite_endpoint = 'size';

	/**
	 * The label of the authentication checkbox
	 *
	 * @var string
	 * @access protected
	 */
	protected $field_label = 'Authentication';

	/**
	 * The help text of the authentication checkbox
	 *
	 * @var string
	 * @access protected
	 */
	protected $field_help = 'Require authentication to view';

	/**
	 * Hooking into WordPress on construct
	 *
	 * @access protected
	 */
	public function __construct() {
		$this->transient_key .= strval( get_current_user_id() );

		did_action( 'init' ) ? $this->init() : add_action( 'init', [ $this, 'init' ] );
		add_action( 'template_redirect', [ $this, 'template_redirect' ], 99 );
		add_filter( 'pre_handle_404', [ $this, 'pre_handle_404' ], 1, 2 );

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		add_action( 'added_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'deleted_post_meta' ], 10, 4 );
		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields_to_edit' ], 9999, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'attachment_fields_to_save' ], 1, 2 );

		add_filter( 'attachment_link', [ $this, 'attachment_link' ], 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'wp_prepare_attachment_for_js' ], 11, 3 );
		add_filter( 'wp_get_attachment_url', [ $this, 'wp_get_attachment_url' ], 11, 3 );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'wp_get_attachment_image_src' ], 99, 4 );
		add_filter( 'wp_video_shortcode_override', [ $this, 'wp_audio_video_shortcode_override' ], 11, 5 );
		add_filter( 'wp_audio_shortcode_override', [ $this, 'wp_audio_video_shortcode_override' ], 11, 5 );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'wp_get_attachment_image_src_preview' ], 99, 4 );
		add_filter( 'wp_edit_form_attachment_display', [ $this, 'wp_edit_form_attachment_display' ] );
	}

	/**
	 * Add a rewrite endpoint on attachments to enable protected thumbnails
	 *
	 * @return void
	 * @access public
	 * @action init
	 */
	public function init() {
		add_rewrite_endpoint( $this->rewrite_endpoint, EP_ATTACHMENT | EP_PAGES );

		register_post_meta(
			'attachment',
			$this->meta_key,
			[
				'type'         => 'boolean',
				'description'  => 'A boolean value for if the attachment should require authentication to view',
				'default'      => false,
				'single'       => true,
				'show_in_rest' => true,
			]
		);
	}

	/**
	 * Get the protected status of a media attachment
	 *
	 * @param int $post_id
	 * @return boolean
	 * @access public
	 */
	public function is_protected( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$post = get_post( $post_id );

		if ( ! is_a( $post, '\WP_Post' ) || 'attachment' !== $post->post_type ) {
			return false;
		}

		return ! empty( get_post_meta( $post_id, $this->meta_key, true ) );
	}

	/**
	 * Redirect attachment pages to the full URI, stream file if authorized, or 401
	 *
	 * @return void
	 * @access public
	 *
	 * @action template_redirect
	 */
	public function template_redirect() {
		if ( ! is_attachment() ) {
			return;
		}

		$attachment_id = get_queried_object_id();

		if ( ! $this->is_protected( $attachment_id ) ) {
			wp_safe_redirect( apply_filters( 'wdg/media/redirect', wp_get_attachment_url( $attachment_id ) ) );
			exit;
		}

		if ( ! $this->has_access() ) {
			/**
			 * Use this action to supply an alternative response for unauthorized media access
			 */
			do_action( 'wdg/media/unauthorized', $attachment_id );

			status_header( 401, 'Unauthorized' );
			echo '401 Unauthorized';
			exit;
		}

		$this->stream_file( $attachment_id, get_query_var( $this->rewrite_endpoint, null ), true );
	}

	/**
	 * Redirect previously unprotected files to their protected attachment page
	 *
	 * @param bool $handle
	 * @param \WP_Query $query
	 * @return bool
	 * @filter pre_handle_404
	 */
	public function pre_handle_404( $handle ) {
		global $wpdb, $wp;

		$uploads = wp_get_upload_dir();

		// bail if it's not an uploads request
		if ( ! preg_match( '/^' . preg_quote( $uploads['baseurl'], '/' ) . '/', home_url( $wp->request ) ) ) {
			return $handle;
		}

		// build what might be the protected path of the current url
		$protected_attached_file = $this->private_dir . str_replace( str_replace( constant( 'ABSPATH' ), '', $uploads['basedir'] ), '', $wp->request );

		// see if it exists in the database under the _wp_attached_file key
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
				$protected_attached_file
			)
		);

		// found it, redirect with a 302 found status in case it moves back and forth
		if ( ! empty( $post_id ) ) {
			wp_safe_redirect( get_the_permalink( $post_id ), 302 );
			exit;
		}

		return $handle;
	}

	/**
	 * Determine access to a protected file, default to the user being logged in to wordpress
	 *
	 * @param int $post_id
	 * @return boolean
	 * @access public
	 */
	public function has_access( $post_id = null ) {
		$post_id = $post_id ?? get_the_ID();

		return apply_filters( 'wdg/media/access', is_user_logged_in(), $post_id );
	}

	/**
	 * Stream a file to the browser
	 *
	 * @param int $attachment_id
	 * @param string|null $size
	 * @param bool $done - call exit after stream
	 * @return void
	 * @access protected
	 */
	protected function stream_file( $attachment_id, $size = null, bool $done = true ) {
		$attachment_path = null;

		if ( wp_attachment_is_image( $attachment_id ) && ! empty( $size ) ) {
			$size_data       = image_get_intermediate_size( $attachment_id, $size );
			$upload_dir      = wp_upload_dir();
			$attachment_path = $upload_dir['basedir'] . '/' . $size_data['path'];
		}

		if ( ! is_file( $attachment_path ) ) {
			$attachment_path = get_attached_file( $attachment_id );
		}

		if ( ! is_file( $attachment_path ) ) {
			status_header( 500, 'Internal Server Error' );

			if ( $done ) {
				exit;
			}
		}

		header( 'Cache-Control: max-age=0' );
		header( 'Content-Type: ' . get_post_mime_type( $attachment_id ) );
		header( 'Content-Length: ' . filesize( $attachment_path ) );

		/**
		 * Add/Modify headers with the wdg/media/stream action
		 */
		do_action( 'wdg/media/stream', $attachment_id );

		readfile( $attachment_path );

		if ( $done ) {
			exit;
		}
	}

	/**
	 * Filter the attachment link to the full URI
	 *
	 * @param string $link
	 * @param int $post_id
	 * @return string
	 *
	 * @filter attachment_link
	 */
	public function attachment_link( $link, $post_id ) {
		return $this->is_protected( $post_id ) ? $link : wp_get_attachment_url( $post_id );
	}

	/**
	 * Modify the json model of a protected attachment
	 *
	 * @param array $response
	 * @param WP_Post $attachment
	 * @param array $meta
	 * @return array
	 *
	 * @filter wp_prepare_attachment_for_js
	 */
	public function wp_prepare_attachment_for_js( $response, $attachment ) {
		if ( $this->is_protected( $attachment->ID ) ) {
			$response['url'] = $response['link'];

			if ( ! empty( $response['sizes'] ) ) {
				foreach ( $response['sizes'] as $size => &$data ) {
					$src = wp_get_attachment_image_src( $attachment->ID, $size );

					if ( ! empty( $src ) ) {
						$data['url'] = current( $src );
					}
				}
			}
		}

		return $response;
	}

	/**
	 * Add fields to the Attachment model
	 *
	 * @param array $fields
	 * @param \WP_Post $post
	 * @return array
	 * @access public
	 *
	 * @filter attachment_fields_to_edit
	 */
	public function attachment_fields_to_edit( $fields, $post ) {
		$fields[ $this->meta_key ] = [
			'label' => $this->field_label,
			'helps' => $this->field_help,
			'input' => 'html',
			'html'  => sprintf(
				'<input type="checkbox" name="attachments[%1$s][%2$s]" id="attachments-%1$s-%2$s"%3$s>',
				$post->ID,
				esc_attr( $this->meta_key ),
				checked( true, ! empty( get_post_meta( $post->ID, $this->meta_key, true ) ), false )
			),
		];

		return $fields;
	}

	/**
	 * Save our meta value
	 *
	 * @param array $post
	 * @param array $attachment
	 * @return array
	 *
	 * @filter attachment_fields_to_save
	 */
	public function attachment_fields_to_save( $post, $attachment ) {
		if ( 'cli' === php_sapi_name() || current_user_can( 'edit_post', $post['ID'] ) ) {
			if ( isset( $attachment[ $this->meta_key ] ) && 'on' === strtolower( $attachment[ $this->meta_key ] ) ) {
				update_post_meta( $post['ID'], $this->meta_key, '1' );
			} else {
				delete_post_meta( $post['ID'], $this->meta_key );
			}
		}

		return $post;
	}

	/**
	 * Modify the attachment url to the permalink if protected
	 *
	 * @param string $url
	 * @param int $post_id
	 * @return string
	 *
	 * @filter wp_get_attachment_url
	 */
	public function wp_get_attachment_url( $url, $post_id ) {
		return $this->is_protected( $post_id ) ? get_the_permalink( $post_id ) : $url;
	}

	/**
	 * Modify the thumbnail url of protected images to the attachment rewrite endpoint
	 *
	 * @param array $image
	 * @param int $attachment_id
	 * @param string|array $size
	 * @param bool $icon
	 * @return array
	 *
	 * @filter wp_get_attachment_image_src
	 */
	public function wp_get_attachment_image_src( $image, $attachment_id, $size ) {
		if ( ! $this->is_protected( $attachment_id ) ) {
			return $image;
		}

		$permalink = trailingslashit( get_the_permalink( $attachment_id ) );

		if ( ! empty( $size ) && ! is_array( $size ) ) {
			$permalink .= $this->rewrite_endpoint . '/' . $size . '/';
		}

		$image[0] = $permalink;

		return $image;
	}

	/**
	 * Change the upload dir if the file is protected
	 *
	 * @param array $pathdata
	 * @return array
	 * @access protected
	 */
	protected function upload_dir( $pathdata ) {
		if ( is_array( $pathdata ) && ! empty( $pathdata['subdir'] ) ) {
			$subdir = '/' . $this->private_dir . $pathdata['subdir'];

			$pathdata['path']   = str_replace( $pathdata['subdir'], $subdir, $pathdata['path'] );
			$pathdata['url']    = str_replace( $pathdata['subdir'], $subdir, $pathdata['url'] );
			$pathdata['subdir'] = str_replace( $pathdata['subdir'], $subdir, $pathdata['subdir'] );
		}

		return $pathdata;
	}

	/**
	 * Handle moving the file to the protected directory
	 *
	 * @param int $meta_id
	 * @param int $attachment_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @return bool - whether the file was moved or not
	 * @access public
	 *
	 * @action updated_post_meta
	 * @action added_post_meta
	 */
	public function updated_post_meta( $meta_id, $attachment_id, $meta_key, $meta_value ) {
		if ( $meta_key !== $this->meta_key || get_post_type( $attachment_id ) !== 'attachment' ) {
			return false;
		}

		$moved = $this->move_attachment( $attachment_id, (bool) $meta_value );

		if ( is_wp_error( $moved ) ) {
			set_transient( $this->transient_key, implode( ', ', $moved->get_error_messages() ), 5 * MINUTE_IN_SECONDS );
			return false;
		}

		return $moved;
	}

	/**
	 * Handle moving the file to the public directory
	 *
	 * @param int $meta_ids
	 * @param int $attachment_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @return bool - whether the file was moved or not
	 * @access public
	 *
	 * @action deleted_post_meta
	 */
	public function deleted_post_meta( $meta_ids, $attachment_id, $meta_key ) {
		if ( $meta_key !== $this->meta_key || get_post_type( $attachment_id ) !== 'attachment' ) {
			return false;
		}

		$moved = $this->move_attachment( $attachment_id, false );

		if ( is_wp_error( $moved ) ) {
			set_transient( $this->transient_key, implode( ', ', $moved->get_error_messages() ), 5 * MINUTE_IN_SECONDS );
			return false;
		}

		return $moved;
	}

	/**
	 * Move a file to or from the protected directory
	 *
	 * @param int $id
	 * @param boolean $protect - whether to move to private or public directory
	 * @return boolean|WP_Error - if the file was moved or not, or an error
	 * @access protected
	 */
	protected function move_attachment( $attachment_id, bool $protect = false ) {
		$attachment = get_post( $attachment_id );

		// verify we're looking at a media file
		if ( ! is_a( $attachment, '\WP_Post' ) || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error( '001', sprintf( 'id %d is not an attachment', $attachment_id ) );
		}

		// get the full path to the file
		$attachment_path = get_attached_file( $attachment_id );

		// can't do anything with an unreadable file
		if ( ! is_readable( $attachment_path ) ) {
			return new \WP_Error( '002', sprintf( 'attachment %s is not readable', $attachment_path ) );
		}

		// get the path relative to uploads
		$attachment_upload_path = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
			// parse the year/month out of the date while ignoring the private dir
			$attachment_year_month = preg_replace( '/^(?:' . preg_quote( $this->private_dir, '/' ) . '\/)?(\d+\/\d+)\/.+/', '$1', $attachment_upload_path );

			// get default upload dir for this file
			$upload_dir = wp_upload_dir( $attachment_year_month, true, true );
		} else {
			// get default upload dir with no year/month folders
			$upload_dir = wp_upload_dir();
		}

		// build private upload dir for this file
		$upload_dir_private = $this->upload_dir( $upload_dir );

		// grab the file name
		$attachment_file_name = basename( $attachment_path );

		if ( true === $protect ) {
			// file does not need moved to private
			if ( strpos( $attachment_upload_path, $this->private_dir ) === 0 ) {
				return false;
			}

			// moving to protected
			$source_dir       = $upload_dir['path'];
			$dest_dir         = $upload_dir_private['path'];
			$dest_path        = trailingslashit( $dest_dir ) . $attachment_file_name;
			$dest_upload_path = trailingslashit( ltrim( $upload_dir_private['subdir'], '/' ) ) . $attachment_file_name;
		} else {
			// file does not need moved to public
			if ( strpos( $attachment_upload_path, $this->private_dir ) !== 0 ) {
				return false;
			}

			// moving to public
			$source_dir       = $upload_dir_private['path'];
			$dest_dir         = $upload_dir['path'];
			$dest_path        = trailingslashit( $dest_dir ) . $attachment_file_name;
			$dest_upload_path = trailingslashit( ltrim( $upload_dir['subdir'], '/' ) ) . $attachment_file_name;
		}

		// verify our private directory exists
		$private_path = trailingslashit( $upload_dir_private['basedir'] ) . $this->private_dir;

		if ( ! file_exists( $private_path ) ) {
			wp_mkdir_p( $private_path );

			// verify again
			if ( ! file_exists( $private_path ) ) {
				return new \WP_Error( '003', sprintf( 'Count not create directory %s', $private_path ) );
			}
		}

		// put our .htaccess protection here
		$htaccess_path = $private_path . '/.htaccess';

		if ( ! file_exists( $htaccess_path ) ) {
			file_put_contents( $htaccess_path, 'Deny from all' );
		}

		// verify our directory exists
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );

			// verify again
			if ( ! file_exists( $dest_dir ) ) {
				return new \WP_Error( '003', sprintf( 'Count not create directory %s', $dest_dir ) );
			}
		}

		// move the file
		$attachment_moved = rename( $attachment_path, $dest_path );

		// ¯\_(ツ)_/¯
		if ( ! $attachment_moved ) {
			return new \WP_Error( '004', 'Error moving attachment file' );
		}

		// update the attached file path
		update_post_meta( $attachment_id, '_wp_attached_file', $dest_upload_path );

		if ( wp_attachment_is_image( $attachment_id ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );

			// no additional metadata / sizes on attachment - return true
			if ( empty( $metadata ) ) {
				return true;
			}

			// update attachment metadata with new path
			$metadata['file'] = $dest_upload_path;

			// moving image thumbnails
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => &$data ) {
					$size_source_path = $source_dir . '/' . $data['file'];
					$size_dest_path   = $dest_dir . '/' . $data['file'];

					// don't replace an existing file
					if ( ! file_exists( $size_source_path ) || file_exists( $size_dest_path ) ) {
						continue;
					}

					rename( $size_source_path, $size_dest_path );
				}
			}

			// update metadata & file location
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return true;
	}

	/**
	 * Display any errors stored in the transients API
	 *
	 * @return bool
	 * @action admin_notices
	 * @access public
	 */
	public function admin_notices() {
		$transient = get_transient( $this->transient_key );

		if ( ! empty( $transient ) ) {
			delete_transient( $this->transient_key );

			printf( '<div class="notice notice-error">%s</div>', wp_kses_post( wpautop( $transient ) ) );
			return true;
		}

		return false;
	}

	/**
	 * Rebuild the audio/video shortcode since it doesn't like our permalink format without a file extension
	 *
	 * @param string $html
	 * @param array $attr
	 * @param string $content
	 * @param int $instance
	 * @return string
	 * @access public
	 *
	 * @filter wp_audio_shortcode_override
	 * @filter wp_video_shortcode_override
	 */
	public function wp_audio_video_shortcode_override( $html, $attr, $content ) {
		if ( empty( $attr['src'] ) ) {
			return $html;
		}

		// determine which filter we're running under
		$current_filter = current_filter();

		// get our post id since we have no context beside the final url
		$post_id = url_to_postid( $attr['src'] );

		// not protected - default
		if ( empty( $post_id ) || ! $this->is_protected( $post_id ) ) {
			return $html;
		}

		// build the attr src to the actual file so wp_video_shortcode can property generate
		$upload_dir    = $this->upload_dir( wp_upload_dir() );
		$src           = $attr['src'];
		$attached_file = get_post_meta( $post_id, '_wp_attached_file', true );
		$attached_path = $upload_dir['basedir'] . '/' . $attached_file;

		// no file at the calculated path :(
		if ( ! is_file( $attached_path ) ) {
			return $html;
		}

		// replace the permalink src with the actual file
		$attr['src'] = $upload_dir['baseurl'] . '/' . $attached_file;

		// make sure we don't recurse
		remove_filter( $current_filter, [ $this, __FUNCTION__ ], 11, 5 );

		// call the shortcode again with our modified src and then replace the modified src with the original
		switch ( $current_filter ) {
			case 'wp_audio_shortcode_override':
				$html = wp_audio_shortcode( $attr, $content );
				break;
			case 'wp_video_shortcode_override':
				$html = wp_video_shortcode( $attr, $content );
				break;
		}

		$html = str_replace( $attr['src'], $src, $html );

		add_filter( $current_filter, [ $this, __FUNCTION__ ], 11, 5 );

		return $html;
	}

	/**
	 * Mime types that can be viewed through an iframe in the admin editor
	 * empty this array in a child class to disable
	 *
	 * @var array
	 * @access protected
	 */
	protected $embeddable_mime_types = [
		'application/pdf',
		'text/plain',
	];

	/**
	 * Remove the document icon image for embeddable mime types so our wp_edit_form_attachment_display will fire
	 * Re-enable the document icon for protected files that can't be embedded
	 *
	 * @param array $image
	 * @param int $attachment_id
	 * @param string|array $size
	 * @param bool $icon
	 * @return array
	 * @access public
	 *
	 * @filter wp_get_attachment_image_src
	 *
	 * @see wp-includes/media.php wp_get_attachment_image_src for icon logic
	 */
	public function wp_get_attachment_image_src_preview( $image, $attachment_id, $size, $icon ) {
		// front end or not an icon - bail
		if ( ! is_admin() || ! $icon ) {
			return $image;
		}

		$mime_type = get_post_mime_type( $attachment_id );

		// image/audio/video - bail
		if ( preg_match( '/^(image|audio|video)\//', $mime_type ) ) {
			return $image;
		}

		// is embeddeable - null the image src
		if ( in_array( $mime_type, $this->embeddable_mime_types, true ) ) {
			$image[0] = null;
			return $image;
		}

		// regernate file icon for protected files
		if ( $this->is_protected( $attachment_id ) ) {
			$src = wp_mime_type_icon( $attachment_id );

			/** This filter is documented in wp-includes/post.php */
			$icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/media' );

			$src_file   = $icon_dir . '/' . wp_basename( $src );
			$image_size = getimagesize( $src_file );

			if ( $src && ! empty( $image_size['width'] ) && ! empty( $image_size['height'] ) ) {
				$image = array( $src, $image_size['width'], $image_size['height'] );
			}
		}

		return $image;
	}

	/**
	 * Render an iframe with the pdf document instead of a document icon
	 *
	 * @param int $attachment_id
	 * @return void
	 * @access public
	 *
	 * @action wp_edit_form_attachment_display
	 */
	public function wp_edit_form_attachment_display( $attachment_id ) {
		if ( in_array( get_post_mime_type( $attachment_id ), $this->embeddable_mime_types, true ) ) {
			printf( '<iframe src="%s" style="width:100%%;min-height:300px;margin-top:1em;"></iframe>', esc_url( get_the_permalink( $attachment_id ) ) );
		}
	}
}
