<?php
/**
 * Plugin Name: AI SEO Agent
 * Plugin URI:  https://github.com/Arjunsingh-ask/mi
 * Description: Advanced AI-powered SEO plugin for WordPress with full Yoast-like functionality, including content optimization, schema, sitemaps, and dashboard controls.
 * Version:     1.0.0
 * Author:      Arjunsingh-ask
 * Author URI:  https://github.com/Arjunsingh-ask
 * License:     GPL2
 * Text Domain: ai-seo-agent
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AI_SEO_AGENT_VERSION', '1.0.0' );
define( 'AI_SEO_AGENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_SEO_AGENT_URL', plugin_dir_url( __FILE__ ) );

require_once AI_SEO_AGENT_PATH . 'includes/class-ai-seo-agent-admin.php';
require_once AI_SEO_AGENT_PATH . 'includes/class-ai-seo-agent-optimizer.php';
require_once AI_SEO_AGENT_PATH . 'includes/class-ai-seo-agent-schema.php';
require_once AI_SEO_AGENT_PATH . 'includes/class-ai-seo-agent-sitemap.php';

// Load plugin textdomain
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'ai-seo-agent', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
});

// Activation hook: create sitemap and default settings
register_activation_hook( __FILE__, function() {
    AI_SEO_Agent_SitemapManager::activate_plugin();
    AI_SEO_Agent_Admin::set_default_settings();
});

// Initialize plugin
add_action( 'init', function() {
    AI_SEO_Agent_Admin::init();
    AI_SEO_Agent_Optimizer::init();
    AI_SEO_Agent_SchemaManager::init();
    AI_SEO_Agent_SitemapManager::init();
});

// Admin scripts/styles only
add_action( 'admin_enqueue_scripts', function( $hook ) {
    AI_SEO_Agent_Admin::admin_enqueue_scripts( $hook );
});
