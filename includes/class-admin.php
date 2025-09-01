<?php
if (!defined('ABSPATH')) exit;

class AISEO_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_post_data']);
    }

    public function register_menu() {
        add_menu_page('SEO Agent', 'SEO Agent', 'manage_options', 'aiseo-settings', [$this, 'render_settings'], 'dashicons-chart-line');
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'aiseo') !== false || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_style('aiseo-admin', AI_SEO_AGENT_URL . 'assets/admin.css');
            wp_enqueue_script('aiseo-admin', AI_SEO_AGENT_URL . 'assets/admin.js', ['jquery'], false, true);
            wp_localize_script('aiseo-admin','aiseo_ai',['nonce'=>wp_create_nonce('aiseo_ai_nonce'),'ajax'=>admin_url('admin-ajax.php')]);
        }
    }

    public function render_settings() {
        $settings = get_option('aiseo_settings', []);
        $api_key = get_option('aiseo_openai_key', '');
        ?>
        <div class="wrap">
            <h1>AI SEO Agent</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aiseo_settings_group'); ?>
                <h2>AI Settings</h2>
                <label>OpenAI API Key:</label>
                <input type="password" name="aiseo_openai_key" value="<?php echo esc_attr($api_key); ?>" style="width:400px" />
                <h2>Schema Settings</h2>
                <?php foreach (['article','product','faq','geo','ai_overview'] as $schema): ?>
                    <label><input type="checkbox" name="aiseo_settings[schemas][<?php echo $schema; ?>]" value="1" <?php checked(isset($settings['schemas'][$schema]) && $settings['schemas'][$schema]); ?>> Enable <?php echo ucfirst(str_replace('_',' ',$schema)); ?> Schema</label><br>
                <?php endforeach; ?>
                <h2>Sitemap</h2>
                <label><input type="checkbox" name="aiseo_settings[sitemap]" value="1" <?php checked(isset($settings['sitemap']) && $settings['sitemap']); ?>> Enable Sitemap</label>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Bulk Optimize</h2>
            <button id="aiseo-bulk-optimize" class="button button-primary">⚡ Bulk Optimize All Posts</button>
            <div id="aiseo-bulk-log"></div>
        </div>
        <?php
    }

    public function add_meta_box() {
        add_meta_box('aiseo_meta','AI SEO Agent',[$this,'render_meta_box'],['post','page'],'side');
    }

    public function render_meta_box($post) {
        wp_nonce_field('aiseo_save','aiseo_nonce');
        $focus = get_post_meta($post->ID,'_aiseo_focus_keyword',true);
        ?>
        <p><strong>Focus Keyword:</strong></p>
        <input type="text" name="aiseo_focus_keyword" value="<?php echo esc_attr($focus); ?>" style="width:100%" />
        <p><button type="button" class="button button-primary aiseo-ai-optimize">⚡ Auto Optimize with AI</button></p>
        <div id="aiseo-ai-output"></div>
        <?php
    }

    public function save_post_data($post_id) {
        if (!isset($_POST['aiseo_nonce']) || !wp_verify_nonce($_POST['aiseo_nonce'],'aiseo_save')) return;
        if (isset($_POST['aiseo_focus_keyword'])) {
            update_post_meta($post_id,'_aiseo_focus_keyword',sanitize_text_field($_POST['aiseo_focus_keyword']));
        }
    }
}
