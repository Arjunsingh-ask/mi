<?php
/**
 * Plugin Name: AI SEO Agent
 * Description: Pages-first SEO with AI content, bulk optimizer, schema & sitemap.
 * Version: 1.3.0
 * Author: Your Name
 * Text Domain: ai-seo-agent
 */

if (!defined('ABSPATH')) exit;

// 1) Constants
define('AI_SEO_AGENT_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_AGENT_URL',  plugin_dir_url(__FILE__));

// 2) Includes (classes must be loaded BEFORE bootstrap)
require_once AI_SEO_AGENT_PATH.'includes/class-admin.php';
require_once AI_SEO_AGENT_PATH.'includes/class-frontend.php';
require_once AI_SEO_AGENT_PATH.'includes/class-optimizer.php';
require_once AI_SEO_AGENT_PATH.'includes/class-schema-manager.php';
require_once AI_SEO_AGENT_PATH.'includes/class-sitemap-manager.php';
require_once AI_SEO_AGENT_PATH.'includes/class-ai.php';

// 3) Activation hook (optional)
function aiseo_activate_pages_only() {
    if (!get_option('aiseo_settings')) {
        add_option('aiseo_settings', [
            'target_type' => 'page',
            'schemas'     => ['webpage'=>1,'faq'=>1,'geo'=>0,'ai_overview'=>1],
            'sitemap'     => 1,
        ]);
    }
    update_option('aiseo_build_sitemap_next_boot', 1);
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'aiseo_activate_pages_only');

// 4) **BOOTSTRAP — add exactly here**
add_action('plugins_loaded', function () {
    if (class_exists('AISEO_Admin'))          new AISEO_Admin();
    if (class_exists('AISEO_Frontend'))       new AISEO_Frontend();
    if (class_exists('AISEO_Optimizer'))      new AISEO_Optimizer();
    if (class_exists('AISEO_SchemaManager'))  new AISEO_SchemaManager();
    if (class_exists('AISEO_SitemapManager')) new AISEO_SitemapManager();
    if (class_exists('AISEO_AI'))             new AISEO_AI();   // ← CRITICAL
});

// 5) Optional: deferred sitemap build on first run
add_action('init', function () {
    if (get_option('aiseo_build_sitemap_next_boot')) {
        if (class_exists('AISEO_SitemapManager')) {
            AISEO_SitemapManager::generate_sitemap();
        }
        delete_option('aiseo_build_sitemap_next_boot');
        flush_rewrite_rules(false);
    }
});
