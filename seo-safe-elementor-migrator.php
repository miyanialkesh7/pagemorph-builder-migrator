<?php
/**
 * Plugin Name:       SEO-Safe Elementor Migrator
 * Plugin URI:        https://miyanialkesh7.com
 * Description:       Securely pulls Elementor layouts from a staging site into an existing live page built with WPBakery via the REST API. Automatically deactivates WPBakery data on the page, purges legacy shortcodes, sideloads media, and preserves RankMath SEO metadata completely untouched.
 * Version:           1.0.0
 * Author:            Alkesh Miyani
 * Author URI:        https://miyanialkesh7.com
 * Text Domain:       seo-safe-elementor-migrator
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class SEOSafe_Elementor_Migrator
{

	public function __construct()
	{
		add_action('plugins_loaded', array($this, 'seosafe_load_textdomain'));
		add_action('add_meta_boxes', array($this, 'seosafe_register_sync_meta_box'));
		add_action('admin_enqueue_scripts', array($this, 'seosafe_enqueue_admin_assets'));
		add_action('wp_ajax_seosafe_pull_content', array($this, 'seosafe_ajax_handle_pull_content'));
	}

	/**
	 * Load translation files.
	 */
	public function seosafe_load_textdomain()
	{
		load_plugin_textdomain('seo-safe-elementor-migrator', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Register the Sidebar Meta Box on Pages.
	 */
	public function seosafe_register_sync_meta_box()
	{
		add_meta_box(
			'seosafe_pull_box',
			__('Staging Content Sync (SEO-Safe)', 'seo-safe-elementor-migrator'),
			array($this, 'seosafe_render_meta_box_html'),
			'page',
			'side',
			'high'
		);
	}

	/**
	 * Render Meta Box Controls.
	 */
	public function seosafe_render_meta_box_html($post)
	{
		wp_nonce_field('seosafe_pull_nonce_action', 'seosafe_pull_nonce');
		?>
		<div class="seosafe-meta-box-wrapper">
			<p style="color: #d63638; font-weight: bold; font-size: 12px; border-left: 3px solid #d63638; padding-left: 5px;">
				<?php esc_html_e('Ideal for moving from WPBakery to Elementor without losing RankMath data.', 'seo-safe-elementor-migrator'); ?>
			</p>
			<p>
				<label
					for="seosafe_staging_url"><strong><?php esc_html_e('Staging Site URL:', 'seo-safe-elementor-migrator'); ?></strong></label>
				<input type="url" id="seosafe_staging_url" class="large-text" placeholder="https://example.com" value="" />
			</p>
			<p>
				<label
					for="seosafe_app_username"><strong><?php esc_html_e('Staging Admin Username:', 'seo-safe-elementor-migrator'); ?></strong></label>
				<input type="text" id="seosafe_app_username" class="large-text" placeholder="admin" value="" />
			</p>
			<p>
				<label
					for="seosafe_app_password"><strong><?php esc_html_e('Staging Application Password:', 'seo-safe-elementor-migrator'); ?></strong></label>
				<input type="password" id="seosafe_app_password" class="large-text"
					placeholder="<?php esc_attr_e('xxxx xxxx xxxx xxxx', 'seo-safe-elementor-migrator'); ?>" value="" />
				<span
					class="description"><?php esc_html_e('Generated via Staging Profile > Application Passwords.', 'seo-safe-elementor-migrator'); ?></span>
			</p>
			<p>
				<label
					for="seosafe_staging_post_id"><strong><?php esc_html_e('Staging Page ID (Elementor):', 'seo-safe-elementor-migrator'); ?></strong></label>
				<input type="number" id="seosafe_staging_post_id" class="all-options" min="1" placeholder="45" value="" />
			</p>
			<hr />
			<button type="button" id="seosafe_pull_btn" class="button button-primary button-large"
				style="width:100%; text-align:center;">
				<?php esc_html_e('Migrate Layout & Clear WPBakery', 'seo-safe-elementor-migrator'); ?>
			</button>
			<div id="seosafe_status_message" style="margin-top:10px; font-weight:bold;"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue inline AJAX script.
	 */
	public function seosafe_enqueue_admin_assets($hook)
	{
		if ('post.php' !== $hook && 'post-new.php' !== $hook) {
			return;
		}

		global $post;
		if (!$post || 'page' !== $post->post_type) {
			return;
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				$('#seosafe_pull_btn').on('click', function (e) {
					e.preventDefault();

					var stagingUrl = $('#seosafe_staging_url').val();
					var appUsername = $('#seosafe_app_username').val();
					var appPassword = $('#seosafe_app_password').val();
					var stagingPostId = $('#seosafe_staging_post_id').val();
					var localPostId = '<?php echo absint($post->ID); ?>';
					var nonce = $('#seosafe_pull_nonce').val();

					if (!stagingUrl || !appUsername || !appPassword || !stagingPostId) {
						alert('<?php echo esc_js(__('Please fill out all staging credentials and the Page ID.', 'seo-safe-elementor-migrator')); ?>');
						return;
					}

					if (!confirm('<?php echo esc_js(__('Warning: This will overwrite your live layout, erase WPBakery settings for this page, and apply the Elementor design. RankMath SEO metadata is safely preserved. Proceed?', 'seo-safe-elementor-migrator')); ?>')) {
						return;
					}

					var $btn = $(this);
					var $status = $('#seosafe_status_message');

					$btn.prop('disabled', true).text('<?php echo esc_js(__('Purging WPBakery & Overwriting...', 'seo-safe-elementor-migrator')); ?>');
					$status.css('color', '#333').text('<?php echo esc_js(__('Connecting to staging site API...', 'seo-safe-elementor-migrator')); ?>');

					wp.ajax.post('seosafe_pull_content', {
						staging_url: stagingUrl,
						app_username: appUsername,
						app_password: appPassword,
						staging_id: stagingPostId,
						local_id: localPostId,
						_ajax_nonce: nonce
					})
						.done(function (response) {
							$status.css('color', 'green').text(response.message);
							setTimeout(function () {
								location.reload();
							}, 1500);
						})
						.fail(function (response) {
							$btn.prop('disabled', false).text('<?php echo esc_js(__('Migrate Layout & Clear WPBakery', 'seo-safe-elementor-migrator')); ?>');
							var errMsg = response && response.message ? response.message : '<?php echo esc_js(__('An unknown execution error occurred.', 'seo-safe-elementor-migrator')); ?>';
							$status.css('color', 'red').text('<?php echo esc_js(__('Error: ', 'seo-safe-elementor-migrator')); ?>' + errMsg);
						});
				});
			});
		</script>
		<?php
	}

	/**
	 * Main AJAX execution method.
	 */
	public function seosafe_ajax_handle_pull_content()
	{
		check_ajax_referer('seosafe_pull_nonce_action', '_ajax_nonce');

		if (!current_user_can('edit_pages')) {
			wp_send_json_error(array('message' => __('Insufficient permissions to perform this action.', 'seo-safe-elementor-migrator')));
		}

		$staging_url = esc_url_raw(wp_unslash($_POST['staging_url'] ?? ''));
		$username = sanitize_text_field(wp_unslash($_POST['app_username'] ?? ''));
		$app_password = sanitize_text_field(wp_unslash($_POST['app_password'] ?? ''));
		$staging_id = absint(wp_unslash($_POST['staging_id'] ?? 0));
		$local_id = absint(wp_unslash($_POST['local_id'] ?? 0));

		if (!$staging_url || !$username || !$app_password || !$staging_id || !$local_id) {
			wp_send_json_error(array('message' => __('Missing parameters.', 'seo-safe-elementor-migrator')));
		}

		$api_url = trailingslashit($staging_url) . 'wp-json/wp/v2/pages/' . $staging_id . '?_fields=meta,content&context=edit';

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode($username . ':' . $app_password),
			),
			'timeout' => 45,
		);

		$response = wp_remote_get($api_url, $args);

		if (is_wp_error($response)) {
			/* translators: %s: Staging API error message details. */
			wp_send_json_error(array('message' => sprintf(__('Staging API request failed: %s', 'seo-safe-elementor-migrator'), $response->get_error_message())));
		}

		$status_code = wp_remote_retrieve_response_code($response);
		if (200 !== $status_code) {
			/* translators: %d: HTTP status code. */
			wp_send_json_error(array('message' => sprintf(__('Staging API returned status code %d. Verify your credentials and Page ID.', 'seo-safe-elementor-migrator'), $status_code)));
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($data) || !isset($data['meta']['_elementor_data'])) {
			wp_send_json_error(array('message' => __('Failed to fetch valid meta records from staging.', 'seo-safe-elementor-migrator')));
		}

		$meta_payload = $data['meta'];
		$elementor_data = $meta_payload['_elementor_data'] ?? '';

		if (empty($elementor_data)) {
			wp_send_json_error(array('message' => __('Target staging page does not contain any Elementor Layout data.', 'seo-safe-elementor-migrator')));
		}

		// Process image sideloading and update URLs inside the layout JSON string
		$updated_elementor_data = $this->seosafe_sideload_layout_media($elementor_data, $staging_url);

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
			'ID'           => $local_id,
			'post_content' => $staging_content,
		));

		wp_send_json_success(array('message' => __('Success! WPBakery data cleared, new Elementor layout synced, and RankMath safely preserved. Reloading...', 'seo-safe-elementor-migrator')));
	}

	/**
	 * Parse JSON string, lookup remote staging domains, sideload image items, and alter URLs.
	 */
	private function seosafe_sideload_layout_media($elementor_json_string, $staging_url)
	{
		// It's safer to decode, traverse, and re-encode than to use regex on a JSON string.
		$elementor_data = json_decode($elementor_json_string, true);
		if (!is_array($elementor_data)) {
			// Fallback to original string if JSON is invalid
			return $elementor_json_string;
		}

		// Identify all asset URLs belonging to the staging environment uploads zone
		$staging_domain = wp_parse_url($staging_url, PHP_URL_HOST);

		// Match files inside wp-content/uploads/
		preg_match_all('/https?:\/\/' . preg_quote($staging_domain, '/') . '[^\s"\']+\.(?:jpg|jpeg|png|gif|svg|webp)/i', $elementor_json_string, $matches);

		if (empty($matches[0])) {
			return $elementor_json_string;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$unique_urls = array_unique($matches[0]);
		$url_remap = array();

		foreach ($unique_urls as $remote_url) {
			$filename = basename(wp_parse_url($remote_url, PHP_URL_PATH));
			$local_id = $this->seosafe_get_attachment_id_by_filename($filename);

			if (!$local_id) {
				$local_img_url = media_sideload_image($remote_url, 0, null, 'src');
				if (!is_wp_error($local_img_url)) {
					$url_remap[$remote_url] = $local_img_url;
				}
			} else {
				$url_remap[$remote_url] = wp_get_attachment_url($local_id);
			}
		}

		if (empty($url_remap)) {
			return $elementor_json_string;
		}

		// Safely replace URLs by walking the data structure.
		array_walk_recursive(
			$elementor_data,
			function (&$value) use ($url_remap) {
				if (is_string($value) && isset($url_remap[$value])) { // Check if the string is a URL we need to replace.
					$value = $url_remap[$value];
				}
			}
		);

		return wp_json_encode($elementor_data);
	}

	/**
	 * Helper function to match existing attachments by text slug.
	 */
	private function seosafe_get_attachment_id_by_filename($filename)
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

new SEOSafe_Elementor_Migrator();
