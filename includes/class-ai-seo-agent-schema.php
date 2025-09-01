<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles schema.org markup injection, toggles, and output.
 */
class AI_SEO_Agent_SchemaManager {

    public static function init() {
        add_action( 'wp_head', [ __CLASS__, 'inject_schema' ] );
    }

    public static function inject_schema() {
        if ( is_singular() ) {
            $post = get_post();
            $meta = get_post_meta( $post->ID, '_ai_seo_agent', true );
            $settings = get_option( 'ai_seo_agent_settings', [] );
            $enabled_types = $settings['schema_types'] ?? [];

            // Article/Product schema
            if ( in_array( 'article', $enabled_types ) && get_post_type( $post ) === 'post' ) {
                echo self::get_article_schema( $post );
            }
            if ( in_array( 'product', $enabled_types ) && get_post_type( $post ) === 'product' ) {
                echo self::get_product_schema( $post );
            }
            // FAQ schema
            if ( in_array( 'faq', $enabled_types ) && ! empty( $meta['faq'] ) ) {
                echo self::get_faq_schema( $meta['faq'], $post );
            }
            // GEO schema
            if ( in_array( 'geo', $enabled_types ) ) {
                echo self::get_geo_schema();
            }
            // AI Overview schema
            if ( in_array( 'ai_overview', $enabled_types ) ) {
                echo self::get_ai_overview_schema( $post );
            }
        }
    }

    public static function get_article_schema( $post ) {
        $data = [
            "@context" => "https://schema.org",
            "@type" => "Article",
            "headline" => get_the_title( $post ),
            "description" => wp_strip_all_tags( get_the_excerpt( $post ) ),
            "author" => [
                "@type" => "Person",
                "name" => get_the_author_meta( 'display_name', $post->post_author )
            ],
            "datePublished" => get_the_date( 'c', $post ),
        ];
        return '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
    }

    public static function get_product_schema( $post ) {
        $data = [
            "@context" => "https://schema.org",
            "@type" => "Product",
            "name" => get_the_title( $post ),
            "description" => wp_strip_all_tags( get_the_excerpt( $post ) ),
        ];
        return '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
    }

    public static function get_faq_schema( $faq, $post ) {
        $mainEntity = [];
        foreach ( $faq as $qna ) {
            $parts = explode( '|', $qna );
            if ( count( $parts ) === 2 ) {
                $mainEntity[] = [
                    "@type" => "Question",
                    "name" => sanitize_text_field( $parts[0] ),
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => sanitize_text_field( $parts[1] ),
                    ]
                ];
            }
        }
        $data = [
            "@context" => "https://schema.org",
            "@type" => "FAQPage",
            "mainEntity" => $mainEntity,
        ];
        return '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
    }

    public static function get_geo_schema() {
        $settings = get_option( 'ai_seo_agent_settings', [] );
        $data = [
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
            "name" => $settings['site_title'] ?? get_bloginfo('name'),
            "address" => $settings['geo_address'] ?? '',
            "geo" => [
                "@type" => "GeoCoordinates",
                "latitude" => $settings['geo_lat'] ?? '',
                "longitude" => $settings['geo_lng'] ?? '',
            ],
        ];
        return '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
    }

    public static function get_ai_overview_schema( $post ) {
        $data = [
            "@context" => "https://schema.org",
            "@type" => "WebPage",
            "name" => get_the_title( $post ),
            "about" => wp_strip_all_tags( get_the_excerpt( $post ) ),
            "isAccessibleForFree" => true,
        ];
        return '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
    }

    public static function generate_all_schema() {
        // Placeholder: In production, loop and update all posts
        return true;
    }
}
