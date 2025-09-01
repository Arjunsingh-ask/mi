<?php
if (!defined('ABSPATH')) exit;

class AISEO_SitemapManager {
    public function __construct() {
        add_action('init', [$this,'rewrite']);
        add_action('template_redirect', [$this,'render_sitemap']);
        add_action('save_post', [__CLASS__,'generate_sitemap']);
    }

    public function rewrite() {
        add_rewrite_rule('sitemap.xml$', 'index.php?aiseo_sitemap=1', 'top');
        add_rewrite_tag('%aiseo_sitemap%','1');
    }

    public function render_sitemap() {
        if (get_query_var('aiseo_sitemap')) {
            header('Content-Type: application/xml; charset=utf-8');
            echo file_get_contents(AI_SEO_AGENT_PATH.'sitemap.xml');
            exit;
        }
    }

    public static function generate_sitemap() {
        $posts = get_posts(['post_type'=>'any','post_status'=>'publish','numberposts'=>-1]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($posts as $p) {
            $xml .= '<url><loc>'.get_permalink($p).'</loc><lastmod>'.get_the_modified_date('c',$p).'</lastmod></url>';
        }
        $xml .= '</urlset>';
        file_put_contents(AI_SEO_AGENT_PATH.'sitemap.xml',$xml);
    }
}
