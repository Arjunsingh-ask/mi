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
// inside the success callback of aiseo_generate_content
if(res.success){
  var d = res.data || {};
  // render result (keep your existing markup if you like)
  var html =
    "<p><strong>H1:</strong> "+(d.h1||"")+"</p>"+
    (d.outline ? "<p><strong>Outline:</strong> "+d.outline.join(" • ")+"</p>" : "")+
    "<div class='aiseo-gen-body' style='margin:8px 0'>"+(d.body||"")+"</div>"+
    "<p><strong>Meta Title:</strong> "+(d.meta_title||"")+
    "<br><strong>Meta Description:</strong> "+(d.meta_description||"")+
    "<br><strong>Focus Keyword:</strong> "+(d.focus_keyword||"")+"</p>"+
    "<p>" +
      "<button class='button' id='aiseo-apply-draft'>💾 Apply to Draft</button> " +
      "<button class='button button-primary' id='aiseo-apply-publish'>✅ Apply & Publish</button>" +
    "</p>";

  $("#aiseo-gen-output").html(html);

  // stash result for publish clicks
  var payload = {
    meta_title: d.meta_title || "",
    meta_description: d.meta_description || "",
    focus_keyword: d.focus_keyword || "",
    body: d.body || ""
  };
  $("#aiseo-gen-output").data("aiseo-payload", payload);

  // click handlers (use one function to avoid duplicate code)
  function applyGenerated(publishFlag){
    var page_id = $("#aiseo_gen_page").val();
    var saved   = $("#aiseo-gen-output").data("aiseo-payload");
    if(!page_id || !saved){ return; }
    $("#aiseo-gen-output").append("<p>⏳ Applying...</p>");
    $.post(aiseo_ai.ajax, {
      action: "aiseo_apply_generated",
      nonce: aiseo_ai.nonce,
      page_id: page_id,
      meta_title: saved.meta_title,
      meta_description: saved.meta_description,
      focus_keyword: saved.focus_keyword,
      body: saved.body,
      publish: publishFlag ? 1 : 0
    }, function(r){
      if(r && r.success){
        $("#aiseo-gen-output").append("<p>✅ Saved"+(publishFlag?" & published":"")+" successfully.</p>");
      } else {
        $("#aiseo-gen-output").append("<p>❌ "+(r && r.data ? r.data : "Error")+"</p>");
      }
    }).fail(function(xhr){
      $("#aiseo-gen-output").append("<p>❌ Request failed ("+xhr.status+")</p>");
    });
  }

  $("#aiseo-apply-draft").off("click").on("click", function(){ applyGenerated(false); });
  $("#aiseo-apply-publish").off("click").on("click", function(){ applyGenerated(true); });

} else {
  $("#aiseo-gen-output").html("❌ "+(res.data || "Error"));
}


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

