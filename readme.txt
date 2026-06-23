=== SEO-Safe Elementor Migrator ===
Contributors: alkesh7,techeshta, seljabhalala
Donate link: https://miyanialkesh7.com
Tags: elementor, rankmath, staging migration, page sync, elementor clone
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://gnu.org

Migrate Elementor layouts from staging to live sites seamlessly without losing your RankMath SEO configuration, metadata, or media file attachments.

== Description ==

**SEO-Safe Elementor Migrator** is a lightweight, secure developer tool designed to bridge the gap between design environments and production sites. If you design your pages on a staging platform but manage active keywords, custom schemas, and structured snippets on your live site, typical migration tools will overwrite and destroy your production SEO data.

This plugin creates a secure backend REST API pipeline from your Live site to your Staging site. It isolates and pulls *only* the required Elementor template payload (`_elementor_data`) and page template meta parameters, automatically sideloads referenced remote media assets into your local library, rewrites content URLs on the fly, and keeps your **RankMath SEO database rows completely untouched**.

### Key Features
* **Zero SEO Data Loss:** Safely preserves all `_rank_math_` postmeta parameters, target keywords, and snippets.
* **Smart Media Sideloading:** Automatically parses layout images, imports them to the live Media Library, and remaps attachment handles.
* **Legacy Clean-up:** Purges conflicting raw WPBakery or Classic Editor shortcode text wrappers from the post content block during synchronization.
* **Fail-Safe Revamping:** Automatically triggers a native WordPress post revision backup before applying any data payload changes.
* **Translation-Ready:** Built natively with clear I18n text domains for immediate multi-language localization.

== Installation ==

1. Upload the entire `seo-safe-elementor-migrator` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress Admin dashboard (**Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Ensure both the Staging and Live sites have the **Elementor** plugin activated.

== Frequently Asked Questions ==

= Does this plugin delete my existing content before syncing? =
It overwrites the active Elementor or WPBakery layout workspace on that specific page and flushes the main `post_content` row. However, a native WordPress page revision is created right before this happens so you can easily undo changes if needed.

= Does it affect my global Elementor styles? =
No. This plugin is targeted specifically to individual page-level content metadata strings. Global fonts, colors, and site-wide theme builder settings remain unmodified.

= How do I authenticate with my staging environment? =
The plugin utilizes the core WordPress REST API infrastructure. You simply generate an **Application Password** from the Staging site under **Users > Profile** and input it directly into the live page sidebar interface.

== Screenshots ==

1. The clean sidebar control panel added natively to your standard WordPress page editor screen.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Automated Page-to-ID REST API pipeline wrapper.
* Selective `_elementor_data` preservation mapping engine.
* Regex asset capture and media library sideloading engine.

== Upgrade Notice ==

= 1.0.0 =
Initial production-ready deployment. No upgrades available.
