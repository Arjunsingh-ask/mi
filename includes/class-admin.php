<?php
if (!defined('ABSPATH')) exit;

class AISEO_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('add_meta_boxes', [$this, 'meta_box']);
        add_action('save_post', [$this, 'save_post'], 10, 2);
        add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);
    }

    public function menu() {
        add_menu_page(
            'SEO Agent',
            'SEO Agent',
            'manage_options',
            'aiseo-settings',
            [$this, 'settings_page'],
            'dashicons-chart-line',
            59
        );
    }

    public function register_settings() {
        register_setting('aiseo_group', 'aiseo_settings');
        register_setting('aiseo_group', 'aiseo_openai_key', ['sanitize_callback'=>'sanitize_text_field']);
    }

    public function assets($hook) {
        if ($hook === 'toplevel_page_aiseo-settings' || in_array($hook, ['post.php','post-new.php'], true)) {
            wp_enqueue_style('aiseo-admin', AI_SEO_AGENT_URL.'assets/admin.css', [], '1.0.2');
            wp_enqueue_script('aiseo-admin', AI_SEO_AGENT_URL.'assets/admin.js', ['jquery'], '1.0.2', true);
            wp_localize_script('aiseo-admin','aiseo_ai',[
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiseo_ai')
            ]);
        }
    }

    public function settings_page() {
        $s = get_option('aiseo_settings', []);
        $key = get_option('aiseo_openai_key','');
        ?>
        <div class="wrap">
            <h1>AI SEO Agent</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aiseo_group'); ?>
                <h2>AI Settings</h2>
                <p><input type="password" name="aiseo_openai_key" style="width:420px" value="<?php echo esc_attr($key); ?>" placeholder="OpenAI API Key"></p>

                <h2>Schema</h2>
                <?php foreach (['article','product','faq','geo','ai_overview'] as $k): ?>
                    <label><input type="checkbox" name="aiseo_settings[schemas][<?php echo $k; ?>]" value="1" <?php checked(!empty($s['schemas'][$k])); ?>> <?php echo esc_html(ucfirst(str_replace('_',' ',$k))); ?></label><br>
                <?php endforeach; ?>

                <h2>Sitemap</h2>
                <label><input type="checkbox" name="aiseo_settings[sitemap]" value="1" <?php checked(!empty($s['sitemap'])); ?>> Enable sitemap (served at /sitemap.xml)</label>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr/>
            <h2>Bulk AI Optimize</h2>
            <p><button class="button button-primary" id="aiseo-bulk-optimize">⚡ Bulk Optimize All Posts</button></p>
            <div id="aiseo-bulk-log"></div>
        </div>
        <?php
    }

    public function meta_box() {
        add_meta_box('aiseo_meta','AI SEO Agent',[$this,'render_meta'],['post','page'],'side','high');
    }

    public function render_meta($post) {
        $focus = get_post_meta($post->ID, '_aiseo_focus_keyword', true);
        wp_nonce_field('aiseo_save', 'aiseo_nonce');
        ?>
        <p><input type="text" name="aiseo_focus_keyword" value="<?php echo esc_attr($focus); ?>" placeholder="Focus keyword" style="width:100%"></p>
        <p><button type="button" class="button button-primary aiseo-ai-optimize">✨ Auto Optimize with AI</button></p>
        <div id="aiseo-ai-output"></div>
        <?php
    }

    public function save_post($post_id, $post) {
        if (!isset($_POST['aiseo_nonce']) || !wp_verify_nonce($_POST['aiseo_nonce'], 'aiseo_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['aiseo_focus_keyword'])) {
            update_post_meta($post_id, '_aiseo_focus_keyword', sanitize_text_field($_POST['aiseo_focus_keyword']));
        }
    }

    public function dashboard_widget() {
        wp_add_dashboard_widget('aiseo_dashboard','AI SEO Health', [$this,'render_dashboard']);
    }

    public function render_dashboard() {
        $settings = get_option('aiseo_settings', []);
        $last = get_option('aiseo_last_ai_run','Never');
        $schemas = implode(', ', array_keys(array_filter($settings['schemas'] ?? [])));
        echo '<ul>';
        echo '<li>📄 Sitemap: '.(!empty($settings['sitemap']) ? 'Enabled ✅' : 'Disabled ❌').'</li>';
        echo '<li>📊 Schema: '.($schemas ?: 'None').'</li>';
        echo '<li>🤖 Last AI Optimization: '.esc_html($last).'</li>';
        echo '</ul>';
        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=aiseo-settings')).'">Open SEO Agent</a></p>';
    }
}
