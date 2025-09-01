<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles admin UI, menu, settings, dashboard widgets, and one-click actions.
 */
class AI_SEO_Agent_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'dashboard_widgets' ] );
        add_action( 'admin_post_ai_seo_agent_action', [ __CLASS__, 'handle_admin_actions' ] );
    }

    public static function admin_menu() {
        add_menu_page(
            __( 'SEO Agent', 'ai-seo-agent' ),
            __( 'SEO Agent', 'ai-seo-agent' ),
            'manage_options',
            'ai-seo-agent',
            [ __CLASS__, 'settings_page' ],
            'dashicons-search',
            65
        );
        add_submenu_page(
            'ai-seo-agent',
            __( 'Bulk Content Optimizer', 'ai-seo-agent' ),
            __( 'Bulk Content', 'ai-seo-agent' ),
            'edit_posts',
            'ai-seo-bulk',
            [ __CLASS__, 'bulk_optimizer_page' ]
        );
    }

    public static function register_settings() {
        // Register all settings sections/tabs
        register_setting( 'ai_seo_agent_settings', 'ai_seo_agent_settings', [ __CLASS__, 'sanitize_settings' ] );

        // Add sections/fields via Settings API
        // General, Content, Schema, Sitemap, Advanced
        // ... Add code for add_settings_section/field here ...
    }

    public static function sanitize_settings( $input ) {
        // Sanitize all settings fields
        if (!is_array($input)) return [];
        $clean = [];
        $clean['site_title'] = sanitize_text_field( $input['site_title'] ?? '' );
        $clean['meta_defaults'] = sanitize_text_field( $input['meta_defaults'] ?? '' );
        $clean['social_meta'] = sanitize_textarea_field( $input['social_meta'] ?? '' );
        $clean['schema_types'] = array_map( 'sanitize_text_field', $input['schema_types'] ?? [] );
        $clean['sitemap_enabled'] = isset( $input['sitemap_enabled'] ) ? 1 : 0;
        $clean['sitemap_post_types'] = array_map( 'sanitize_text_field', $input['sitemap_post_types'] ?? [] );
        $clean['robots_txt'] = sanitize_textarea_field( $input['robots_txt'] ?? '' );
        $clean['canonical_url'] = esc_url_raw( $input['canonical_url'] ?? '' );
        // ... more fields as needed
        return $clean;
    }

    public static function settings_page() {
        ?>
        <div class="wrap ai-seo-agent-settings">
            <h1><?php esc_html_e('AI SEO Agent Settings', 'ai-seo-agent'); ?></h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ai_seo_agent_settings' );
                do_settings_sections( 'ai-seo-agent' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function bulk_optimizer_page() {
        // Bulk content optimization interface
        ?>
        <div class="wrap ai-seo-agent-bulk">
            <h1><?php esc_html_e('Bulk Content Optimizer', 'ai-seo-agent'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('ai_seo_bulk_optimize', 'ai_seo_bulk_nonce'); ?>
                <textarea name="bulk_content" rows="10" style="width: 100%;"></textarea>
                <button type="submit" class="button button-primary"><?php esc_html_e('Optimize Content', 'ai-seo-agent'); ?></button>
            </form>
            <?php
            if ( isset($_POST['bulk_content']) && check_admin_referer('ai_seo_bulk_optimize', 'ai_seo_bulk_nonce') ) {
                $optimized = AI_SEO_Agent_Optimizer::bulk_optimize( sanitize_textarea_field($_POST['bulk_content']) );
                echo '<h3>'.esc_html__('Optimized Result:', 'ai-seo-agent').'</h3>';
                echo '<textarea rows="10" style="width:100%;">' . esc_textarea($optimized) . '</textarea>';
            }
            ?>
        </div>
        <?php
    }

    public static function dashboard_widgets() {
        wp_add_dashboard_widget(
            'ai_seo_agent_site_health',
            __( 'SEO Agent: Site SEO Health', 'ai-seo-agent' ),
            [ __CLASS__, 'dashboard_widget_content' ]
        );
    }

    public static function dashboard_widget_content() {
        $status = AI_SEO_Agent_Optimizer::site_health_check();
        ?>
        <ul class="ai-seo-agent-health">
            <?php foreach ( $status as $item ): ?>
                <li><?php echo esc_html( $item ); ?></li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field('ai_seo_agent_action', 'ai_seo_agent_nonce'); ?>
            <input type="hidden" name="action" value="ai_seo_agent_action" />
            <button class="button" name="agent_btn" value="optimize"><?php esc_html_e('Optimize Content', 'ai-seo-agent'); ?></button>
            <button class="button" name="agent_btn" value="schema"><?php esc_html_e('Generate Schema', 'ai-seo-agent'); ?></button>
            <button class="button" name="agent_btn" value="url"><?php esc_html_e('Update URL', 'ai-seo-agent'); ?></button>
            <button class="button" name="agent_btn" value="sitemap"><?php esc_html_e('Rebuild + Submit Sitemap', 'ai-seo-agent'); ?></button>
        </form>
        <?php
    }

    public static function handle_admin_actions() {
        if ( ! current_user_can('manage_options') || ! isset( $_POST['ai_seo_agent_nonce'] ) || ! wp_verify_nonce( $_POST['ai_seo_agent_nonce'], 'ai_seo_agent_action' ) ) {
            wp_die( __( 'Unauthorized request.', 'ai-seo-agent' ) );
        }
        $btn = sanitize_text_field( $_POST['agent_btn'] ?? '' );
        switch ( $btn ) {
            case 'optimize':
                AI_SEO_Agent_Optimizer::bulk_optimize_all();
                break;
            case 'schema':
                AI_SEO_Agent_SchemaManager::generate_all_schema();
                break;
            case 'url':
                AI_SEO_Agent_Optimizer::bulk_update_urls();
                break;
            case 'sitemap':
                AI_SEO_Agent_SitemapManager::rebuild_and_ping();
                break;
        }
        wp_redirect( admin_url('index.php?ai-seo-msg=action-completed') );
        exit;
    }

    public static function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'ai-seo-agent' ) === false ) return;
        wp_enqueue_style( 'ai-seo-agent-admin', AI_SEO_AGENT_URL . 'assets/admin.css', [], AI_SEO_AGENT_VERSION );
        wp_enqueue_script( 'ai-seo-agent-admin', AI_SEO_AGENT_URL . 'assets/admin.js', [ 'jquery' ], AI_SEO_AGENT_VERSION, true );
        wp_localize_script( 'ai-seo-agent-admin', 'AISEOAgent', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ai_seo_agent_nonce' )
        ]);
    }

    public static function set_default_settings() {
        $defaults = [
            'site_title' => get_bloginfo('name'),
            'meta_defaults' => '',
            'social_meta' => '',
            'schema_types' => [ 'article', 'faq', 'geo', 'ai_overview' ],
            'sitemap_enabled' => 1,
            'sitemap_post_types' => [ 'post', 'page' ],
            'robots_txt' => "User-agent: *\nDisallow:",
            'canonical_url' => home_url(),
        ];
        add_option( 'ai_seo_agent_settings', $defaults );
    }
}
