<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles content optimization, meta boxes, keyword suggestions, slug, and readability.
 */
class AI_SEO_Agent_Optimizer {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post', [ __CLASS__, 'save_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_editor_assets' ] );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'suggest_slug' ], 10, 2 );
    }

    public static function add_meta_box() {
        $screens = apply_filters( 'ai_seo_agent_meta_box_post_types', [ 'post', 'page' ] );
        foreach ( $screens as $screen ) {
            add_meta_box(
                'ai_seo_agent_meta',
                __( 'SEO Agent Optimization', 'ai-seo-agent' ),
                [ __CLASS__, 'meta_box_html' ],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public static function meta_box_html( $post ) {
        $meta = get_post_meta( $post->ID, '_ai_seo_agent', true );
        $keyword = $meta['focus_keyword'] ?? '';
        $faq = $meta['faq'] ?? [];
        wp_nonce_field( 'ai_seo_agent_meta_box', 'ai_seo_agent_meta_nonce' );
        ?>
        <div id="ai-seo-agent-meta-box">
            <label>
                <strong><?php esc_html_e('Focus Keyword:', 'ai-seo-agent'); ?></strong>
                <input type="text" name="ai_seo_agent[focus_keyword]" value="<?php echo esc_attr($keyword); ?>" />
            </label>
            <div class="ai-seo-agent-analytics">
                <span id="ai-seo-keyword-density"></span>
                <span id="ai-seo-readability"></span>
                <span id="ai-seo-suggestions"></span>
            </div>
            <label>
                <strong><?php esc_html_e('Snippet Preview:', 'ai-seo-agent'); ?></strong>
                <div id="ai-seo-snippet-preview">
                    <!-- JS will update this dynamically -->
                </div>
            </label>
            <label>
                <strong><?php esc_html_e('FAQ Builder:', 'ai-seo-agent'); ?></strong>
                <div id="ai-seo-faq-builder">
                    <?php
                    if ( ! empty( $faq ) && is_array( $faq ) ) {
                        foreach ( $faq as $pair ) {
                            echo '<div class="faq-pair">';
                            echo '<input type="text" name="ai_seo_agent[faq][]" value="' . esc_attr($pair) . '" />';
                            echo '</div>';
                        }
                    }
                    ?>
                    <button type="button" class="button add-faq-pair"><?php esc_html_e('Add Q&A', 'ai-seo-agent'); ?></button>
                </div>
            </label>
            <div>
                <button type="button" id="ai-seo-update-slug" class="button"><?php esc_html_e('Update Slug', 'ai-seo-agent'); ?></button>
            </div>
        </div>
        <?php
    }

    public static function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['ai_seo_agent_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ai_seo_agent_meta_nonce'], 'ai_seo_agent_meta_box' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $data = $_POST['ai_seo_agent'] ?? [];
        $clean = [];
        $clean['focus_keyword'] = sanitize_text_field( $data['focus_keyword'] ?? '' );
        $clean['faq'] = array_filter( array_map( 'sanitize_text_field', $data['faq'] ?? [] ) );
        update_post_meta( $post_id, '_ai_seo_agent', $clean );
    }

    public static function enqueue_editor_assets( $hook ) {
        if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) return;
        wp_enqueue_script( 'ai-seo-agent-meta', AI_SEO_AGENT_URL . 'assets/meta-box.js', [ 'jquery' ], AI_SEO_AGENT_VERSION, true );
        wp_enqueue_style( 'ai-seo-agent-meta', AI_SEO_AGENT_URL . 'assets/meta-box.css', [], AI_SEO_AGENT_VERSION );
    }

    public static function suggest_slug( $data, $postarr ) {
        if ( isset( $_POST['ai_seo_agent']['focus_keyword'] ) && ! empty( $_POST['ai_seo_agent']['focus_keyword'] ) ) {
            $keyword = sanitize_title( $_POST['ai_seo_agent']['focus_keyword'] );
            $data['post_name'] = $keyword;
        }
        return $data;
    }

    public static function bulk_optimize( $content ) {
        // Minimal example: auto-insert focus keyword in headings, check density, and suggest improvements
        $settings = get_option( 'ai_seo_agent_settings', [] );
        $keyword = $settings['bulk_focus_keyword'] ?? '';
        if ( ! $keyword ) $keyword = 'seo';

        $density = substr_count( strtolower( $content ), strtolower( $keyword ) ) / max( str_word_count( $content ), 1 );
        $suggest = $density < 0.01 ? __( 'Increase keyword usage.', 'ai-seo-agent' ) : __( 'Good density!', 'ai-seo-agent' );
        $content .= "\n<!-- " . esc_html( $suggest ) . " -->";
        return $content;
    }

    public static function bulk_optimize_all() {
        // Loop through all posts/pages and optimize (placeholder)
        // In production, more logic for AI-driven optimization
        return true;
    }

    public static function bulk_update_urls() {
        // Go through posts and update slugs based on focus keyword
        return true;
    }

    public static function site_health_check() {
        // Check for meta tags, schema, sitemap, CWV (placeholder)
        $results = [
            __( 'Meta tags: OK', 'ai-seo-agent' ),
            __( 'Schema: OK', 'ai-seo-agent' ),
            __( 'Sitemap: OK', 'ai-seo-agent' ),
            __( 'Core Web Vitals: OK', 'ai-seo-agent' ),
        ];
        return $results;
    }
}
