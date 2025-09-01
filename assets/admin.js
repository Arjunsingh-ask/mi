jQuery(document).ready(function($){
  $(".aiseo-ai-optimize").click(function(){
    let post_id = $("#post_ID").val();
    $("#aiseo-ai-output").html("⏳ Optimizing...");
    $.post(aiseo_ai.ajax,{
      action:"aiseo_auto_optimize",
      nonce:aiseo_ai.nonce,
      post_id:post_id
    },function(res){
      if(res.success){
        $("#aiseo-ai-output").html("✅ " + res.data.title + "<br><em>"+res.data.description+"</em>");
      } else {
        $("#aiseo-ai-output").html("❌ " + res.data);
      }
    });
  });

  $("#aiseo-bulk-optimize").click(function(){
    $("#aiseo-bulk-log").html("⏳ Running bulk optimization...");
    $.post(aiseo_ai.ajax,{action:"aiseo_bulk_optimize",nonce:aiseo_ai.nonce},function(res){
      if(res.success){
        $("#aiseo-bulk-log").html("✅ Optimized posts: " + res.optimized.length);
      } else {
        $("#aiseo-bulk-log").html("❌ Error");
      }
    });
  });
});
