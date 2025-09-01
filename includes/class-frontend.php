<?php
if (!defined('ABSPATH')) exit;

class AISEO_Frontend {
    public function __construct() {
        add_action('wp_head', [$this, 'output_meta']);
    }

    public function output_meta() {
        if (!is_singular()) return;
        global $post;

        $title = get_post_meta($post->ID, '_aiseo_meta_title', true) ?: get_the_title($post);
        $desc  = get_post_meta($post->ID, '_aiseo_meta_description', true) ?: wp_trim_words(strip_tags($post->post_content), 25);
        $focus = get_post_meta($post->ID, '_aiseo_focus_keyword', true);

        echo '<title>'.esc_html($title).'</title>'."\n";
        echo '<meta name="description" content="'.esc_attr($desc).'">'."\n";
        if ($focus) echo '<meta name="keywords" content="'.esc_attr($focus).'">'."\n";
        echo '<link rel="canonical" href="'.esc_url(get_permalink($post)).'">'."\n";
    }
}
