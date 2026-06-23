=== SEO-Safe Elementor Migrator ===
Contributors: alkesh7,techeshta, seljabhalala
Donate link: https://miyanialkesh7.com
Tags: elementor, rankmath, staging migration, page sync, elementor clone
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://gnu.org

Sync Elementor page layouts from staging to live sites. Safely keep RankMath SEO metadata, titles, and schemas while sideloading layout images.

== Description ==

**SEO-Safe Elementor Migrator** is a lightweight, secure developer tool built for WordPress designers, SEO specialists, and developers. 

Do you build and refine your designs on a staging environment while keeping your live site updated with active blogs, customer comments, and optimized RankMath SEO configurations? Standard WordPress migration plugins or manual exports overwrite the entire page metadata, destroying your hard-earned SEO rankings, custom schemas, focus keywords, and meta descriptions on the live site.

This plugin bridges that gap. By utilizing a secure, authenticated WordPress REST API pipeline, it pulls *only* the Elementor page layout data (`_elementor_data`) from your staging site to your live site. It automatically imports (sideloads) all referenced images into your live Media Library, updates layout URLs, and leaves your **RankMath SEO data completely untouched**.

### Key Features
* **Zero SEO Data Loss (RankMath Safe):** Completely preserves `_rank_math_` postmeta records, focus keywords, meta titles, meta descriptions, and custom schema configurations on the destination page.
* **Instant Staging-to-Live Sync:** Directly pull layout designs between environments on a page-by-page basis using standard WordPress Application Passwords.
* **Automatic Media Sideloading:** Automatically detects remote images (JPEG, PNG, WebP, SVG, GIF) from the staging layout, downloads them to the live site's media library, and remaps attachment URLs.
* **Clean WPBakery Migration:** Easily switch from WPBakery page builder or Classic Editor to Elementor. The plugin purges legacy shortcodes from the main content area while keeping the page layout clean and SEO configurations intact.
* **Automatic Page Backups:** Automatically generates a native WordPress post revision backup before applying the new layout, giving you a safe undo point.
* **Secure and Lightweight:** No bloat, no constant background processes, and runs entirely via core WordPress REST API actions.

== Installation ==

1. Upload the entire `seo-safe-elementor-migrator` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress Admin dashboard (**Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Ensure that **Elementor** is active on both the staging and live environments.

== Frequently Asked Questions ==

= Does this plugin delete my existing content before syncing? =
It overwrites the active Elementor or WPBakery layout workspace on that specific page and flushes the main `post_content` row. However, a native WordPress page revision is created right before this happens so you can easily undo changes if needed.

= How does this protect my RankMath SEO settings? =
When you trigger the migration, the plugin fetches the Elementor layout JSON and replaces the live page's `_elementor_data` and layout options. It does not modify or overwrite any database keys prefixed with `_rank_math_`, ensuring all your SEO settings, custom schemas, and page optimization scores remain identical.

= Does it affect my global Elementor styles? =
No. This plugin is targeted specifically to individual page-level content metadata strings. Global fonts, colors, and site-wide theme builder settings remain unmodified.

= How do I authenticate with my staging environment? =
The plugin utilizes the core WordPress REST API infrastructure. You simply generate an **Application Password** from the Staging site under **Users > Profile** and input it directly into the live page sidebar interface.

= Does this import remote images to my live website? =
Yes. The plugin parses the staging layout JSON for any images residing on the staging domain. It checks if the image already exists in the live Media Library by filename. If not, it downloads the media using native WordPress sideloading functions and replaces the remote URL in the layout JSON.

= Can I revert the sync if something goes wrong? =
Yes. Before overwriting the live page, the plugin triggers a native WordPress post revision. You can roll back to the previous state at any time by viewing the Revisions section in the WordPress page editor.

= How do I configure staging authentication? =
You do not need to install this plugin on your staging site. Simply go to your Staging site, navigate to **Users > Profile**, scroll down to **Application Passwords**, generate a new password, and paste it alongside your staging username and page ID into the sidebar panel of your live WordPress page editor.

== Screenshots ==

1. The intuitive sidebar control panel added to the WordPress page editor.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Added Page-to-ID REST API synchronization interface.
* Implemented selective Elementor metadata migration.
* Implemented automated media library sideloading for remote layout images.
* Implemented automated WordPress page revision backup prior to synchronization.
* Added clean-up support for WPBakery/Classic Editor shortcodes.

== Upgrade Notice ==

= 1.0.0 =
First production-ready version. No upgrades required.
