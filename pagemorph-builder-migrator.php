<?php
/**
 * Plugin Name:       PageMorph Builder Migrator
 * Plugin URI:        https://wordpress.org/plugins/pagemorph-builder-migrator/
 * Description:       Securely pulls Elementor layouts from a staging site into an existing live page built with WPBakery via the REST API. Automatically deactivates WPBakery data on the page, purges legacy shortcodes, sideloads media, and preserves RankMath SEO metadata completely untouched.
 * Version:           1.0.1
 * Author:            Alkesh Miyani
 * Author URI:        https://miyanialkesh7.com
 * Text Domain:       pagemorph-builder-migrator
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class PageMorph_Builder_Migrator
{

	/** Populated by pagemorph_sideload_layout_media(); used to patch the Elementor CSS file. */
	private $url_remap = array();

	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'pagemorph_register_sync_meta_box'));
		add_action('admin_enqueue_scripts', array($this, 'pagemorph_enqueue_admin_assets'));
		add_action('wp_ajax_pagemorph_pull_content', array($this, 'pagemorph_ajax_handle_pull_content'));
	}

	/**
	 * Register the Sidebar Meta Box on supported post types.
	 */
	public function pagemorph_register_sync_meta_box()
	{
		$supported_post_types = get_option('elementor_cpt_support', array('post', 'page'));
		if (!is_array($supported_post_types)) {
			$supported_post_types = array('post', 'page');
		}

		foreach ($supported_post_types as $post_type) {
			add_meta_box(
				'pagemorph_pull_box',
				__('Staging Layout Sync', 'pagemorph-builder-migrator'),
				array($this, 'pagemorph_render_meta_box_html'),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render Meta Box Controls.
	 */
	public function pagemorph_render_meta_box_html($post)
	{
		wp_nonce_field('pagemorph_pull_nonce_action', 'pagemorph_pull_nonce');
		?>
		<div class="pagemorph-meta-box-wrapper">
			<p style="color: #d63638; font-weight: bold; font-size: 12px; border-left: 3px solid #d63638; padding-left: 5px;">
				<?php esc_html_e('Ideal for moving from WPBakery to Elementor without losing RankMath data.', 'pagemorph-builder-migrator'); ?>
			</p>
			<p>
				<label
					for="pagemorph_staging_url"><strong><?php esc_html_e('Staging Site URL:', 'pagemorph-builder-migrator'); ?></strong></label>
				<input type="url" id="pagemorph_staging_url" class="large-text" placeholder="https://example.com" value="" />
			</p>
			<p>
				<label
					for="pagemorph_app_username"><strong><?php esc_html_e('Staging Admin Username:', 'pagemorph-builder-migrator'); ?></strong></label>
				<input type="text" id="pagemorph_app_username" class="large-text" placeholder="admin" value="" />
			</p>
			<p>
				<label
					for="pagemorph_app_password"><strong><?php esc_html_e('Staging Application Password:', 'pagemorph-builder-migrator'); ?></strong></label>
				<input type="password" id="pagemorph_app_password" class="large-text"
					placeholder="<?php esc_attr_e('xxxx xxxx xxxx xxxx', 'pagemorph-builder-migrator'); ?>" value="" />
				<span
					class="description"><?php esc_html_e('Generated via Staging Profile > Application Passwords.', 'pagemorph-builder-migrator'); ?></span>
			</p>
			<p>
				<label
					for="pagemorph_staging_post_id"><strong><?php esc_html_e('Staging Post ID (Elementor):', 'pagemorph-builder-migrator'); ?></strong></label>
				<input type="number" id="pagemorph_staging_post_id" class="all-options" min="1" placeholder="45" value="" />
			</p>
			<hr />
			<button type="button" id="pagemorph_pull_btn" class="button button-primary button-large"
				style="width:100%; text-align:center;">
				<?php esc_html_e('Migrate Layout & Clear WPBakery', 'pagemorph-builder-migrator'); ?>
			</button>
			<div id="pagemorph_status_message" style="margin-top:10px; font-weight:bold;"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets for the sync interface.
	 */
	public function pagemorph_enqueue_admin_assets($hook)
	{
		if ('post.php' !== $hook && 'post-new.php' !== $hook) {
			return;
		}

		global $post;
		if (!$post) {
			return;
		}

		$supported_post_types = get_option('elementor_cpt_support', array('post', 'page'));
		if (!is_array($supported_post_types)) {
			$supported_post_types = array('post', 'page');
		}

		if (!in_array($post->post_type, $supported_post_types, true)) {
			return;
		}

		wp_enqueue_script(
			'pagemorph-admin-sync',
			plugins_url('assets/js/admin-sync.js', __FILE__),
			array('jquery', 'wp-util'),
			'1.0.0',
			true
		);

		wp_localize_script(
			'pagemorph-admin-sync',
			'pageMorphSyncData',
			array(
				'postId' => absint($post->ID),
				'i18n' => array(
					'fillFields' => __('Please fill out all staging credentials and the Post ID.', 'pagemorph-builder-migrator'),
					'confirmPrompt' => __('Warning: This will overwrite your live layout, erase WPBakery settings for this post, and apply the Elementor design. RankMath SEO metadata is safely preserved. Proceed?', 'pagemorph-builder-migrator'),
					'purging' => __('Purging WPBakery & Overwriting...', 'pagemorph-builder-migrator'),
					'connecting' => __('Connecting to staging site API...', 'pagemorph-builder-migrator'),
					'migrateBtn' => __('Migrate Layout & Clear WPBakery', 'pagemorph-builder-migrator'),
					'errorPrefix' => __('Error: ', 'pagemorph-builder-migrator'),
					'unknownError' => __('An unknown execution error occurred.', 'pagemorph-builder-migrator'),
				),
			)
		);
	}

	/**
	 * Main AJAX execution method.
	 */
	public function pagemorph_ajax_handle_pull_content()
	{
		check_ajax_referer('pagemorph_pull_nonce_action', '_ajax_nonce');

		$local_id = absint(wp_unslash($_POST['local_id'] ?? 0));
		if (!$local_id) {
			wp_send_json_error(array('message' => __('Missing parameters.', 'pagemorph-builder-migrator')));
		}

		$post_type = get_post_type($local_id);
		$post_type_object = get_post_type_object($post_type);
		if (!$post_type_object) {
			wp_send_json_error(array('message' => __('Invalid post type.', 'pagemorph-builder-migrator')));
		}

		$edit_capability = $post_type_object->cap->edit_post;
		if (!current_user_can($edit_capability, $local_id)) {
			wp_send_json_error(array('message' => __('Insufficient permissions to perform this action.', 'pagemorph-builder-migrator')));
		}

		$staging_url = esc_url_raw(wp_unslash($_POST['staging_url'] ?? ''));
		$username = sanitize_text_field(wp_unslash($_POST['app_username'] ?? ''));
		$app_password = sanitize_text_field(wp_unslash($_POST['app_password'] ?? ''));
		$staging_id = absint(wp_unslash($_POST['staging_id'] ?? 0));

		if (!$staging_url || !$username || !$app_password || !$staging_id) {
			wp_send_json_error(array('message' => __('Missing parameters.', 'pagemorph-builder-migrator')));
		}

		$rest_base = !empty($post_type_object->rest_base) ? $post_type_object->rest_base : $post_type;

		$rest_bases_to_try = array($rest_base);
		if ($rest_base !== 'pages') {
			$rest_bases_to_try[] = 'pages';
		}
		if ($rest_base !== 'posts') {
			$rest_bases_to_try[] = 'posts';
		}

		// Also try any other registered public post types' rest bases
		$public_post_types = get_post_types(array('public' => true), 'objects');
		if (is_array($public_post_types)) {
			foreach ($public_post_types as $pt) {
				$pb = !empty($pt->rest_base) ? $pt->rest_base : $pt->name;
				if (!in_array($pb, $rest_bases_to_try, true)) {
					$rest_bases_to_try[] = $pb;
				}
			}
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode($username . ':' . $app_password),
			),
			'timeout' => 45,
		);

		$response = null;
		$status_code = 404;
		$last_error_message = '';

		foreach ($rest_bases_to_try as $try_base) {
			$api_url = trailingslashit($staging_url) . 'wp-json/wp/v2/' . $try_base . '/' . $staging_id . '?_fields=meta,content&context=edit';
			$response = wp_remote_get($api_url, $args);

			if (is_wp_error($response)) {
				$last_error_message = $response->get_error_message();
				continue;
			}

			$status_code = wp_remote_retrieve_response_code($response);
			if (200 === $status_code) {
				break;
			}
		}

		if (is_wp_error($response) && 200 !== $status_code) {
			/* translators: %s: Staging API error message details. */
			wp_send_json_error(array('message' => sprintf(__('Staging API request failed: %s', 'pagemorph-builder-migrator'), $last_error_message ?: $response->get_error_message())));
		}

		if (200 !== $status_code) {
			/* translators: %d: HTTP status code. */
			wp_send_json_error(array('message' => sprintf(__('Staging API returned status code %d. Verify your credentials and Page/Post ID.', 'pagemorph-builder-migrator'), $status_code)));
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($data) || !isset($data['meta']['_elementor_data'])) {
			wp_send_json_error(array('message' => __('Failed to fetch valid meta records from staging.', 'pagemorph-builder-migrator')));
		}

		$meta_payload = $data['meta'];
		$elementor_data = $meta_payload['_elementor_data'] ?? '';

		if (empty($elementor_data)) {
			wp_send_json_error(array('message' => __('Target staging page does not contain any Elementor Layout data.', 'pagemorph-builder-migrator')));
		}

		// Process image sideloading and update URLs inside the layout JSON string
		$updated_elementor_data = $this->pagemorph_sideload_layout_media($elementor_data, $staging_url);

		// Create an automated local revision backup before modification for data safety
		wp_save_post_revision($local_id);

		// 1. CLEAN UP WPBAKERY META KEYS ON LIVE
		delete_post_meta($local_id, '_wpb_vc_js_status');
		delete_post_meta($local_id, 'vc_teaser');

		// 2. SET UP NEW ELEMENTOR PARAMETERS ON LIVE
		update_post_meta($local_id, '_elementor_data', wp_slash($updated_elementor_data));
		update_post_meta($local_id, '_elementor_edit_mode', 'builder');

		// Map page layout setting template if specified (e.g. Elementor Full Width or Elementor Canvas)
		if (isset($meta_payload['_wp_page_template']) && !empty($meta_payload['_wp_page_template'])) {
			update_post_meta($local_id, '_wp_page_template', sanitize_text_field($meta_payload['_wp_page_template']));
		} else {
			// Fallback standard full width layout for modern conversion setups
			update_post_meta($local_id, '_wp_page_template', 'elementor_header_footer');
		}

		// 3. PURGE THE OLD WPBAKERY SHORTCODES FROM MAIN CONTENT STREAM
		// Leaves rank_math_ fields completely untouched in wp_postmeta table.
		$staging_content = $data['content']['raw'] ?? $data['content']['rendered'] ?? '';
		wp_update_post(array(
			'ID' => $local_id,
			'post_content' => $staging_content,
		));

		// Patch the Elementor CSS file with the new local media URLs so background images
		// are correct immediately — no on-demand regeneration needed for the first visitor.
		$this->pagemorph_patch_elementor_css($local_id);

		wp_send_json_success(array('message' => __('Success! WPBakery data cleared, new Elementor layout synced, and RankMath safely preserved. Reloading...', 'pagemorph-builder-migrator')));
	}

	/**
	 * Patch the Elementor-generated CSS file for a post in place.
	 *
	 * Strategy:
	 *  1. If the CSS file exists on disk → replace staging URLs with local ones and
	 *     bump the cached meta timestamp so Elementor keeps treating the file as valid.
	 *  2. If the CSS file does not exist yet → delete the stale _elementor_css meta so
	 *     Elementor generates a fresh file (from the already-updated _elementor_data) on
	 *     the next page load. The file is absent when the page was never viewed after the
	 *     last Elementor save, so no staging URLs can be cached in it anyway.
	 */
	private function pagemorph_patch_elementor_css($post_id)
	{
		if (empty($this->url_remap)) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$css_file_path = $upload_dir['basedir'] . '/elementor/css/post-' . $post_id . '.css';

		if (file_exists($css_file_path)) {
			$css_content = file_get_contents($css_file_path); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if (false !== $css_content) {
				foreach ($this->url_remap as $old_url => $new_url) {
					$css_content = str_replace($old_url, $new_url, $css_content);
				}
				file_put_contents($css_file_path, $css_content); // phpcs:ignore WordPress.WP.AlternativeFunctions

				// Keep the existing meta but refresh its timestamp so Elementor does not
				// invalidate the file we just patched.
				$css_meta = get_post_meta($post_id, '_elementor_css', true);
				if (is_array($css_meta)) {
					$css_meta['time'] = time();
					update_post_meta($post_id, '_elementor_css', $css_meta);
				}
			}
		} else {
			// No cached CSS file — wipe the meta so Elementor generates a clean one.
			delete_post_meta($post_id, '_elementor_css');
			if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}
	}

	/**
	 * Parse JSON string, lookup remote staging domains, sideload image items, and alter URLs.
	 */
	private function pagemorph_sideload_layout_media($elementor_json_string, $staging_url)
	{
		$elementor_data = json_decode($elementor_json_string, true);
		if (!is_array($elementor_data)) {
			return $elementor_json_string;
		}

		$staging_domain = wp_parse_url($staging_url, PHP_URL_HOST);

		// Collect all staging image URLs by walking the decoded structure (handles escaped slashes correctly).
		$staging_urls = array();
		$this->pagemorph_collect_staging_urls($elementor_data, $staging_domain, $staging_urls);

		if (empty($staging_urls)) {
			return $elementor_json_string;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url_remap = array();
		$id_remap = array();

		foreach (array_unique($staging_urls) as $remote_url) {
			$filename = basename(wp_parse_url($remote_url, PHP_URL_PATH));
			$local_attachment_id = $this->pagemorph_get_attachment_id_by_filename($filename);

			if (!$local_attachment_id) {
				$local_attachment_id = media_sideload_image($remote_url, 0, null, 'id');
			}

			if (!is_wp_error($local_attachment_id) && $local_attachment_id) {
				$url_remap[$remote_url] = wp_get_attachment_url($local_attachment_id);
				$id_remap[$remote_url] = (int) $local_attachment_id;
			}
		}

		if (empty($url_remap)) {
			return $elementor_json_string;
		}

		// Expose the remap so the AJAX handler can patch the Elementor CSS file directly.
		$this->url_remap = $url_remap;

		$this->pagemorph_replace_media_recursive($elementor_data, $url_remap, $id_remap);

		return wp_json_encode($elementor_data);
	}

	/**
	 * Recursively collect all staging-domain image URLs from the decoded Elementor data array.
	 */
	private function pagemorph_collect_staging_urls($data, $staging_domain, &$urls)
	{
		if (!is_array($data)) {
			return;
		}
		foreach ($data as $value) {
			if (is_array($value)) {
				$this->pagemorph_collect_staging_urls($value, $staging_domain, $urls);
			} elseif (is_string($value) && strpos($value, $staging_domain) !== false) {
				preg_match_all(
					'/https?:\/\/' . preg_quote($staging_domain, '/') . '[^\s"\')\\\]*\.(?:jpg|jpeg|png|gif|svg|webp)/i',
					$value,
					$matches
				);
				if (!empty($matches[0])) {
					$urls = array_merge($urls, $matches[0]);
				}
			}
		}
	}

	/**
	 * Recursively replace staging URLs and attachment IDs in the decoded Elementor data array.
	 *
	 * Handles three cases:
	 *  - Elementor media objects: arrays with a 'url' key (background_image, image, etc.)
	 *    → updates both 'url' and 'id' so Elementor does not regenerate the old URL from the stale ID.
	 *  - Exact-URL string values.
	 *  - URLs embedded inside CSS strings (e.g. custom CSS fields).
	 */
	private function pagemorph_replace_media_recursive(&$data, $url_remap, $id_remap)
	{
		if (!is_array($data)) {
			return;
		}
		foreach ($data as $key => &$value) {
			if (is_array($value)) {
				// Update Elementor media object in-place, then recurse for any nested structure.
				if (isset($value['url']) && is_string($value['url']) && isset($url_remap[$value['url']])) {
					$old_url = $value['url'];
					$value['url'] = $url_remap[$old_url];
					if (array_key_exists('id', $value) && isset($id_remap[$old_url])) {
						$value['id'] = $id_remap[$old_url];
					}
				}
				$this->pagemorph_replace_media_recursive($value, $url_remap, $id_remap);
			} elseif (is_string($value)) {
				if (isset($url_remap[$value])) {
					$value = $url_remap[$value];
				} else {
					// Replace URLs embedded inside CSS strings or other compound values.
					foreach ($url_remap as $old_url => $new_url) {
						if (strpos($value, $old_url) !== false) {
							$value = str_replace($old_url, $new_url, $value);
						}
					}
				}
			}
		}
	}

	/**
	 * Helper function to match existing attachments by text slug.
	 */
	private function pagemorph_get_attachment_id_by_filename($filename)
	{
		$filename = sanitize_file_name($filename);

		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_wp_attached_file',
					'value' => $filename,
					'compare' => 'LIKE',
				),
			),
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$attachments = get_posts($args);

		if (!empty($attachments)) {
			return intval($attachments[0]);
		}

		return false;
	}

}

new PageMorph_Builder_Migrator();
