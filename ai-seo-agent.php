<?php
/**
 * Plugin Name: AI SEO Agent (Pages Only)
 * Description: Pages-first SEO with an all-in-one Dashboard: AI content generation, page optimization, internal linking, link-building tracker, schema & sitemap.
 * Version: 1.2.0
 * Author: Your Name
 * Text Domain: ai-seo-agent
 */

if (!defined('ABSPATH')) exit;

define('AI_SEO_AGENT_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_AGENT_URL',  plugin_dir_url(__FILE__));

require_once AI_SEO_AGENT_PATH.'includes/class-admin.php';
require_once AI_SEO_AGENT_PATH.'includes/class-frontend.php';
require_once AI_SEO_AGENT_PATH.'includes/class-optimizer.php';
require_once AI_SEO_AGENT_PATH.'includes/class-schema-manager.php';
require_once AI_SEO_AGENT_PATH.'includes/class-sitemap-manager.php';
require_once AI_SEO_AGENT_PATH.'includes/class-ai.php';

function aiseo_activate_pages_only() {
    if (!get_option('aiseo_settings')) {
        add_option('aiseo_settings', [
            'target_type' => 'page',
            'schemas' => ['webpage'=>1,'faq'=>1,'geo'=>0,'product'=>0,'article'=>0,'ai_overview'=>1],
            'sitemap' => 1,
        ]);
    }
    update_option('aiseo_build_sitemap_next_boot', 1);
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'aiseo_activate_pages_only');

add_action('plugins_loaded', function() {
    new AISEO_Admin();
    new AISEO_Frontend();
    new AISEO_Optimizer();
    new AISEO_SchemaManager();
    new AISEO_SitemapManager();
    new AISEO_AI();
});

add_action('init', function () {
    if (get_option('aiseo_build_sitemap_next_boot')) {
        if (class_exists('AISEO_SitemapManager')) {
            AISEO_SitemapManager::generate_sitemap(); // pages only
        }
        delete_option('aiseo_build_sitemap_next_boot');
        flush_rewrite_rules(false);
    }
});
