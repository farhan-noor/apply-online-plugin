<?php
/**
 * AOL Ad Form Builder (v2)
 *
 * Provides a dedicated form builder UI for `aol_ad` post type and persists fields
 * using the plugin's existing meta conventions: `_aol_app_*` + `_aol_fields_order`.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AOL_Ad_Form_Builder_V2' ) ) :
class AOL_Ad_Form_Builder_V2 {
	/** @var string */
	private $version;
	/** @var string */
	private $meta_nonce_key = 'aol_ad_fb_v2_nonce';
	/** @var string */
	private $meta_nonce_action = 'aol_ad_fb_v2_save';
	/** @var string */
	private $schema_post_key = 'aol_ad_fb_v2_schema';

	public function __construct( $version ) {
		$this->version = $version;

		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 20 );
		add_action( 'save_post', array( $this, 'save' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_meta_box() {
		add_meta_box(
			'aol_ad_form_builder_v2',
			'<span class="dashicons dashicons-feedback"></span> ' . esc_html__( 'Form Builder (New)', 'apply-online' ),
			array( $this, 'render_meta_box' ),
			array( 'aol_ad' ),
			'advanced',
			'high'
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( empty( $screen ) || $screen->post_type !== 'aol_ad' ) {
			return;
		}

		$base_url = plugin_dir_url( __FILE__ );
		wp_enqueue_style(
			'aol-ad-fb-v2',
			$base_url . 'assets/aol-ad-form-builder.css',
			array(),
			$this->version
		);
		wp_enqueue_script(
			'aol-ad-fb-v2',
			$base_url . 'assets/aol-ad-form-builder.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'aol-ad-fb-v2',
			'aolAdFbV2',
			array(
				'fieldTypes' => $this->field_types(),
				'fieldTypeIcons' => $this->field_type_icons(),
				'i18n'       => array(
					'addField'     => esc_html__( 'Add field', 'apply-online' ),
					'edit'         => esc_html__( 'Edit', 'apply-online' ),
					'delete'       => esc_html__( 'Delete', 'apply-online' ),
					'confirmDel'   => esc_html__( 'Delete this field?', 'apply-online' ),
					'idRequired'   => esc_html__( 'Unique ID is required.', 'apply-online' ),
					'labelRequired'=> esc_html__( 'Label is required.', 'apply-online' ),
					'optionsHint'  => esc_html__( 'Comma separated (Option 1, Option 2)', 'apply-online' ),
				),
			)
		);
	}

	public function render_meta_box( $post ) {
		$schema = $this->get_schema_for_post( $post->ID );
		wp_nonce_field( $this->meta_nonce_action, $this->meta_nonce_key );
		?>
		<div class="aol-ad-fb-v2" data-post-id="<?php echo (int) $post->ID; ?>">
			<p class="description">
				<?php esc_html_e( 'Build the application form for this Ad. Fields are stored using the existing ApplyOnline meta keys so the front-end stays compatible.', 'apply-online' ); ?>
			</p>

			<input type="hidden" name="<?php echo esc_attr( $this->schema_post_key ); ?>" class="aol-ad-fb-v2-schema" value="<?php echo esc_attr( wp_json_encode( $schema ) ); ?>" />

			<div class="aol-ad-fb-v2-toolbar">
				<button type="button" class="button button-primary aol-ad-fb-v2-add">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add field', 'apply-online' ); ?>
				</button>
			</div>

			<table class="widefat striped aol-ad-fb-v2-table">
				<thead>
					<tr>
						<th class="col-order"></th>
						<th><?php esc_html_e( 'Field', 'apply-online' ); ?></th>
						<th><?php esc_html_e( 'Required', 'apply-online' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'apply-online' ); ?></th>
					</tr>
				</thead>
				<tbody class="aol-ad-fb-v2-rows"></tbody>
			</table>

			<template class="aol-ad-fb-v2-row-template">
				<tr class="aol-ad-fb-v2-row" tabindex="0" role="button">
					<td class="col-order">
						<span class="dashicons dashicons-menu"></span>
					</td>
					<td class="col-field">
						<div class="aol-ad-fb-v2-preview">
							<div class="aol-ad-fb-v2-preview__meta">
								<span class="aol-ad-fb-v2-preview__label"></span>
								<span class="aol-ad-fb-v2-preview__type"></span>
							</div>
							<div class="aol-ad-fb-v2-preview__control"></div>
							<div class="aol-ad-fb-v2-preview__help"></div>
						</div>
					</td>
					<td class="col-required"></td>
					<td class="col-actions">
						<button type="button" class="button button-small aol-ad-fb-v2-edit"><?php esc_html_e( 'Edit', 'apply-online' ); ?></button>
						<button type="button" class="button button-small aol-ad-fb-v2-delete"><?php esc_html_e( 'Delete', 'apply-online' ); ?></button>
						<button type="button" class="button button-small aol-ad-fb-v2-up" aria-label="<?php esc_attr_e( 'Move up', 'apply-online' ); ?>">↑</button>
						<button type="button" class="button button-small aol-ad-fb-v2-down" aria-label="<?php esc_attr_e( 'Move down', 'apply-online' ); ?>">↓</button>
					</td>
				</tr>
			</template>

			<div class="aol-ad-fb-v2-modal" style="display:none;">
				<div class="aol-ad-fb-v2-modal__inner">
					<div class="aol-ad-fb-v2-modal__header">
						<h3 class="aol-ad-fb-v2-modal__title"><?php esc_html_e( 'Field', 'apply-online' ); ?></h3>
						<button type="button" class="button-link aol-ad-fb-v2-close" aria-label="<?php esc_attr_e( 'Close', 'apply-online' ); ?>">×</button>
					</div>

					<div class="aol-ad-fb-v2-modal__body">
						<div class="aol-ad-fb-v2-section">
							<div class="aol-ad-fb-v2-section__title"><?php esc_html_e( 'Field type', 'apply-online' ); ?></div>
							<div class="aol-ad-fb-v2-type-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Field types', 'apply-online' ); ?>"></div>
							<input type="hidden" class="aol-ad-fb-v2-type" value="text" />
						</div>

						<div class="aol-ad-fb-v2-grid">
							<label>
								<span><?php esc_html_e( 'Unique ID', 'apply-online' ); ?> *</span>
								<input type="text" class="regular-text aol-ad-fb-v2-id" placeholder="first_name" />
							</label>

							<label>
								<span><?php esc_html_e( 'Label', 'apply-online' ); ?> *</span>
								<input type="text" class="regular-text aol-ad-fb-v2-label" placeholder="<?php esc_attr_e( 'e.g. First name', 'apply-online' ); ?>" />
							</label>

							<label class="aol-ad-fb-v2-toggle">
								<span><?php esc_html_e( 'Required', 'apply-online' ); ?></span>
								<input type="checkbox" class="aol-ad-fb-v2-required" value="1" />
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--placeholder">
								<span><?php esc_html_e( 'Placeholder', 'apply-online' ); ?></span>
								<input type="text" class="regular-text aol-ad-fb-v2-placeholder" />
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--help">
								<span><?php esc_html_e( 'Help text', 'apply-online' ); ?></span>
								<input type="text" class="regular-text aol-ad-fb-v2-description" />
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--options">
								<span><?php esc_html_e( 'Options', 'apply-online' ); ?></span>
								<input type="text" class="regular-text aol-ad-fb-v2-options" placeholder="<?php esc_attr_e( 'Option 1, Option 2', 'apply-online' ); ?>" />
								<small class="description"><?php esc_html_e( 'Comma separated.', 'apply-online' ); ?></small>
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--paragraph">
								<span><?php esc_html_e( 'Paragraph text', 'apply-online' ); ?></span>
								<textarea class="large-text aol-ad-fb-v2-text" rows="4"></textarea>
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--paragraph">
								<span><?php esc_html_e( 'Fixed height (px)', 'apply-online' ); ?></span>
								<input type="number" class="small-text aol-ad-fb-v2-height" min="0" value="0" />
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--file">
								<span><?php esc_html_e( 'Allowed file types', 'apply-online' ); ?></span>
								<input type="text" class="regular-text aol-ad-fb-v2-allowed-file-types" placeholder="pdf, doc, docx" />
							</label>

							<label class="aol-ad-fb-v2-only aol-ad-fb-v2-only--file">
								<span><?php esc_html_e( 'Max size (MB)', 'apply-online' ); ?></span>
								<input type="number" class="small-text aol-ad-fb-v2-file-max-size" min="0" />
							</label>
						</div>
					</div>

					<div class="aol-ad-fb-v2-modal__footer">
						<button type="button" class="button aol-ad-fb-v2-cancel"><?php esc_html_e( 'Cancel', 'apply-online' ); ?></button>
						<button type="button" class="button button-primary aol-ad-fb-v2-save"><?php esc_html_e( 'Save field', 'apply-online' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function save( $post_id, $post ) {
		$post_id = (int) $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $post ) || empty( $post->post_type ) || $post->post_type !== 'aol_ad' ) {
			return;
		}
		if ( ! current_user_can( 'edit_ads', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST[ $this->meta_nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->meta_nonce_key ] ) ), $this->meta_nonce_action ) ) {
			return;
		}
		if ( ! isset( $_POST[ $this->schema_post_key ] ) ) {
			return;
		}

		$raw = wp_unslash( $_POST[ $this->schema_post_key ] );
		$schema = json_decode( is_string( $raw ) ? $raw : '', true );
		if ( ! is_array( $schema ) ) {
			return;
		}

		$existing = $this->get_existing_app_meta_keys( $post_id );
		$new_keys = array();
		$order = array();

		foreach ( $schema as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$uid = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
			$label = isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : '';
			$type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : 'text';

			if ( $uid === '' || $label === '' ) {
				continue;
			}
			if ( ! array_key_exists( $type, $this->field_types() ) ) {
				$type = 'text';
			}

			$meta_key = '_aol_app_' . $uid;
			$new_keys[] = $meta_key;
			$order[] = $meta_key;

			$val = array(
				'label' => $label,
				'type'  => $type,
			);

			$val['required'] = ! empty( $field['required'] ) ? 1 : 0;
			$val['description'] = isset( $field['description'] ) ? sanitize_text_field( (string) $field['description'] ) : '';
			$val['placeholder'] = isset( $field['placeholder'] ) ? sanitize_text_field( (string) $field['placeholder'] ) : '';
			$val['class'] = isset( $field['class'] ) ? sanitize_text_field( (string) $field['class'] ) : '';

			if ( isset( $field['limit'] ) ) {
				$val['limit'] = max( 0, (int) $field['limit'] );
			}

			if ( in_array( $type, array( 'checkbox', 'radio', 'dropdown' ), true ) ) {
				$opts = isset( $field['options'] ) ? (string) $field['options'] : '';
				$opts = array_map( 'trim', explode( ',', $opts ) );
				$opts = array_filter(
					$opts,
					static function ( $s ) {
						return $s !== '';
					}
				);
				$opts = implode( ',', $opts );
				$val['options'] = $opts;
			}

			if ( $type === 'radio' ) {
				$val['preselect'] = ! empty( $field['preselect'] ) ? 1 : 0;
			}

			if ( $type === 'paragraph' ) {
				$val['text'] = isset( $field['text'] ) ? sanitize_textarea_field( (string) $field['text'] ) : '';
				$val['height'] = isset( $field['height'] ) ? max( 0, (int) $field['height'] ) : 0;
				$val['required'] = 0;
			}

			if ( $type === 'separator' ) {
				$val['required'] = 0;
			}

			if ( $type === 'file' ) {
				$val['allowed_file_types'] = isset( $field['allowed_file_types'] ) ? sanitize_text_field( (string) $field['allowed_file_types'] ) : sanitize_text_field( (string) get_option( 'aol_allowed_file_types', defined( 'ALLOWED_FILE_TYPES' ) ? ALLOWED_FILE_TYPES : '' ) );
				$val['file_max_size'] = isset( $field['file_max_size'] ) ? max( 0, (int) $field['file_max_size'] ) : (int) get_option( 'aol_upload_max_size', 0 );
			}

			update_post_meta( $post_id, $meta_key, $val );
		}

		// Delete removed meta keys
		$to_delete = array_diff( $existing, $new_keys );
		foreach ( $to_delete as $k ) {
			delete_post_meta( $post_id, $k );
		}

		update_post_meta( $post_id, '_aol_fields_order', $order );
	}

	private function field_types() {
		return array(
			'text'      => esc_html__( 'Text', 'apply-online' ),
			'text_area' => esc_html__( 'Textarea', 'apply-online' ),
			'number'    => esc_html__( 'Number', 'apply-online' ),
			'email'     => esc_html__( 'Email', 'apply-online' ),
			'date'      => esc_html__( 'Date', 'apply-online' ),
			'checkbox'  => esc_html__( 'Checkbox', 'apply-online' ),
			'radio'     => esc_html__( 'Radio', 'apply-online' ),
			'dropdown'  => esc_html__( 'Dropdown', 'apply-online' ),
			'file'      => esc_html__( 'File', 'apply-online' ),
			'separator' => esc_html__( 'Separator', 'apply-online' ),
			'paragraph' => esc_html__( 'Paragraph', 'apply-online' ),
		);
	}

	private function field_type_icons() {
		return array(
			'text'      => 'dashicons-editor-textcolor',
			'text_area' => 'dashicons-format-aside',
			'number'    => 'dashicons-editor-ol',
			'email'     => 'dashicons-email-alt',
			'date'      => 'dashicons-calendar',
			'checkbox'  => 'dashicons-yes',
			'radio'     => 'dashicons-marker',
			'dropdown'  => 'dashicons-sort',
			'file'      => 'dashicons-paperclip',
			'separator' => 'dashicons-minus',
			'paragraph' => 'dashicons-editor-justify',
		);
	}

	private function get_existing_app_meta_keys( $post_id ) {
		$post_id = (int) $post_id;
		$all = get_post_meta( $post_id );
		$keys = array();
		foreach ( $all as $key => $_val ) {
			if ( substr( (string) $key, 0, 9 ) === '_aol_app_' ) {
				$keys[] = (string) $key;
			}
		}
		return $keys;
	}

	private function get_schema_for_post( $post_id ) {
		$post_id = (int) $post_id;
		$order = get_post_meta( $post_id, '_aol_fields_order', true );
		$order = is_array( $order ) ? $order : array();

		$fields = array();
		$known = $this->get_existing_app_meta_keys( $post_id );

		// Fallback: if order missing, use current meta keys ordering
		if ( empty( $order ) ) {
			$order = $known;
		}

		foreach ( $order as $meta_key ) {
			$meta_key = (string) $meta_key;
			if ( ! in_array( $meta_key, $known, true ) ) {
				continue;
			}
			$val = get_post_meta( $post_id, $meta_key, true );
			if ( ! is_array( $val ) ) {
				continue;
			}
			$id = str_replace( '_aol_app_', '', $meta_key );
			$fields[] = array(
				'id'                 => sanitize_key( $id ),
				'label'              => isset( $val['label'] ) ? sanitize_text_field( (string) $val['label'] ) : sanitize_text_field( $id ),
				'type'               => isset( $val['type'] ) ? sanitize_key( (string) $val['type'] ) : 'text',
				'required'           => ! empty( $val['required'] ) ? 1 : 0,
				'description'        => isset( $val['description'] ) ? sanitize_text_field( (string) $val['description'] ) : '',
				'placeholder'        => isset( $val['placeholder'] ) ? sanitize_text_field( (string) $val['placeholder'] ) : '',
				'options'            => isset( $val['options'] ) ? sanitize_text_field( (string) $val['options'] ) : '',
				'class'              => isset( $val['class'] ) ? sanitize_text_field( (string) $val['class'] ) : '',
				'preselect'          => ! empty( $val['preselect'] ) ? 1 : 0,
				'text'               => isset( $val['text'] ) ? sanitize_textarea_field( (string) $val['text'] ) : '',
				'height'             => isset( $val['height'] ) ? (int) $val['height'] : 0,
				'allowed_file_types' => isset( $val['allowed_file_types'] ) ? sanitize_text_field( (string) $val['allowed_file_types'] ) : '',
				'file_max_size'      => isset( $val['file_max_size'] ) ? (int) $val['file_max_size'] : 0,
				'limit'              => isset( $val['limit'] ) ? (int) $val['limit'] : 0,
			);
		}

		return $fields;
	}
}
endif;

