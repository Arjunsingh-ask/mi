<?php
if (!defined('ABSPATH')) exit;

class AISEO_Optimizer {
    public function __construct() {
        add_action('admin_footer', [$this, 'analysis_script']);
    }

    public function analysis_script() {
        if (!get_current_screen()->is_block_editor) return;
        ?>
        <script>
        jQuery(document).ready(function($){
            const editor = $("textarea.block-editor-rich-text__editable, #content");
            editor.on("input", function(){
                let text = $(this).val().toLowerCase();
                let words = text.split(/\s+/).filter(w => w.length > 3);
                let wordCount = words.length;
                let sentences = text.split(/[.!?]/).length;
                let avgLen = (wordCount / sentences).toFixed(1);

                $("#aiseo-readability").remove();
                editor.after("<p id='aiseo-readability'>📖 Readability: Avg sentence length " + avgLen + " words</p>");
            });
        });
        </script>
        <?php
    }
}
