<?php
if (!defined('ABSPATH')) exit;

class AISEO_AI {
    private $api_key;

    public function __construct() {
        $this->api_key = get_option('aiseo_openai_key','');
        add_action('wp_ajax_aiseo_fetch_page_data',  [$this,'fetch_page_data']);   // fetch CURRENT
        add_action('wp_ajax_aiseo_generate_content', [$this,'generate_content_legacy']); // already used by your UI
        add_action('wp_ajax_aiseo_apply_generated',  [$this,'apply_generated']);   // PUBLISH new
        add_action('wp_ajax_aiseo_apply_generated', [$this, 'apply_generated']); // NEW
        add_action('wp_ajax_aiseo_fetch_page_data',        [$this,'fetch_page_data']);        // Fetch current
        add_action('wp_ajax_aiseo_generate_preview',       [$this,'generate_preview']);        // Preview
        add_action('wp_ajax_aiseo_publish_updates',        [$this,'publish_updates']);         // Publish
        add_action('wp_ajax_aiseo_bulk_optimize_pages_v2', [$this,'bulk_optimize_pages_v2']);  // Bulk
        add_action('wp_ajax_aiseo_suggest_internal_v2',    [$this,'suggest_internal_v2']);     // Internal links
        add_action('wp_ajax_aiseo_tools_rebuild_ping',     [$this,'tools_rebuild_ping']);      // Tools
        // Legacy dashboard button support (Content Generator)
        add_action('wp_ajax_aiseo_generate_content', [$this, 'generate_content_legacy']);

    }

    /* ---------- Fetch current page snapshot ---------- */
    public function fetch_page_data() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $page_id = intval($_POST['page_id'] ?? 0);
        $p = get_post($page_id);
        if (!$p || $p->post_type !== 'page') wp_send_json_error('Invalid page');

