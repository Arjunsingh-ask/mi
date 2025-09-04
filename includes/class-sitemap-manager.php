<?php
if (!defined('ABSPATH')) exit;

class AISEO_SitemapManager {
    public function __construct() {
        add_action('init', [$this,'rewrite']);
        add_action('template_redirect', [$this,'serve']);
        add_action('save_post_page', [__CLASS__,'generate_sitemap']);
    }
    public function rewrite() {
        add_rewrite_rule('sitemap.xml$', 'index.php?aiseo_sitemap=1', 'top');
        add_rewrite_tag('%aiseo_sitemap%','1');
    }
    public function serve() {
        if (get_query_var('aiseo_sitemap')) {
            header('Content-Type: application/xml; charset=utf-8');
            $file = self::file_path();
            if (file_exists($file)) readfile($file);
            else echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
            exit;
        }
    }
    public static function file_path() {
        $u = wp_upload_dir();
        return trailingslashit($u['basedir']).'aiseo-sitemap.xml';
    }
    public static function generate_sitemap() {
        $pages = get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($pages as $p) {
            $xml .= '<url><loc>'.esc_url_raw(get_permalink($p)).'</loc><lastmod>'.get_the_modified_date('c',$p).'</lastmod></url>';
        }
        $xml .= '</urlset>';
        @file_put_contents(self::file_path(), $xml);
    }
}
