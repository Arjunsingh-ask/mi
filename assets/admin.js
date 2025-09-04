jQuery(function($){
  // Tabs
  $(".aiseo-tabs .nav-tab").on("click", function(){
    $(".aiseo-tabs .nav-tab").removeClass("nav-tab-active");
    $(this).addClass("nav-tab-active");
    var tab = $(this).data("tab");
    $(".aiseo-tab-panel").hide();
    $("#tab-"+tab).show();
  });

  // Content generator
  $("#aiseo-generate-content").on("click", function(){
    var page_id = $("#aiseo_gen_page").val();
    var prompt  = $("#aiseo_gen_prompt").val();
    $("#aiseo-gen-output").html("⏳ Generating...");
    $.post(aiseo_ai.ajax,{
      action: "aiseo_generate_content",
      nonce: aiseo_ai.nonce,
      page_id: page_id,
      prompt: prompt
    }, function(res){
      if(res.success){
        var d = res.data;
        $("#aiseo-gen-output").html(
          "<p><strong>H1:</strong> "+(d.h1||'')+"</p>"+
          (d.outline?("<p><strong>Outline:</strong> "+d.outline.join(" • ")+"</p>"):"")+
          (d.body?("<div class='aiseo-gen-body'>"+d.body+"</div>"):"")+
          "<p><strong>Meta Title:</strong> "+(d.meta_title||'')+"<br><strong>Meta Description:</strong> "+(d.meta_description||'')+"<br><strong>Focus Keyword:</strong> "+(d.focus_keyword||'')+"</p>"
        );
      } else {
        $("#aiseo-gen-output").html("❌ "+(res.data||'Error'));
      }
    });
  });

  // Bulk optimize pages
  $("#aiseo-bulk-optimize-pages").on("click", function(){
    $("#aiseo-bulk-log").html("⏳ Optimizing pages...");
    $.post(aiseo_ai.ajax, { action:"aiseo_bulk_optimize_pages", nonce:aiseo_ai.nonce }, function(res){
      if(res.success){
        $("#aiseo-bulk-log").html("✅ Optimized pages: "+res.data.optimized);
      } else {
        $("#aiseo-bulk-log").html("❌ "+(res.data||'Error'));
      }
    });
  });

  // Internal link suggestions
  $("#aiseo-suggest-internal").on("click", function(){
    $("#aiseo-internal-output").html("⏳ Thinking...");
    $.post(aiseo_ai.ajax, { action:"aiseo_suggest_internal", nonce:aiseo_ai.nonce }, function(res){
      if(res.success){
        var out = "<table class='widefat striped'><thead><tr><th>From</th><th>To</th><th>Anchor</th></tr></thead><tbody>";
        (res.data.suggestions||[]).forEach(function(s){
          out += "<tr><td><a href='"+(s.from_url||s.from?.url||'#')+"' target='_blank'>"+(s.from_title||s.from?.title||'')+"</a></td><td><a href='"+(s.to_url||s.to?.url||'#')+"' target='_blank'>"+(s.to_title||s.to?.title||'')+"</a></td><td>"+(s.suggested_anchor||s.anchor||'')+"</td></tr>";
        });
        out += "</tbody></table>";
        $("#aiseo-internal-output").html(out);
      } else {
        $("#aiseo-internal-output").html("❌ "+(res.data||'Error'));
      }
    });
  });

  // Rebuild sitemap
  $("#aiseo-rebuild-sitemap").on("click", function(){
    $("#aiseo-tools-log").html("⏳ Rebuilding sitemap...");
    $.post(aiseo_ai.ajax, { action:"aiseo_rebuild_sitemap", nonce:aiseo_ai.nonce }, function(res){
      if(res.success){
        $("#aiseo-tools-log").html("✅ "+res.data.message);
      } else {
        $("#aiseo-tools-log").html("❌ "+(res.data||'Error'));
      }
    });
  });

  // Link tracker add row
  $("#aiseo-add-link-row").on("click", function(e){
    e.preventDefault();
    var $last = $("#aiseo-link-rows tr:last");
    var idx = $("#aiseo-link-rows tr").length;
    var clone = $last.clone();
    clone.find("select, input, textarea").each(function(){
      var name = $(this).attr("name");
      if(!name) return;
      name = name.replace(/\[\d+\]/, "["+idx+"]");
      $(this).attr("name", name);
      if(this.tagName === "TEXTAREA") $(this).val("");
      if(this.tagName === "INPUT") $(this).val("");
      if(this.tagName === "SELECT") $(this).prop("selectedIndex",0);
    });
    $("#aiseo-link-rows").append(clone);
  });
});
wp_localize_script('aiseo-admin','aiseo_ai',[
  'ajax'  => admin_url('admin-ajax.php'),
  'nonce' => wp_create_nonce('aiseo_ai'),
]);

