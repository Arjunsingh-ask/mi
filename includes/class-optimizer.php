<?php
if (!defined('ABSPATH')) exit;

class AISEO_Optimizer {
    public function __construct() {
        add_action('admin_footer', [$this, 'readability']);
    }
    public function readability() {
        ?>
        <script>
        jQuery(function($){
          const $t = $("#content");
          if(!$t.length) return;
          $t.on("input", function(){
            const text = $(this).val().toLowerCase();
            const words = text.split(/\s+/).filter(w=>w.length>3);
            const sentences = text.split(/[.!?]+/).filter(Boolean).length || 1;
            const avg = (words.length / sentences).toFixed(1);
            $("#aiseo-readability").remove();
            $t.after("<p id='aiseo-readability'>📖 Avg sentence length: "+avg+" words</p>");
          });
        });
        </script>
        <?php
    }
}
