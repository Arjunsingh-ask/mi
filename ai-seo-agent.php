<?php
/**
 * Plugin Name: AI SEO Agent
 * Description: Yoast-style SEO with AI. Safe activation, admin menu, AI optimize, schema, sitemap.
 * Version: 1.0.2
 * Author: Your Name
 * Text Domain: ai-seo-agent
 */

if (!defined('ABSPATH')) exit;

define('AI_SEO_AGENT_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_AGENT_URL', plugin_dir_url(__FILE__));

/** Load classes with explicit requires (no autoloader to avoid path issues) */
require_once AI_SEO_AGENT_PATH.'includes/class-admin.php';
require_once AI_SEO_AGENT_PATH.'includes/class-frontend.php';
require_once AI_SEO_AGENT_PATH.'includes/class-optimizer.php';
require_once AI_SEO_AGENT_PATH.'includes/class-schema-manager.php';
require_once AI_SEO_AGENT_PATH.'includes/class-sitemap-manager.php';
require_once AI_SEO_AGENT_PATH.'includes/class-ai.php';

/** Activation: set defaults, schedule safe sitemap build, flush rewrites */
function aiseo_activate() {
    if (!get_option('aiseo_settings')) {
        add_option('aiseo_settings', [
            'schemas' => ['article'=>1,'product'=>1,'faq'=>1,'geo'=>1,'ai_overview'=>1],
            'sitemap' => 1,
        ]);
    }
    // flag to (re)build sitemap after WordPress is fully loaded
    update_option('aiseo_build_sitemap_next_boot', 1);
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'aiseo_activate');

/** Bootstrap all modules */
add_action('plugins_loaded', function () {
    new AISEO_Admin();
    new AISEO_Frontend();
    new AISEO_Optimizer();
    new AISEO_SchemaManager();
    new AISEO_SitemapManager();
    new AISEO_AI();
});

/** Do deferred sitemap build (never during activation) */
add_action('init', function () {
    if (get_option('aiseo_build_sitemap_next_boot')) {
        if (class_exists('AISEO_SitemapManager')) {
            AISEO_SitemapManager::generate_sitemap();
        }
        delete_option('aiseo_build_sitemap_next_boot');
        flush_rewrite_rules(false);
    }
});
