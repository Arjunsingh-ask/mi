<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles XML sitemap generation, updates, and pinging search engines.
 */
class AI_SEO_Agent_SitemapManager {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'maybe_output_sitemap' ] );
        add_action( 'save_post', [ __CLASS__, 'maybe_update_sitemap' ] );
    }

    public static function maybe_output_sitemap() {
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/sitemap.xml' ) !== false ) {
            self::output_sitemap();
            exit;
        }
    }

    public static function output_sitemap() {
        header( 'Content-Type: application/xml; charset=utf-8' );
        echo self::build_sitemap();
    }

    public static function build_sitemap() {
        $settings = get_option( 'ai_seo_agent_settings', [] );
        if ( empty( $settings['sitemap_enabled'] ) ) return '';
        $post_types = $settings['sitemap_post_types'] ?? [ 'post', 'page' ];

        $urls = [];
        foreach ( $post_types as $type ) {
            $query = new WP_Query( [
                'post_type' => $type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            foreach ( $query->posts as $id ) {
                $urls[] = [
                    'loc' => get_permalink( $id ),
                    'lastmod' => get_post_modified_time( 'c', true, $id ),
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ( $urls as $url ) {
            $xml .= '<url>';
            $xml .= '<loc>' . esc_url( $url['loc'] ) . '</loc>';
            $xml .= '<lastmod>' . esc_html( $url['lastmod'] ) . '</lastmod>';
            $xml .= '</url>';
        }
        $xml .= '</urlset>';
        return $xml;
    }

    public static function maybe_update_sitemap( $post_id ) {
        // Regenerate sitemap on content changes
        self::write_sitemap_to_file();
        self::ping_search_engines();
    }

    public static function write_sitemap_to_file() {
        $xml = self::build_sitemap();
        $file = ABSPATH . 'sitemap.xml';
        file_put_contents( $file, $xml );
    }

    public static function ping_search_engines() {
        $sitemap_url = home_url( '/sitemap.xml' );
        wp_remote_get( "https://www.google.com/ping?sitemap=" . urlencode( $sitemap_url ) );
        wp_remote_get( "https://www.bing.com/ping?sitemap=" . urlencode( $sitemap_url ) );
    }

    public static function activate_plugin() {
        self::write_sitemap_to_file();
        self::ping_search_engines();
    }

    public static function rebuild_and_ping() {
        self::write_sitemap_to_file();
        self::ping_search_engines();
    }
}