        $meta = [
            'meta_title'       => get_post_meta($page_id,'_aiseo_meta_title', true) ?: get_the_title($p),
            'meta_description' => get_post_meta($page_id,'_aiseo_meta_description', true) ?: '',
            'focus_keyword'    => get_post_meta($page_id,'_aiseo_focus_keyword', true) ?: '',
        ];
        wp_send_json_success(['meta'=>$meta, 'body'=>$p->post_content]);
    }

    /* ---------- Generate preview (length-matched + layout-safe) ---------- */
    public function generate_preview() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $page_id = intval($_POST['page_id'] ?? 0);
        $p = get_post($page_id);
        if (!$p || $p->post_type !== 'page') wp_send_json_error('Invalid page');

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $geo     = sanitize_text_field($_POST['geo'] ?? '');
        $brief   = sanitize_textarea_field($_POST['brief'] ?? '');
        $keep    = !empty($_POST['keep']);
        $length  = !empty($_POST['length']);

        $analysis = $this->analyze_page_structure_and_length($p->post_content);
        $target   = $analysis['word_count'];
        $min = $length ? max(200, (int)floor($target*0.9)) : max(600, $target);
        $max = $length ? (int)ceil($target*1.1)            : $min+300;

        $title = get_the_title($p);
        $focus = get_post_meta($page_id,'_aiseo_focus_keyword',true) ?: $keyword;

        $prompt = [
            'role'=>'user',
            'content' =>
"Generate layout-safe page copy for a WordPress Page.

Constraints:
- Preserve existing heading order: ".implode(' > ', $analysis['structure'])."
- Total length: between {$min} and {$max} words.
- Use service keyword + GEO naturally: '{$focus}' ; GEO: '{$geo}'.
- Optimize for AI Overviews: include a succinct intro (<= 50 words) and scannable sections.
- Include a helpful FAQ (3–6 Q&A) based on likely Google intents for '{$focus}' in '{$geo}'.
- Return strict JSON:
{ h1, sections:[{h2,content, h3s?:[{h3,content}]}], faq:[{q,a}], meta_title, meta_description (<=155), focus_keyword }

Context Title: {$title}
Additional Notes: {$brief}
Current excerpt: ".mb_substr(wp_strip_all_tags($p->post_content),0,400)
        ];

        $ai = $this->call_ai_json($prompt, false);
        if (!$ai) {
            $ai = [
                'h1'=>$title,
                'sections'=>[['h2'=>'Introduction','content'=>'<p>…</p>']],
                'faq'=>[['q'=>'What is this?','a'=>'A local service.']],
                'meta_title'=>$title,
                'meta_description'=>'Learn about '.$focus.' in '.$geo.'.',
                'focus_keyword'=>$focus ?: sanitize_title($title),
            ];
        }

        $html = $this->build_html_from_ai($ai, $keep ? $analysis['structure'] : ['h1','h2','h2','h3']);

        wp_send_json_success([
            'meta'=>[
              'meta_title'=>$ai['meta_title'] ?? $title,
              'meta_description'=>$ai['meta_description'] ?? '',
              'focus_keyword'=>$ai['focus_keyword'] ?? $focus
            ],
            'body'=>$html
        ]);
    }

    /* ---------- Publish updates (applies preview results) ---------- */
    public function publish_updates() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $page_id = intval($_POST['page_id'] ?? 0);
        $p = get_post($page_id);
        if (!$p || $p->post_type !== 'page') wp_send_json_error('Invalid page');

        $result = $this->generate_preview_internal($p, [
            'keyword' => sanitize_text_field($_POST['keyword'] ?? ''),
            'geo'     => sanitize_text_field($_POST['geo'] ?? ''),
            'brief'   => sanitize_textarea_field($_POST['brief'] ?? ''),
            'keep'    => !empty($_POST['keep']),
            'length'  => !empty($_POST['length']),
        ]);

        $update = ['ID'=>$page_id,'post_content'=>$result['body']];
        if (!empty($_POST['publish'])) $update['post_status'] = 'publish';
        wp_update_post($update);

        update_post_meta($page_id,'_aiseo_meta_title',       sanitize_text_field($result['meta']['meta_title']));
        update_post_meta($page_id,'_aiseo_meta_description', sanitize_text_field($result['meta']['meta_description']));
        update_post_meta($page_id,'_aiseo_focus_keyword',    sanitize_text_field($result['meta']['focus_keyword']));

        update_option('aiseo_last_ai_run', current_time('mysql'));

        wp_send_json_success(['ok'=>true]);
    }

    private function generate_preview_internal($p, $req) {
        $analysis = $this->analyze_page_structure_and_length($p->post_content);
        $keep     = !empty($req['keep']);
        $length   = !empty($req['length']);
        $target   = $analysis['word_count'];
        $min = $length ? max(200, (int)floor($target*0.9)) : max(600, $target);
        $max = $length ? (int)ceil($target*1.1)            : $min+300;

        $title = get_the_title($p);
        $focus = get_post_meta($p->ID,'_aiseo_focus_keyword',true) ?: sanitize_text_field($req['keyword'] ?? '');

        $prompt = [
          'role'=>'user',
          'content'=>"Return JSON { h1, sections:[{h2,content,h3s?:[{h3,content}]}], faq:[{q,a}], meta_title, meta_description (<=155), focus_keyword }.
Preserve order: ".implode(' > ', $analysis['structure'])."; words between {$min} and {$max}; keyword '{$focus}'; GEO '".sanitize_text_field($req['geo'] ?? '')."'.
Title: {$title}. Notes: ".sanitize_textarea_field($req['brief'] ?? '')."."
        ];

        $ai = $this->call_ai_json($prompt, false);
        if (!$ai) $ai = ['h1'=>$title,'sections'=>[['h2'=>'Intro','content'=>'<p>…</p>']],'meta_title'=>$title,'meta_description'=>'','focus_keyword'=>$focus];

        return [
          'meta'=>[
            'meta_title'=>$ai['meta_title'] ?? $title,
            'meta_description'=>$ai['meta_description'] ?? '',
            'focus_keyword'=>$ai['focus_keyword'] ?? $focus
          ],
          'body'=>$this->build_html_from_ai($ai, $keep ? $analysis['structure'] : ['h1','h2','h2','h3'])
        ];
    }

    /* ---------- Bulk optimizer (paged with detailed log) ---------- */
    public function bulk_optimize_pages_v2() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $batch  = 20;
        $offset = intval($_POST['offset'] ?? 0);

        $all_ids = get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']);
        $slice = array_slice($all_ids, $offset, $batch);
        $log = [];

        foreach ($slice as $pid) {
            $p = get_post($pid);
            if (!$p) continue;

            $data = $this->call_ai_json([
                'role'=>'user',
                'content'=>"Return JSON {meta_title, meta_description, focus_keyword, slug}. Keep meta_description <= 155 chars.\nPage Title: ".get_the_title($p)."\nContent: ".mb_substr(wp_strip_all_tags($p->post_content),0,2500)
            ]);

            if (!$data || !is_array($data)) continue;

            $before_title = get_post_meta($pid,'_aiseo_meta_title', true) ?: get_the_title($p);
            $before_desc  = get_post_meta($pid,'_aiseo_meta_description', true) ?: '';
            $before_kw    = get_post_meta($pid,'_aiseo_focus_keyword', true) ?: '';

            wp_update_post(['ID'=>$pid,'post_name'=>sanitize_title($data['slug'] ?? $p->post_name)]);
            update_post_meta($pid,'_aiseo_meta_title',       sanitize_text_field($data['meta_title'] ?? $before_title));
            update_post_meta($pid,'_aiseo_meta_description', sanitize_text_field($data['meta_description'] ?? $before_desc));
            update_post_meta($pid,'_aiseo_focus_keyword',    sanitize_text_field($data['focus_keyword'] ?? $before_kw));

            $change = [];
            if (($data['meta_title'] ?? '') && $data['meta_title'] !== $before_title) $change[]='title';
            if (($data['meta_description'] ?? '') && $data['meta_description'] !== $before_desc) $change[]='description';
            if (($data['focus_keyword'] ?? '') && $data['focus_keyword'] !== $before_kw) $change[]='keyword';

            $log[] = ['id'=>$pid,'title'=>get_the_title($p),'change'=>$change ? implode(', ',$change) : 'no change'];
        }

        if ($slice) update_option('aiseo_last_ai_run', current_time('mysql'));

        $next = ($offset + $batch < count($all_ids)) ? $offset + $batch : null;
        wp_send_json_success([
            'processed'  => $offset + count($slice),
            'total'      => count($all_ids),
            'next_offset'=> $next,
            'log'        => $log
        ]);
    }

    /* ---------- Internal linking suggestions ---------- */
    public function suggest_internal_v2() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

        $pages = get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1]);
        $list = array_map(function($p){
            return ['id'=>$p->ID,'title'=>get_the_title($p),'url'=>get_permalink($p)];
        }, $pages);

        if (empty($this->api_key)) {
            $sugs = [];
            foreach ($list as $a) foreach ($list as $b) {
                if ($a['id'] === $b['id']) continue;
                $first = strtok($a['title'],' ');
                if ($first && stripos($b['title'],$first)!==false) {
                    $sugs[] = ['from_title'=>$a['title'],'from_url'=>$a['url'],'to_title'=>$b['title'],'to_url'=>$b['url'],'anchor'=>$first.' in '.$b['title']];
                }
            }
            return wp_send_json_success(array_slice($sugs,0,20));
        }

        $prompt = "Given these pages (title, url), propose up to 20 internal links as JSON array [{from_title,from_url,to_title,to_url,anchor}]:\n".wp_json_encode($list);
        $arr = $this->call_ai_json(['role'=>'user','content'=>$prompt], true);
        $out = [];
        foreach (($arr ?: []) as $r) {
            $out[] = [
                'from_title'=>$r['from_title'] ?? '',
                'from_url'  =>$r['from_url']   ?? '',
                'to_title'  =>$r['to_title']   ?? '',
                'to_url'    =>$r['to_url']     ?? '',
                'anchor'    =>$r['anchor']     ?? ''
            ];
            if (count($out)>=20) break;
        }
        wp_send_json_success($out);
    }

    /* ---------- Tools: rebuild sitemap + ping engines ---------- */
    public function tools_rebuild_ping() {
        check_ajax_referer('aiseo_ai','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        if (class_exists('AISEO_SitemapManager')) {
            AISEO_SitemapManager::generate_sitemap();
        }
        $sitemap_url = home_url('/sitemap.xml');
        wp_remote_get('https://www.google.com/ping?sitemap='.rawurlencode($sitemap_url), ['timeout'=>10]);
        wp_remote_get('https://www.bing.com/ping?sitemap='.rawurlencode($sitemap_url),   ['timeout'=>10]);
        wp_send_json_success(['message'=>'Sitemap rebuilt and pings sent']);
    }

    /* ------------- helpers ------------- */
    private function analyze_page_structure_and_length($html) {
        $text_only = wp_strip_all_tags($html);
        $words = preg_split('/\s+/', trim($text_only));
        $word_count = max(50, count(array_filter($words)));
        preg_match_all('/<(h[1-3])[^>]*>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER);
        $structure = [];
        foreach ($m as $match) $structure[] = strtolower($match[1]);
        if (!$structure) $structure = ['h1','h2','h2','h3'];
        return ['word_count'=>$word_count,'structure'=>$structure];
    }

    private function build_html_from_ai($ai, $structure) {
        $out = [];
        if (!empty($ai['h1'])) $out[] = '<h1>'.esc_html($ai['h1']).'</h1>';
        if (!empty($ai['sections']) && is_array($ai['sections'])) {
            $si = 0;
            foreach ($structure as $tag) {
                if ($tag === 'h1') continue;
                $sec = $ai['sections'][$si] ?? null;
                if (!$sec) break;
                if ($tag === 'h2' && !empty($sec['h2'])) {
                    $out[] = '<h2>'.esc_html($sec['h2']).'</h2>';
                    if (!empty($sec['content'])) $out[] = wp_kses_post($sec['content']);
                    if (!empty($sec['h3s']) && is_array($sec['h3s'])) {
                        foreach ($sec['h3s'] as $h3) {
                            if (!empty($h3['h3'])) $out[] = '<h3>'.esc_html($h3['h3']).'</h3>';
                            if (!empty($h3['content'])) $out[] = wp_kses_post($h3['content']);
                        }
                    }
                    $si++;
                } elseif ($tag === 'h3' && !empty($sec['h3s'][0]['h3'])) {
                    $h3 = $sec['h3s'][0];
                    $out[] = '<h3>'.esc_html($h3['h3']).'</h3>';
                    if (!empty($h3['content'])) $out[] = wp_kses_post($h3['content']);
                }
            }
        } elseif (!empty($ai['body'])) {
            $out[] = wp_kses_post($ai['body']);
        }
        return implode("\n", $out);
    }

public function generate_content_legacy() {
    check_ajax_referer('aiseo_ai','nonce');
    if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

    $page_id = intval($_POST['page_id'] ?? 0);
    $prompt  = sanitize_textarea_field($_POST['prompt'] ?? '');
    $page    = get_post($page_id);
    if (!$page || $page->post_type !== 'page') wp_send_json_error('Invalid page');

    $title   = get_the_title($page);
    $current = wp_strip_all_tags($page->post_content);
    $word_ct = max(200, str_word_count($current)); // match length baseline

    $ai = $this->call_ai_json([
      'role'=>'user',
      'content'=>
"Task: Produce a UNIQUE, HUMANIZED rewrite for a WordPress Page. Avoid plagiarism; do not copy sentences verbatim.
Constraints:
- Match current length within ±10% (target ~{$word_ct} words).
- Use clear H1/H2/H3 structure.
- Keep HTML minimal and clean (p, h2, h3, ul, ol).
- Provide meta title (<= 60 chars) and meta description (<= 155 chars).
- Keep tone professional and natural; remove fluff.
- Return STRICT JSON:
{ h1, outline: [H2 strings], body: HTML, meta_title, meta_description, focus_keyword }

Page Title: {$title}
Brief/Notes: {$prompt}
Current Body (for sense only, rephrase fully): ".mb_substr($current,0,4000)
    ]);

    if (!$ai || !is_array($ai)) {
        $ai = [
            'h1'               => $title,
            'outline'          => ['Introduction','Key Benefits','How it Works','FAQ','Contact'],
            'body'             => '<h2>Introduction</h2><p>…</p>',
            'meta_title'       => wp_trim_words($title, 12, ''),
            'meta_description' => wp_trim_words($prompt ?: $current, 26, ''),
            'focus_keyword'    => sanitize_title($title),
        ];
    }

    wp_send_json_success([
        'h1'               => $ai['h1'] ?? $title,
        'outline'          => $ai['outline'] ?? [],
        'body'             => $ai['body'] ?? '',
        'meta_title'       => $ai['meta_title'] ?? $title,
        'meta_description' => $ai['meta_description'] ?? '',
        'focus_keyword'    => $ai['focus_keyword'] ?? sanitize_title($title),
    ]);
}
public function apply_generated() {
    check_ajax_referer('aiseo_ai','nonce');
    if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

    $page_id = intval($_POST['page_id'] ?? 0);
    $post    = get_post($page_id);
    if (!$post || $post->post_type !== 'page') wp_send_json_error('Invalid page');

    $meta_title       = sanitize_text_field($_POST['meta_title'] ?? '');
    $meta_description = sanitize_text_field($_POST['meta_description'] ?? '');
    $focus_keyword    = sanitize_text_field($_POST['focus_keyword'] ?? '');
    $body_html        = wp_kses_post($_POST['body'] ?? '');
    $publish          = !empty($_POST['publish']);

    $update = ['ID'=>$page_id, 'post_content'=>$body_html];
    if ($publish) $update['post_status'] = 'publish';
    wp_update_post($update);

    if ($meta_title !== '')       update_post_meta($page_id, '_aiseo_meta_title', $meta_title);
    if ($meta_description !== '') update_post_meta($page_id, '_aiseo_meta_description', $meta_description);
    if ($focus_keyword !== '')    update_post_meta($page_id, '_aiseo_focus_keyword', $focus_keyword);

    update_option('aiseo_last_ai_run', current_time('mysql'));
    if (class_exists('AISEO_SitemapManager')) AISEO_SitemapManager::generate_sitemap();

    wp_send_json_success(['updated'=>true]);
}

public function fetch_page_data() {
    check_ajax_referer('aiseo_ai','nonce');
    if (!current_user_can('edit_pages')) wp_send_json_error('No permission');

    $page_id = intval($_POST['page_id'] ?? 0);
    $p = get_post($page_id);
    if (!$p || $p->post_type !== 'page') wp_send_json_error('Invalid page');

    $meta = [
        'meta_title'       => get_post_meta($page_id,'_aiseo_meta_title', true) ?: get_the_title($p),
        'meta_description' => get_post_meta($page_id,'_aiseo_meta_description', true) ?: '',
        'focus_keyword'    => get_post_meta($page_id,'_aiseo_focus_keyword', true) ?: '',
    ];
    wp_send_json_success(['meta'=>$meta, 'body'=>$p->post_content]);
}
    
    private function call_ai_json($message, $expect_array = false) {
        if (empty($this->api_key)) return null;
        $body = ['model'=>'gpt-4o-mini','messages'=>[$message],'max_tokens'=>900,'temperature'=>0.4];
        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => ['Authorization'=>'Bearer '.$this->api_key,'Content-Type'=>'application/json'],
            'body'    => wp_json_encode($body),'timeout'=>45,
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
