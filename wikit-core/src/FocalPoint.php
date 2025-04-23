<?php

namespace WDG\Core;

use WP_HTML_Tag_Processor;

class FocalPoint {

	use SingletonTrait;

	protected string $meta_key = 'focal_point';

	protected function __construct() {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'wp_enqueue_media', [ $this, 'enqueue_script' ] );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'wp_prepare_attachment_for_js' ], 1, 3 );
		add_action( 'wp_ajax_save-attachment', [ $this, 'wp_ajax_save_attachment' ], 1 );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'edit_attachment', [ $this, 'edit_attachment' ], 1, 3 );
		add_action( 'wp_enqueue_media', [ $this, 'enqueue_script' ], 0 );
		add_filter( 'render_block_core/image', [ $this, 'render_block_core_image' ], 1, 2 );
		add_filter( 'render_block_core/media-text', [ $this, 'render_block_core_media_text' ], 1, 2 );
		add_filter( 'render_block_core/cover', [ $this, 'render_block_core_cover' ], 1, 2 );
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'wp_get_attachment_image_attributes' ], 1, 2 );
	}

	/**
	 * Register the meta value on attachments
	 *
	 * @return void
	 * @action init
	 */
	public function init() : void {
		register_meta(
			'post',
			$this->meta_key,
			[
				'object_subtype' => 'attachment',
				'type'           => 'object',
				'single'         => true,
				'default'        => (object) [
					'x' => 0.5,
					'y' => 0.5,
				],
				'show_in_rest'   => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'x' => [
								'type' => 'number',
							],
							'y' => [
								'type' => 'number',
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Enqueue the focal-point component
	 *
	 * @return void
	 */
	public function enqueue_script() {
		wp_enqueue_script( 'wdg-core-focal-point' ); // this file is registered in wikit-core/index.php
	}

	/**
	 * Save the focal point for a post saved from the edit more details screen
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function edit_attachment( $post_id ) : void {
		$screen = get_current_screen();

		if ( ! empty( $screen ) && 'attachment' === $screen->id ) {
			check_admin_referer( 'update-post_' . $post_id );

			if ( ! empty( $_POST[ $this->meta_key ]['x'] ) && ! empty( $_POST[ $this->meta_key ]['y'] ) ) {
				$focal_point = (object) [
					'x' => (float) sanitize_text_field( $_POST[ $this->meta_key ]['x'] ),
					'y' => (float) sanitize_text_field( $_POST[ $this->meta_key ]['y'] ),
				];

				update_post_meta( $post_id, $this->meta_key, $focal_point );
			}
		}
	}

	/**
	 * Add the focal point meta box
	 *
	 * @return void
	 */
	public function add_meta_boxes() : void {
		$this->enqueue_script();

		add_meta_box( 'attachment-focal-point', 'Focal Point', [ $this, 'render_meta_box' ], 'attachment', 'side' );
	}

	/**
	 * Render the focal point meta box on the attachment edit screen
	 */
	public function render_meta_box() {
		global $post_ID;

		$value = get_post_meta( $post_ID, $this->meta_key, true );

		printf( '<input type="hidden" name="%s[x]" value="%s">', esc_attr( $this->meta_key ), esc_attr( $value->x ) );
		printf( '<input type="hidden" name="%s[y]" value="%s">', esc_attr( $this->meta_key ), esc_attr( $value->y ) );

		printf( '<figure id="attachment-focal-point-slot" data-src="%s"></figure>', wp_kses_post( wp_get_attachment_image_url( $post_ID, 'medium' ) ) );
	}

	/**
	 * Add the focal point to the backbone js model for an attachment
	 *
	 * @param array $response
	 * @param WP_Post $attachment
	 * @param false|array $meta - generated meta data or false
	 * @return array
	 */
	public function wp_prepare_attachment_for_js( $response, $attachment ) {
		$response[ $this->meta_key ] = get_post_meta( $attachment->ID, $this->meta_key, true );

		return $response;
	}

	/**
	 * Save a changing in focal point when changing the FocalPointPicker component
	 *
	 * @return void
	 * @action wp_ajax_save-attachment
	 */
	public function wp_ajax_save_attachment() : void {
		if ( isset( $_REQUEST['id'] ) && isset( $_REQUEST['changes'] ) ) {
			$id = absint( $_REQUEST['id'] );

			if ( $id ) {
				check_ajax_referer( 'update-post_' . $id, 'nonce' );

				if ( current_user_can( 'edit_post', $id ) ) {
					$changes = $_REQUEST['changes'];

					if ( is_array( $changes['focal_point'] ) && ! empty( $changes['focal_point']['x'] ) && ! empty( $changes['focal_point']['y'] ) ) {
						update_post_meta(
							$id,
							$this->meta_key,
							(object) [
								'x' => (float) $changes['focal_point']['x'],
								'y' => (float) $changes['focal_point']['y'],
							]
						);
					}
				}
			}
		}
	}

	/**
	 * Get the inline style for a focal point based on x and y attributes
	 *
	 * @param object|array $focal_point
	 * @return string style to insert
	*/
	public static function get_inline_style( $focal_point ) : string {
		if ( empty( $focal_point ) ) {
			return '';
		}

		$focal_point = (object) $focal_point;

		if ( empty( $focal_point->x ) || empty( $focal_point->y ) ) {
			return '';
		}

		return sprintf( 'object-position: %s%% %s%%', round( (float) $focal_point->x * 100 ), round( (float) $focal_point->y * 100 ) );
	}

	/**
	 * Adds a focal_point_style to a tag processor already at it's selected tag
	 *
	 * @param WP_HTML_Tag_Processor $processor
	 * @param string $focal_point_style
	 * @return void
	 */
	protected static function add_inline_style( WP_HTML_Tag_Processor $processor, string $focal_point_style ) : void {
		$style = $processor->get_attribute( 'style' );

		if ( ! empty( $style ) ) {
			$processor->set_attribute( 'style', static::modify_inline_style( $style, $focal_point_style ) );
		} else {
			$processor->set_attribute( 'style', $focal_point_style );
		}
	}

	/**
	 * Modify a style attribute to include the object-position focal point style
	 *
	 * @param string $style - existing style
	 * @param string $focal_point_style - the key:value of the new focal point style
	 * @param bool $override - override an existing object-position property
	 * @return string
	 */
	public static function modify_inline_style( string $style, string $focal_point_style, bool $override = false ) {
		$rules = array_reduce(
			array_filter( explode( ';', $style ) ),
			function ( $rules, $rule ) {
				$rule              = explode( ':', $rule, 2 );
				$rules[ $rule[0] ] = $rule[1];

				return $rules;
			},
			[]
		);

		if ( $override || empty( $rules['object-position'] ) ) {
			$rules['object-position'] = trim( substr( $focal_point_style, strpos( $focal_point_style, ':' ) + 1 ) );

			$style = implode(
				';',
				array_map(
					fn( $prop ) => implode( ':', [ $prop, $rules[ $prop ] ] ),
					array_keys( $rules )
				)
			);
		}

		return $style;
	}

	/**
	 * Add the focal point to the core/image block
	 *
	 * @param string $html
	 * @param array $block
	 * @return string
	 */
	public function render_block_core_image( $html, $block ) {
		if ( ! empty( $block['attrs']['id'] ) ) {
			$focal_point = get_post_meta( $block['attrs']['id'], $this->meta_key, true );

			if ( ! empty( $focal_point ) ) {
				$processor = new WP_HTML_Tag_Processor( $html );

				if ( $processor->next_tag( 'img' ) ) {
					$this->add_inline_style( $processor, $this->get_inline_style( $focal_point ) );

					$html = $processor->get_updated_html();
				}
			}
		}

		return $html;
	}

	/**
	 * Add the focal point to the core/media-text block
	 *
	 * @param string $html
	 * @param array $block
	 * @return string
	 */
	public function render_block_core_media_text( $html, $block ) {
		if ( ! empty( $block['attrs']['mediaId'] ) ) {
			$focal_point = get_post_meta( $block['attrs']['mediaId'], $this->meta_key, true );

			if ( ! empty( $focal_point ) ) {
				$processor = new WP_HTML_Tag_Processor( $html );

				if ( $processor->next_tag( 'img' ) ) {
					$this->add_inline_style( $processor, $this->get_inline_style( $focal_point ) );

					$html = $processor->get_updated_html();
				}
			}
		}

		return $html;
	}

	/**
	 * Add the focal point to the core/cover block
	 *
	 * @param string $html
	 * @param array $block
	 * @return string
	 */
	public function render_block_core_cover( $html, $block ) {
		if ( ! empty( $block['attrs']['id'] ) ) {
			$focal_point = get_post_meta( $block['attrs']['id'], $this->meta_key, true );

			if ( ! empty( $focal_point ) ) {
				$processor = new WP_HTML_Tag_Processor( $html );

				if ( $processor->next_tag( [ 'class_name' => 'wp-block-cover__image-background' ] ) ) {
					$this->add_inline_style( $processor, $this->get_inline_style( $focal_point ) );

					$html = $processor->get_updated_html();
				}
			}
		}

		return $html;
	}

	/**
	 * Modify the inline style attribute of a call to wp_get_attachment_image
	 *
	 * @param array $attributes
	 * @param WP_Post $attachment
	 * @return array
	 */
	public function wp_get_attachment_image_attributes( $attributes, $attachment ) {
		$focal_point = get_post_meta( $attachment->ID, $this->meta_key, true );

		if ( ! empty( $focal_point ) ) {
			if ( ! empty( $attributes['style'] ) ) {
				$attributes['style'] = $this->modify_inline_style( $attributes['style'], $this->get_inline_style( $focal_point ) );
			} else {
				$attributes['style'] = $this->get_inline_style( $focal_point );
			}
		}

		return $attributes;
	}
}
