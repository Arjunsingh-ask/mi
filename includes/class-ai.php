<?php
if (!defined('ABSPATH')) exit;

class AISEO_AI {
    private $api_key;
    public function __construct() {
        $this->api_key = get_option('aiseo_openai_key','');
        add_action('wp_ajax_aiseo_auto_optimize', [$this,'auto_optimize']);
        add_action('wp_ajax_aiseo_bulk_optimize', [$this,'bulk_optimize']);
    }

    public function auto_optimize() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('No permission');
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $post = get_post($post_id);
        if (!$post) wp_send_json_error('Invalid post');

        $data = $this->get_ai_data($post->post_content, get_the_title($post));
        if (!$data) wp_send_json_error('AI unavailable or bad response');

        wp_update_post(['ID'=>$post_id, 'post_name'=>sanitize_title($data['slug'])]);
        update_post_meta($post_id,'_aiseo_focus_keyword',sanitize_text_field($data['focus_keyword']));
        update_post_meta($post_id,'_aiseo_meta_description',sanitize_text_field($data['description']));
        update_post_meta($post_id,'_aiseo_meta_title',sanitize_text_field($data['title']));
        update_option('aiseo_last_ai_run', current_time('mysql'));

        wp_send_json_success($data);
    }

    public function bulk_optimize() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $posts = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>-1]);
        $done = 0;
        foreach ($posts as $p) {
            $data = $this->get_ai_data($p->post_content, get_the_title($p));
            if (!$data) continue;
            wp_update_post(['ID'=>$p->ID, 'post_name'=>sanitize_title($data['slug'])]);
            update_post_meta($p->ID,'_aiseo_focus_keyword',sanitize_text_field($data['focus_keyword']));
            update_post_meta($p->ID,'_aiseo_meta_description',sanitize_text_field($data['description']));
            update_post_meta($p->ID,'_aiseo_meta_title',sanitize_text_field($data['title']));
            $done++;
        }
        if ($done) update_option('aiseo_last_ai_run', current_time('mysql'));
        wp_send_json_success(['optimized'=>$done]);
    }

    private function get_ai_data($content, $title) {
        // If no key, return a safe heuristic result so UI still works
        if (empty($this->api_key)) {
            return [
                'title' => wp_trim_words($title, 12, ''),
                'description' => wp_trim_words(wp_strip_all_tags($content), 26, ''),
                'focus_keyword' => sanitize_title($title),
                'slug' => sanitize_title($title)
            ];
        }

        // OpenAI Chat Completions (safe defaults). If your host blocks external calls, this will still fail gracefully.
        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [[
                'role'=>'user',
                'content' => "Create SEO fields as JSON: {title, description, focus_keyword, slug}. Title: ".mb_substr($title,0,120)."\nContent: ".mb_substr(wp_strip_all_tags($content),0,4000)
            ]],
            'max_tokens' => 200,
            'temperature' => 0.3,
        ];
        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);
        if (is_wp_error($res)) return null;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        $text = $json['choices'][0]['message']['content'] ?? '';
        $data = json_decode(trim($text), true);
        if (!is_array($data)) return null;
        return [
            'title' => sanitize_text_field($data['title'] ?? $title),
            'description' => sanitize_text_field($data['description'] ?? wp_trim_words(wp_strip_all_tags($content), 26, '')),
            'focus_keyword' => sanitize_text_field($data['focus_keyword'] ?? sanitize_title($title)),
            'slug' => sanitize_title($data['slug'] ?? $title)
        ];
    }
}
