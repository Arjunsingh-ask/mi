<?php
if (!defined('ABSPATH')) exit;

class AISEO_Admin {
    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'register_settings']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        add_action('add_meta_boxes', [$this,'meta_box_pages_only']);
        add_action('save_post_page', [$this,'save_page_meta'], 10, 2);
        add_action('wp_dashboard_setup', [$this,'dashboard_widget']);
    }

    public function menu() {
        add_menu_page(
            'SEO Agent',
            'SEO Agent',
            'manage_options',
            'aiseo-dashboard',
            [$this,'dashboard'],
            'dashicons-chart-line',
            58
        );
        add_submenu_page('aiseo-dashboard','Settings','Settings','manage_options','aiseo-settings',[$this,'settings_page']);
    }

    public function register_settings() {
        register_setting('aiseo_group', 'aiseo_settings');
        register_setting('aiseo_group', 'aiseo_openai_key', ['sanitize_callback'=>'sanitize_text_field']);
        register_setting('aiseo_group', 'aiseo_link_ops', ['sanitize_callback'=>[$this,'sanitize_link_ops']]); // link tracker
    }

    public function sanitize_link_ops($input) {
        if (!is_array($input)) return [];
        $clean = [];
        foreach ($input as $i => $row) {
            $clean[$i] = [
                'target_page' => isset($row['target_page']) ? intval($row['target_page']) : 0,
                'prospect_url'=> isset($row['prospect_url']) ? esc_url_raw($row['prospect_url']) : '',
                'status'      => isset($row['status']) ? sanitize_text_field($row['status']) : 'new',
                'notes'       => isset($row['notes']) ? sanitize_textarea_field($row['notes']) : ''
            ];
        }
        return $clean;
    }

    public function assets($hook) {
        if ($hook === 'toplevel_page_aiseo-dashboard' || $hook === 'seo-agent_page_aiseo-settings' || in_array($hook, ['page.php','post.php','post-new.php'], true)) {
            wp_enqueue_style('aiseo-admin', AI_SEO_AGENT_URL.'assets/admin.css', [], '1.2.0');
            wp_enqueue_script('aiseo-admin', AI_SEO_AGENT_URL.'assets/admin.js', ['jquery'], '1.2.0', true);
            wp_enqueue_script('aiseo-admin', AI_SEO_AGENT_URL.'assets/admin.js', ['jquery'], '1.3.0', true);
            wp_localize_script('aiseo-admin','aiseo_ai',[
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiseo_ai'),
            ]);
        }
    }

    /** Dashboard (all-in-one hub) */
    public function dashboard() {
        $key = get_option('aiseo_openai_key','');
        $link_ops = get_option('aiseo_link_ops', []);
        ?>
        <div class="wrap">
            <h1>AI SEO Agent — Dashboard (Pages)</h1>
            <p class="desc">Generate content, optimize pages, manage internal links and link-building — in one place.</p>

            <div class="aiseo-tabs">
                <button class="nav-tab nav-tab-active" data-tab="content">Content Generator</button>
                <button class="nav-tab" data-tab="optimize">Page Optimizer</button>
                <button class="nav-tab" data-tab="internal">Internal Linking</button>
                <button class="nav-tab" data-tab="linkdev">Link Development</button>
                <button class="nav-tab" data-tab="tools">Tools</button>
            </div>

            <!-- Content Generator -->
            <div class="aiseo-tab-panel" id="tab-content" style="display:block">
                <h2>Content Generator (Pages)</h2>
                <p>Pick a page, add a prompt, and let AI draft headings, copy, and meta.</p>
                <p>
                    <label>Select Page: <?php wp_dropdown_pages(['show_option_none'=>'— Select a page —','name'=>'aiseo_gen_page','id'=>'aiseo_gen_page']); ?></label>
                </p>
                <textarea id="aiseo_gen_prompt" rows="5" style="width:100%" placeholder="Describe what to write..."></textarea>
                <p><button class="button button-primary" id="aiseo-generate-content">✨ Generate Content</button></p>
                <div id="aiseo-gen-output"></div>
                <?php if(empty($key)) echo '<p class="notice notice-warning">Tip: Add your OpenAI API key in Settings for best results.</p>'; ?>
            </div>
            <div class="aiseo-tab-panel" id="tab-content" style="display:block">
  <h2>Content Generator (Pages)</h2>
  <p>Pick a page. Fetch current content, generate a humanized unique version, then publish.</p>

  <p>
    <label>Select Page:
      <?php wp_dropdown_pages(['show_option_none'=>'— Select a page —','name'=>'aiseo_gen_page','id'=>'aiseo_gen_page']); ?>
    </label>
  </p>

  <p><textarea id="aiseo_gen_prompt" rows="4" style="width:100%" placeholder="Optional notes: tone, key selling points, FAQs to include…"></textarea></p>

  <p>
    <button class="button" id="aiseo-fetch-current">🔎 Fetch Current</button>
    <button class="button button-primary" id="aiseo-generate-content">✨ Generate Content</button>
  </p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <h3>Current Content</h3>
      <div id="aiseo-current-meta"></div>
      <div id="aiseo-current-body" class="aiseo-diff-box"></div>
    </div>
    <div>
      <h3>New (Humanized AI)</h3>
      <div id="aiseo-new-meta"></div>
      <div id="aiseo-new-body" class="aiseo-diff-box"></div>
      <p>
        <button class="button" id="aiseo-apply-draft" style="display:none">💾 Apply to Draft</button>
        <button class="button button-primary" id="aiseo-apply-publish" style="display:none">✅ Apply & Publish</button>
      </p>
      <div id="aiseo-gen-output"></div>
    </div>
  </div>
</div>


            <!-- Page Optimizer -->
            <div class="aiseo-tab-panel" id="tab-optimize">
                <h2>Page Optimizer</h2>
                <p>Bulk optimize titles, meta descriptions, slugs, and focus keywords for Pages only.</p>
                <p><button class="button button-primary" id="aiseo-bulk-optimize-pages">⚡ Bulk Optimize Pages</button></p>
                <div id="aiseo-bulk-log"></div>
            </div>

            <!-- Internal Linking -->
            <div class="aiseo-tab-panel" id="tab-internal">
                <h2>Internal Linking Suggestions</h2>
                <p>AI suggests internal links between your pages based on titles & keywords.</p>
                <p><button class="button" id="aiseo-suggest-internal">💡 Suggest Links</button></p>
                <div id="aiseo-internal-output"></div>
            </div>

            <!-- Link Development -->
            <div class="aiseo-tab-panel" id="tab-linkdev">
                <h2>Link-Building Tracker</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('aiseo_group'); ?>
                    <table class="widefat striped">
                        <thead><tr><th style="width:220px">Target Page</th><th>Prospect URL</th><th style="width:120px">Status</th><th>Notes</th></tr></thead>
                        <tbody id="aiseo-link-rows">
                        <?php
                        $pages = get_pages(['sort_column'=>'post_title']);
                        $page_map = [];
                        foreach ($pages as $p) $page_map[$p->ID] = $p->post_title;

                        if (!$link_ops) $link_ops = [['target_page'=>0,'prospect_url'=>'','status'=>'new','notes'=>'']];
                        foreach ($link_ops as $i=>$row): ?>
                            <tr>
                                <td>
                                  <select name="aiseo_link_ops[<?php echo $i; ?>][target_page]" style="width:100%">
                                    <option value="0">— Select page —</option>
                                    <?php foreach ($page_map as $pid=>$ptitle): ?>
                                      <option value="<?php echo $pid; ?>" <?php selected(intval($row['target_page']),$pid); ?>><?php echo esc_html($ptitle); ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                </td>
                                <td><input type="url" name="aiseo_link_ops[<?php echo $i; ?>][prospect_url]" value="<?php echo esc_attr($row['prospect_url']); ?>" style="width:100%"></td>
                                <td>
                                  <select name="aiseo_link_ops[<?php echo $i; ?>][status]">
                                      <?php foreach (['new','pitched','live','rejected'] as $st): ?>
                                          <option <?php selected($row['status'],$st); ?>><?php echo esc_html($st); ?></option>
                                      <?php endforeach; ?>
                                  </select>
                                </td>
                                <td><textarea name="aiseo_link_ops[<?php echo $i; ?>][notes]" rows="2" style="width:100%"><?php echo esc_textarea($row['notes']); ?></textarea></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><button class="button" id="aiseo-add-link-row">+ Add Row</button> <?php submit_button('Save Tracker','secondary','','',false); ?></p>
                </form>
            </div>

            <!-- Tools -->
            <div class="aiseo-tab-panel" id="tab-tools">
                <h2>Tools</h2>
                <p><a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank" class="button">View Sitemap</a>
                <button class="button" id="aiseo-rebuild-sitemap">Rebuild Sitemap</button></p>
                <div id="aiseo-tools-log"></div>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        $s = get_option('aiseo_settings', []);
        $key = get_option('aiseo_openai_key','');
        ?>
        <div class="wrap">
            <h1>AI SEO Agent — Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aiseo_group'); ?>
                <h2>AI</h2>
                <p><input type="password" name="aiseo_openai_key" style="width:420px" value="<?php echo esc_attr($key); ?>" placeholder="OpenAI API Key"></p>
                <h2>Schema (Pages)</h2>
                <?php foreach (['webpage','faq','geo','ai_overview'] as $k): ?>
                    <label><input type="checkbox" name="aiseo_settings[schemas][<?php echo $k; ?>]" value="1" <?php checked(!empty($s['schemas'][$k])); ?>> <?php echo esc_html(ucfirst(str_replace('_',' ',$k))); ?></label><br>
                <?php endforeach; ?>
                <h2>Sitemap</h2>
                <label><input type="checkbox" name="aiseo_settings[sitemap]" value="1" <?php checked(!empty($s['sitemap'])); ?>> Enable sitemap (/sitemap.xml, pages only)</label>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    /** Meta box for PAGES only */
    public function meta_box_pages_only() {
        add_meta_box('aiseo_meta','AI SEO Agent (Page) — Focus Keyword',[$this,'render_page_meta'],['page'],'side','high');
    }
    public function render_page_meta($post) {
        $focus = get_post_meta($post->ID, '_aiseo_focus_keyword', true);
        wp_nonce_field('aiseo_save_page','aiseo_nonce_page');
        echo '<p><input type="text" name="aiseo_focus_keyword" value="'.esc_attr($focus).'" placeholder="Focus keyword" style="width:100%"></p>';
    }
    public function save_page_meta($post_id, $post) {
        if (!isset($_POST['aiseo_nonce_page']) || !wp_verify_nonce($_POST['aiseo_nonce_page'],'aiseo_save_page')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_page', $post_id)) return;
        if (isset($_POST['aiseo_focus_keyword'])) {
            update_post_meta($post_id,'_aiseo_focus_keyword',sanitize_text_field($_POST['aiseo_focus_keyword']));
        }
    }

    public function dashboard_widget() {
        wp_add_dashboard_widget('aiseo_dashboard','AI SEO Health (Pages)', [$this,'render_widget']);
    }
    public function render_widget() {
        $s = get_option('aiseo_settings',[]);
        $last = get_option('aiseo_last_ai_run','Never');
        echo '<ul>';
        echo '<li>📄 Target: Pages only</li>';
        echo '<li>🗺️ Sitemap: '.(!empty($s['sitemap'])?'Enabled ✅':'Disabled ❌').'</li>';
        echo '<li>📊 Schema: '.implode(', ', array_keys(array_filter($s['schemas']??[]))).'</li>';
        echo '<li>🤖 Last AI Optimization: '.esc_html($last).'</li>';
        echo '</ul>';
        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=aiseo-dashboard')).'">Open Dashboard</a></p>';
    }
}
