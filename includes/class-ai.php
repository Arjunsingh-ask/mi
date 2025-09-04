<?php
if (!defined('ABSPATH')) exit;

class AISEO_AI {
    private $api_key;
    public function __construct() {
        $this->api_key = get_option('aiseo_openai_key','');
        add_action('wp_ajax_aiseo_generate_content', [$this,'generate_content']);
        add_action('wp_ajax_aiseo_bulk_optimize_pages', [$this,'bulk_optimize_pages']);
        add_action('wp_ajax_aiseo_suggest_internal', [$this,'suggest_internal']);
        add_action('wp_ajax_aiseo_rebuild_sitemap', [$this,'rebuild_sitemap']);
    }

    /** Content generator for a selected PAGE */
    public function generate_content() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $prompt  = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        $page    = get_post($page_id);
        if (!$page || $page->post_type !== 'page') wp_send_json_error('Invalid page');

        $title = get_the_title($page);
        $content = wp_strip_all_tags($page->post_content);

        $result = $this->call_ai_json([
            'role'=>'user',
            'content'=>"You are an SEO/content assistant. Given a Page and a brief, produce JSON with keys: {h1, outline (array of H2 strings), body (HTML), meta_title, meta_description, focus_keyword}. Keep meta_description <= 155 chars.\n\nPage Title: {$title}\nBrief: {$prompt}\nCurrent Excerpt: ".mb_substr(wp_strip_all_tags($content),0,400)
        ]);

        if (!$result) {
            // fallback heuristic
            $fallback = [
                'h1' => $title,
                'outline' => ['Introduction','Key Benefits','How it Works','FAQ','Contact'],
                'body' => '<h2>Introduction</h2><p>...</p>',
                'meta_title' => wp_trim_words($title, 10,''),
                'meta_description' => wp_trim_words($prompt ?: $content, 24,''),
                'focus_keyword' => sanitize_title($title),
            ];
            wp_send_json_success($fallback);
        }
        wp_send_json_success($result);
    }

    /** Bulk optimize PAGE meta */
    public function bulk_optimize_pages() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $pages = get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1]);
        $optimized = 0;

        foreach ($pages as $p) {
            $data = $this->call_ai_json([
                'role'=>'user',
                'content'=>"Return JSON {meta_title, meta_description, focus_keyword, slug}. Keep meta_description <= 155 chars.\nPage Title: ".get_the_title($p)."\nContent: ".mb_substr(wp_strip_all_tags($p->post_content),0,3000)
            ]);
            if (!$data) continue;

            wp_update_post(['ID'=>$p->ID, 'post_name'=>sanitize_title($data['slug'] ?? $p->post_name)]);
            update_post_meta($p->ID,'_aiseo_meta_title',sanitize_text_field($data['meta_title'] ?? get_the_title($p)));
            update_post_meta($p->ID,'_aiseo_meta_description',sanitize_text_field($data['meta_description'] ?? ''));
            update_post_meta($p->ID,'_aiseo_focus_keyword',sanitize_text_field($data['focus_keyword'] ?? ''));
            $optimized++;
        }
        if ($optimized) update_option('aiseo_last_ai_run', current_time('mysql'));
        wp_send_json_success(['optimized'=>$optimized]);
    }

    /** Suggest internal links between pages (simple title-keyword cosine-ish heuristic via AI) */
    public function suggest_internal() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $pages = get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1]);
        $pairs = [];
        $list = [];
        foreach($pages as $p) $list[] = ['id'=>$p->ID,'title'=>get_the_title($p),'url'=>get_permalink($p)];

        // If no key, do a lightweight heuristic (title substrings)
        if (empty($this->api_key)) {
            foreach ($list as $a) {
                foreach ($list as $b) {
                    if ($a['id'] === $b['id']) continue;
                    if (stripos($b['title'], explode(' ', $a['title'])[0]) !== false) {
                        $pairs[] = ['from'=>$a, 'to'=>$b, 'anchor'=>sanitize_text_field($a['title'])];
                    }
                }
            }
            wp_send_json_success(['suggestions'=>array_slice($pairs,0,20)]);
        }

        // With AI, ask for top 20 pairs
        $prompt = "Given the following pages with titles and URLs, propose up to 20 internal link suggestions as JSON array: [{from_title, from_url, to_title, to_url, suggested_anchor}]. Prefer linking hub pages to relevant detail pages.\n\n".wp_json_encode($list);
        $data = $this->call_ai_json(['role'=>'user','content'=>$prompt], true);
        if (!$data || !is_array($data)) $data = [];
        wp_send_json_success(['suggestions'=>array_slice($data,0,20)]);
    }

    public function rebuild_sitemap() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        if (class_exists('AISEO_SitemapManager')) {
            AISEO_SitemapManager::generate_sitemap();
            wp_send_json_success(['message'=>'Sitemap rebuilt']);
        }
        wp_send_json_error('Unavailable');
    }

    /** Helper: OpenAI call returning decoded JSON or null */
    private function call_ai_json($message, $expect_array = false) {
        if (empty($this->api_key)) return null;
        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [ $message ],
            'max_tokens' => 800,
            'temperature' => 0.4,
        ];
        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return null;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        $text = $json['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode(trim($text), true);
        if (!$parsed) return null;
        if ($expect_array && !is_array($parsed)) return null;
        return $parsed;
    }
}
