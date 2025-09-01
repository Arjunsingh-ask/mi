<?php
if (!defined('ABSPATH')) exit;

class AISEO_SchemaManager {
    public function __construct() {
        add_action('wp_head', [$this, 'output_schema']);
    }

    public function output_schema() {
        if (!is_singular()) return;
        global $post;
        $settings = get_option('aiseo_settings', []);
        $schemas = [];

        // Article
        if (!empty($settings['schemas']['article'])) {
            $schemas[] = [
                "@context"=>"https://schema.org",
                "@type"=>"Article",
                "headline"=>get_the_title($post),
                "datePublished"=>get_the_date('c'),
                "dateModified"=>get_the_modified_date('c'),
                "author"=>["@type"=>"Person","name"=>get_the_author()],
                "mainEntityOfPage"=>get_permalink($post)
            ];
        }

        // Product
        if (!empty($settings['schemas']['product']) && get_post_type($post)==='product') {
            $schemas[] = [
                "@context"=>"https://schema.org",
                "@type"=>"Product",
                "name"=>get_the_title($post),
                "description"=>wp_strip_all_tags(get_the_excerpt($post)),
                "sku"=>$post->ID,
                "offers"=>[
                    "@type"=>"Offer",
                    "url"=>get_permalink($post),
                    "priceCurrency"=>"USD",
                    "price"=>"99.00",
                    "availability"=>"https://schema.org/InStock"
                ]
            ];
        }

        // FAQ
        if (!empty($settings['schemas']['faq'])) {
            $faq = get_post_meta($post->ID,'_aiseo_faq',true);
            if ($faq) {
                $faqs=[];
                foreach ($faq as $qna) {
                    $faqs[]=[
                        "@type"=>"Question",
                        "name"=>$qna['q'],
                        "acceptedAnswer"=>["@type"=>"Answer","text"=>$qna['a']]
                    ];
                }
                $schemas[]=["@context"=>"https://schema.org","@type"=>"FAQPage","mainEntity"=>$faqs];
            }
        }

        // GEO
        if (!empty($settings['schemas']['geo'])) {
            $schemas[]=[
                "@context"=>"https://schema.org",
                "@type"=>"LocalBusiness",
                "name"=>get_bloginfo('name'),
                "address"=>["@type"=>"PostalAddress","streetAddress"=>"123 Demo Street","addressLocality"=>"City","addressCountry"=>"Country"],
                "geo"=>["@type"=>"GeoCoordinates","latitude"=>"23.0225","longitude"=>"72.5714"]
            ];
        }

        // AI Overview
        if (!empty($settings['schemas']['ai_overview'])) {
            $schemas[]=[
                "@context"=>"https://schema.org",
                "@type"=>"WebPage",
                "name"=>get_the_title($post),
                "description"=>wp_strip_all_tags(get_the_excerpt($post)),
                "isPartOf"=>get_bloginfo('name')
            ];
        }

        if ($schemas) {
            echo '<script type="application/ld+json">'.wp_json_encode($schemas,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).'</script>';
        }
    }
}
