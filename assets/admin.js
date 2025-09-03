jQuery(function($){
  $(".aiseo-ai-optimize").on("click", function(){
    const post_id = $("#post_ID").val();
    $("#aiseo-ai-output").html("⏳ Optimizing...");
    $.post(aiseo_ai.ajax, {
      action: "aiseo_auto_optimize",
      nonce: aiseo_ai.nonce,
      post_id
    }, function(res){
      if(res.success){
        $("#aiseo-ai-output").html("✅ Updated<br><strong>"+res.data.title+"</strong><br><em>"+res.data.description+"</em>");
      } else {
        $("#aiseo-ai-output").html("❌ "+(res.data || 'Error'));
      }
    });
  });

  $("#aiseo-bulk-optimize").on("click", function(){
    $("#aiseo-bulk-log").html("⏳ Running bulk optimization...");
    $.post(aiseo_ai.ajax, {
      action: "aiseo_bulk_optimize",
      nonce: aiseo_ai.nonce
    }, function(res){
      if(res.success){
        $("#aiseo-bulk-log").html("✅ Optimized posts: "+res.data.optimized);
      } else {
        $("#aiseo-bulk-log").html("❌ "+(res.data || 'Error'));
      }
    });
  });
});
