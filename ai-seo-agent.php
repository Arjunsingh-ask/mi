<?php
/**
 * Plugin Name: AI SEO Agent
 * Description: 2025-ready, AI-driven SEO and AEO plugin. Optimizes for AI Overviews, schema, speed, E-E-A-T, local, intent, and more.
 * Version: 0.1.0
 * Author: Arjunsingh-ask
 * License: GPL2+
 * Text Domain: ai-seo-agent
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define constants
define( 'AISA_VERSION', '0.1.0' );
define( 'AISA_PATH', plugin_dir_path( __FILE__ ) );
define( 'AISA_URL', plugin_dir_url( __FILE__ ) );

// Autoload modules
require_once AISA_PATH . 'includes/class-ai-seo-agent-loader.php';

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'AISEO_Agent_Loader', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AISEO_Agent_Loader', 'deactivate' ] );

// Initialize plugin
add_action( 'plugins_loaded', [ 'AISEO_Agent_Loader', 'init' ] );