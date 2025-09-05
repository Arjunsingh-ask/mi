jQuery(function($){

  function renderMeta(where, meta){
    $(where).html(
      "<table class='widefat striped'><tbody>"+
      "<tr><td><strong>Meta Title</strong></td><td>"+(meta.meta_title||"")+"</td></tr>"+
      "<tr><td><strong>Meta Description</strong></td><td>"+(meta.meta_description||"")+"</td></tr>"+
      "<tr><td><strong>Focus Keyword</strong></td><td>"+(meta.focus_keyword||"")+"</td></tr>"+
      "</tbody></table>"
    );
  }
  function renderBody(where, html){ $(where).html(html||""); }

  // Fetch current content/meta
  $("#aiseo-fetch-current").on("click", function(){
    var page_id = $("#aiseo_gen_page").val();
    if(!page_id) return alert("Select a page first.");
    $("#aiseo-current-body").html("⏳ Loading…");
    $.post(aiseo_ai.ajax, {action:"aiseo_fetch_page_data", nonce:aiseo_ai.nonce, page_id:page_id}, function(res){
      if(res.success){
        renderMeta("#aiseo-current-meta", res.data.meta);
        renderBody("#aiseo-current-body", res.data.body);
      } else {
        $("#aiseo-current-body").html("❌ "+(res.data||"Error"));
      }
    });
  });

  // Generate humanized unique content
  $("#aiseo-generate-content").on("click", function(){
    var page_id = $("#aiseo_gen_page").val();
    if(!page_id) return alert("Select a page first.");
    $("#aiseo-new-body").html("⏳ Generating…");

    $.post(aiseo_ai.ajax, {
      action:"aiseo_generate_content",
      nonce:aiseo_ai.nonce,
      page_id:page_id,
      prompt: $("#aiseo_gen_prompt").val()
    }, function(res){
      if(res.success){
        var d = res.data||{};
        renderMeta("#aiseo-new-meta", {
          meta_title: d.meta_title||"",
          meta_description: d.meta_description||"",
          focus_keyword: d.focus_keyword||""
        });
        renderBody("#aiseo-new-body", d.body||"");

        // stash payload for publish
        $("#aiseo-new-body").data("aiseo-payload", {
          meta_title: d.meta_title||"",
          meta_description: d.meta_description||"",
          focus_keyword: d.focus_keyword||"",
          body: d.body||""
        });

        $("#aiseo-apply-draft, #aiseo-apply-publish").show();
        $("#aiseo-gen-output").html("✅ Preview ready. Use the buttons to apply.");
      } else {
        $("#aiseo-new-body").html("❌ "+(res.data||"Error"));
      }
    });
  });

  function applyGenerated(publishFlag){
    var page_id = $("#aiseo_gen_page").val();
    var saved   = $("#aiseo-new-body").data("aiseo-payload");
    if(!page_id || !saved) return alert("Generate content first.");
    $("#aiseo-gen-output").html("⏳ Applying…");
    $.post(aiseo_ai.ajax, {
      action:"aiseo_apply_generated",
      nonce:aiseo_ai.nonce,
      page_id:page_id,
      meta_title:saved.meta_title,
      meta_description:saved.meta_description,
      focus_keyword:saved.focus_keyword,
      body:saved.body,
      publish: publishFlag ? 1 : 0
    }, function(r){
      if(r && r.success){
        $("#aiseo-gen-output").html("✅ Saved"+(publishFlag?" & published":"")+" successfully.");
      } else {
        $("#aiseo-gen-output").html("❌ "+(r && r.data ? r.data : "Error"));
      }
    }).fail(function(xhr){
      $("#aiseo-gen-output").html("❌ Request failed ("+xhr.status+")");
    });
  }
  $("#aiseo-apply-draft").on("click", function(){ applyGenerated(false); });
  $("#aiseo-apply-publish").on("click", function(){ applyGenerated(true); });

});
