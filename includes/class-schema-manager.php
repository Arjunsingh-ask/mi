<?php
if (!defined('ABSPATH')) exit;

class AISEO_SchemaManager {
    public function __construct() {
        add_action('wp_head', [$this, 'output']);
    }
    public function output() {
        if (!is_singular()) return;
        global $post;
        $s = get_option('aiseo_settings', []);
        $out = [];

        if (!empty($s['schemas']['article'])) {
            $out[] = [
                "@context"=>"https://schema.org","@type"=>"Article",
                "headline"=>get_the_title($post),
                "datePublished"=>get_the_date('c',$post),
                "dateModified"=>get_the_modified_date('c',$post),
                "author"=>["@type"=>"Person","name"=>get_the_author()],
                "mainEntityOfPage"=>get_permalink($post)
            ];
        }
        if (!empty($s['schemas']['product']) && get_post_type($post)==='product') {
            $out[] = [
                "@context"=>"https://schema.org","@type"=>"Product",
                "name"=>get_the_title($post),
                "description"=>wp_strip_all_tags(get_the_excerpt($post)),
                "sku"=>$post->ID,
                "offers"=>["@type"=>"Offer","url"=>get_permalink($post),"priceCurrency"=>"USD","price"=>"99.00","availability"=>"https://schema.org/InStock"]
            ];
        }
        if (!empty($s['schemas']['faq'])) {
            $faq = get_post_meta($post->ID, '_aiseo_faq', true);
            if ($faq && is_array($faq)) {
                $q = [];
                foreach ($faq as $pair) {
                    if (empty($pair['q']) || empty($pair['a'])) continue;
                    $q[]=["@type"=>"Question","name"=>$pair['q'],"acceptedAnswer"=>["@type"=>"Answer","text"=>$pair['a']]];
                }
                if ($q) $out[]=["@context"=>"https://schema.org","@type"=>"FAQPage","mainEntity"=>$q];
            }
        }
        if (!empty($s['schemas']['geo'])) {
            $out[] = [
                "@context"=>"https://schema.org","@type"=>"LocalBusiness",
                "name"=>get_bloginfo('name'),
                "address"=>["@type"=>"PostalAddress","streetAddress"=>"","addressLocality"=>"","addressCountry"=>""],
                "geo"=>["@type"=>"GeoCoordinates","latitude"=>"","longitude"=>""]
            ];
        }
        if (!empty($s['schemas']['ai_overview'])) {
            $out[] = [
                "@context"=>"https://schema.org","@type"=>"WebPage",
                "name"=>get_the_title($post),
                "description"=>wp_strip_all_tags(get_the_excerpt($post)),
                "isPartOf"=>get_bloginfo('name')
            ];
        }
        if ($out) {
            echo '<script type="application/ld+json">'.wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
        }
    }
}
