jQuery(document).ready(function($) {
    // Real-time keyword analysis, readability, snippet preview, FAQ builder logic, slug update
    const $metaBox = $('#ai-seo-agent-meta-box');
    if ($metaBox.length) {
        let $keyword = $metaBox.find('input[name="ai_seo_agent[focus_keyword]"]');
        let $snippet = $metaBox.find('#ai-seo-snippet-preview');
        let $slugBtn = $metaBox.find('#ai-seo-update-slug');

        function updateSnippet() {
            let title = $('#title').val();
            let desc  = $('#excerpt').val() || '';
            let slug  = title.toLowerCase().replace(/[^\w\s]/gi, '').replace(/\s+/g, '-');
            $snippet.html('<strong>' + title + '</strong><br><em>' + window.location.origin + '/' + slug + '</em><br>' + desc);
        }

        $keyword.on('input', function() {
            // Simulate keyword density and suggestions
            let content = $('#content').val() || '';
            let kw = $(this).val().toLowerCase();
            let count = (content.toLowerCase().match(new RegExp(kw, 'g')) || []).length;
            let density = count / (content.split(/\s+/).length || 1);
            $metaBox.find('#ai-seo-keyword-density').text('Keyword density: ' + (density * 100).toFixed(2) + '%');
            // TODO: Fetch suggestions from Google Trends API or free alternative
        });

        $('#title, #excerpt').on('input', updateSnippet);
        updateSnippet();

        $slugBtn.on('click', function() {
            let kw = $keyword.val();
            if (kw) {
                let slug = kw.toLowerCase().replace(/[^\w\s]/gi, '').replace(/\s+/g, '-');
                $('#post_name, #editable-post-name-full').text(slug);
                alert('Slug updated to ' + slug);
            }
        });

        // FAQ builder: add new pair
        $metaBox.on('click', '.add-faq-pair', function() {
            $(this).before('<div class="faq-pair"><input type="text" name="ai_seo_agent[faq][]" value="" /></div>');
        });
    }
});
