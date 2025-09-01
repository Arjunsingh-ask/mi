<?php
/**
 * Plugin Name: AI SEO Agent
 * Description: AI-powered Yoast-style SEO plugin for WordPress. Includes auto content optimization, schema, sitemap, and more.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: ai-seo-agent
 */

if (!defined('ABSPATH')) exit;

define('AI_SEO_AGENT_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_AGENT_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'AISEO_') !== false) {
        $file = AI_SEO_AGENT_PATH . 'includes/class-' . strtolower(str_replace('AISEO_', '', $class)) . '.php';
        if (file_exists($file)) require $file;
    }
});

// Activation
function aiseo_activate() {
    AISEO_SitemapManager::generate_sitemap();
    add_option('aiseo_settings', [
        'schemas' => ['article'=>1,'product'=>1,'faq'=>1,'geo'=>1,'ai_overview'=>1],
        'sitemap' => 1,
    ]);
}
register_activation_hook(__FILE__, 'aiseo_activate');

// Settings Registration
add_action('admin_init', function(){
    register_setting('aiseo_settings_group', 'aiseo_settings');
    register_setting('aiseo_settings_group', 'aiseo_openai_key', ['sanitize_callback'=>'sanitize_text_field']);
});

// Init
function aiseo_init() {
    new AISEO_Admin();
    new AISEO_Frontend();
    new AISEO_Optimizer();
    new AISEO_SchemaManager();
    new AISEO_SitemapManager();
    new AISEO_AI();
}
add_action('plugins_loaded', 'aiseo_init');
