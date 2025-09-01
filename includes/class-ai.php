<?php
if (!defined('ABSPATH')) exit;

class AISEO_AI {
    private $api_key;
    public function __construct() {
        $this->api_key = get_option('aiseo_openai_key', '');
        add_action('wp_ajax_aiseo_auto_optimize', [$this, 'auto_optimize']);
        add_action('wp_ajax_aiseo_bulk_optimize', [$this, 'bulk_optimize']);
    }

    public function auto_optimize() {
        check_ajax_referer('aiseo_ai_nonce','nonce');
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if(!$post) wp_send_json_error("Invalid post");

        $data = $this->get_ai_data($post->post_content);
        if(!$data) wp_send_json_error("AI parse error");

        wp_update_post(['ID'=>$post_id,'post_name'=>sanitize_title($data['slug'])]);
        update_post_meta($post_id,'_aiseo_focus_keyword',sanitize_text_field($data['focus_keyword']));
        update_post_meta($post_id,'_aiseo_meta_description',sanitize_text_field($data['description']));
        update_post_meta($post_id,'_aiseo_meta_title',sanitize_text_field($data['title']));
        update_option('aiseo_last_ai_run', current_time('mysql')); // ✅ record last run
        wp_send_json_success(['message'=>'Optimized','data'=>$data]);
    }

    public function bulk_optimize() {
        check_ajax_referer('aiseo_ai_nonce','nonce');
        $posts = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>-1]);
        $done = [];
        foreach($posts as $p){
            $data = $this->get_ai_data($p->post_content);
            if(!$data) continue;
            wp_update_post(['ID'=>$p->ID,'post_name'=>sanitize_title($data['slug'])]);
            update_post_meta($p->ID,'_aiseo_focus_keyword',sanitize_text_field($data['focus_keyword']));
            update_post_meta($p->ID,'_aiseo_meta_description',sanitize_text_field($data['description']));
            update_post_meta($p->ID,'_aiseo_meta_title',sanitize_text_field($data['title']));
            $done[] = $p->ID;
        }
        wp_send_json_success(['optimized'=>$done]);
    }

    private function get_ai_data($content) {
        $prompt = "Optimize this WordPress post for SEO. Return JSON: {title, description, focus_keyword, slug}. Content:\n".wp_strip_all_tags($content);
        $raw = $this->call_ai($prompt);
        $json = json_decode($raw,true);
        return $json ?: null;
    }

    private function call_ai($prompt) {
        if(!$this->api_key) return "{}";
        $res = wp_remote_post("https://api.openai.com/v1/chat/completions",[
            'headers'=>[
                'Authorization'=>'Bearer '.$this->api_key,
                'Content-Type'=>'application/json'
            ],
            'body'=>json_encode([
                'model'=>'gpt-4o-mini',
                'messages'=>[['role'=>'user','content'=>$prompt]],
                'max_tokens'=>200
            ])
        ]);
        if(is_wp_error($res)) return "{}";
        $body = json_decode(wp_remote_retrieve_body($res),true);
        return $body['choices'][0]['message']['content'] ?? "{}";
    }
}
