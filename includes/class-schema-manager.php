<?php
if (!defined('ABSPATH')) exit;

class AISEO_SchemaManager {
    public function __construct() {
        add_action('wp_head', [$this,'output']);
    }
    public function output() {
        if (!is_page()) return;
        global $post;
        $s = get_option('aiseo_settings', []);
        $out = [];

        if (!empty($s['schemas']['webpage'])) {
            $out[] = [
                "@context"=>"https://schema.org","@type"=>"WebPage",
                "name"=>get_the_title($post),
                "description"=>wp_strip_all_tags(get_the_excerpt($post)),
                "url"=>get_permalink($post),
                "isPartOf"=>get_bloginfo('name')
            ];
        }
        if (!empty($s['schemas']['faq'])) {
            $faq = get_post_meta($post->ID, '_aiseo_faq', true);
            if ($faq && is_array($faq)) {
                $q=[];
                foreach ($faq as $pair) {
                    if (empty($pair['q']) || empty($pair['a'])) continue;
                    $q[]=["@type"=>"Question","name"=>$pair['q'],"acceptedAnswer"=>["@type"=>"Answer","text"=>$pair['a']]];
                }
                if ($q) $out[]=["@context"=>"https://schema.org","@type"=>"FAQPage","mainEntity"=>$q];
            }
        }
        if (!empty($s['schemas']['ai_overview'])) {
            $out[] = [
                "@context"=>"https://schema.org","@type"=>"WebPage","name"=>get_the_title($post),
                "description"=>wp_strip_all_tags(get_the_excerpt($post))
            ];
        }
        if ($out) {
            echo '<script type="application/ld+json">'.wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
        }
    }
}
