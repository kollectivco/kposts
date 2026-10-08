/* Kontentainment AI Writer v4 */
(function ($) {
    'use strict';

    function parseArticleParts(text){
        text = (text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
        var title = '', tagline = '', body = text;

        var titleMatch = body.match(/^\s*(?:\*{1,2}|#{1,6}\s*)?(?:TITLE|العنوان):\s*(?:\*{1,2})?\s*(.+?)(?:\*{1,2})?\s*$/im);
        if(titleMatch){
            title = titleMatch[1].trim();
            body = body.replace(/^\s*(?:\*{1,2}|#{1,6}\s*)?(?:TITLE|العنوان):\s*.+?$\n?/im, '');
        }

        var taglineMatch = body.match(/^\s*(?:\*{1,2}|#{1,6}\s*)?(?:TAGLINE|العنوان الفرعي):\s*(?:\*{1,2})?\s*(.+?)(?:\*{1,2})?\s*$/im);
        if(taglineMatch){
            tagline = taglineMatch[1].trim();
            body = body.replace(/^\s*(?:\*{1,2}|#{1,6}\s*)?(?:TAGLINE|العنوان الفرعي):\s*.+?$\n?/im, '');
        }

        body = body.trim();

        if(!title){
            var blocks = body.split(/\n\s*\n/);
            var firstBlock = (blocks.shift() || '').trim();
            var lines = firstBlock.split(/\n/).map(function(l){ return l.trim(); }).filter(Boolean);
            if(lines.length){
                title = lines.shift();
                if(lines.length && !tagline && lines[0].length < 140 && !/[.?!،。]$/.test(lines[0])){
                    tagline = lines.shift();
                }
                if(lines.length){
                    blocks.unshift(lines.join('\n'));
                }
            }
            body = blocks.join('\n\n').trim();
        }

        return { title: title, tagline: tagline, body: body };
    }

    function cleanArticleLabels(s){
        var p = parseArticleParts(s);
        var parts = [];
        if(p.title) parts.push(p.title);
        if(p.tagline) parts.push(p.tagline);
        if(p.body) parts.push(p.body);
        return parts.join('\n\n').trim();
    }

    function countWords(s){ return cleanArticleLabels(s).replace(/^##\s+/gm,'').split(/\s+/).filter(Boolean).length; }

    function arabicDigits(value){
        var digits='٠١٢٣٤٥٦٧٨٩';
        if(typeof value==='string') return value.replace(/[0-9]/g,function(digit){ return digits[digit]; });
        if(Array.isArray(value)) return value.map(arabicDigits);
        if(value && typeof value==='object'){
            Object.keys(value).forEach(function(key){ value[key]=arabicDigits(value[key]); });
        }
        return value;
    }

    function arabicSeoDigits(seo){
        if(!seo) return seo;
        ['title','meta','tags'].forEach(function(key){ if(seo[key]) seo[key]=arabicDigits(seo[key]); });
        return seo;
    }

    function plainArticle(s){
        var p = parseArticleParts(s);
        var parts = [];
        if(p.title) parts.push(p.title);
        if(p.tagline) parts.push(p.tagline);
        if(p.body) parts.push(p.body.replace(/^##\s+/gm, '').trim());
        return parts.join('\n\n').trim();
    }

    function renderArticle($el, article){
        var esc=function(value){ return $('<div>').text(value||'').html(); };
        var parsed = parseArticleParts(article);
        var html = [];

        if(parsed.title){
            html.push('<h1 class="kaw-article-title">'+esc(parsed.title)+'</h1>');
        }
        if(parsed.tagline){
            html.push('<p class="kaw-article-deck">'+esc(parsed.tagline)+'</p>');
        }

        var blocks = parsed.body.split(/\n\s*\n/).filter(Boolean);
        blocks.forEach(function(block){
            block = block.trim();
            var heading = block.match(/^##\s+([^\n]+)(?:\n([\s\S]*))?$/);
            if(heading){
                html.push('<h2>'+esc(heading[1])+'</h2>');
                if(heading[2]) html.push('<p>'+esc(heading[2]).replace(/\n/g,'<br>')+'</p>');
                return;
            }

            html.push('<p>'+esc(block).replace(/\n/g,'<br>')+'</p>');
        });

        $el.html(html.join(''));
    }

    function applyDir($el, text){
        // If text contains Arabic characters, render RTL
        if(/[\u0600-\u06FF]/.test(text)){
            $el.attr('dir','rtl').css('text-align','right');
        } else {
            $el.attr('dir','ltr').css('text-align','left');
        }
    }

    function chipListener(rowId, attr, onChange){
        $('#'+rowId).on('click', '.kaw-chip', function(){
            $('#'+rowId+' .kaw-chip').removeClass('active');
            $(this).addClass('active');
            onChange($(this).data(attr));
        });
    }
    function typeListener(gridId, onChange){
        $('#'+gridId).on('click', '.kaw-type-btn', function(){
            $('#'+gridId+' .kaw-type-btn').removeClass('active');
            $(this).addClass('active');
            onChange($(this).data('type'));
        });
    }

    function renderSEO($box, seo){
        if(!seo){ $box.hide().html(''); return; }
        var esc = function(t){ return $('<div>').text(t||'').html(); };
        $box.show().html(
            '<div class="kaw-seo-title">SEO</div>'+
            '<div class="kaw-seo-row"><span>Title</span><b>'+esc(seo.title)+'</b></div>'+
            '<div class="kaw-seo-row"><span>Meta</span><b>'+esc(seo.meta)+'</b></div>'+
            '<div class="kaw-seo-row"><span>Slug</span><b>'+esc(seo.slug)+'</b></div>'+
            '<div class="kaw-seo-row"><span>Tags</span><b>'+esc(seo.tags)+'</b></div>'
        );
    }

    function doGenerate(params, $btn, $output, onSuccess){
        $btn.prop('disabled', true).html('<span class="kaw-dots"><span>&#9679;</span><span>&#9679;</span><span>&#9679;</span></span>');
        $.ajax({
            url: KAW.ajax_url, method:'POST',
            data: Object.assign({ action:'kaw_generate', nonce:KAW.nonce }, params),
            success: function(res){
                if(res.success) onSuccess(res.data.article, res.data.seo);
                else $output.addClass('kaw-empty').empty().append(
                    $('<span>').addClass('kaw-placeholder').css('color','#c0392b').text('Error: '+(res.data||'Could not generate article.'))
                );
            },
            error: function(){ $output.addClass('kaw-empty').html('<span class="kaw-placeholder" style="color:#c0392b;">Request failed.</span>'); },
            complete: function(){ $btn.prop('disabled', false).text('Write article'); },
        });
    }

    function doInsert(data, $btn){
        $btn.prop('disabled', true).text(data.image_mode==='gemini' ? 'Generating image & creating draft...' : 'Creating draft...');
        var draftWindow=data.image_mode==='gemini' ? window.open('about:blank','_blank') : null;
        $.ajax({
            url: KAW.ajax_url, method:'POST',
            data: Object.assign({ action:'kaw_create_draft', nonce:KAW.nonce }, data),
            success: function(res){
                if(res.success && res.data.edit_url){
                    if(draftWindow) draftWindow.location=res.data.edit_url;
                    else window.open(res.data.edit_url, '_blank');
                    if(res.data.image_error) alert('Draft created, but the featured image could not be added: '+res.data.image_error);
                }
                else {
                    if(draftWindow) draftWindow.close();
                    alert('Error: '+(res.data||'Could not create draft.'));
                }
            },
            error: function(){ if(draftWindow) draftWindow.close(); alert('Request failed.'); },
            complete: function(){ $btn.prop('disabled', false).text('Insert to new post \u2197'); },
        });
    }

    function initWriter(){
        var type='News', tone='Informative', lang='Arabic', lastSeo=null, lastArticle='';
        typeListener('kaw-type-grid', function(v){ type=v; });
        chipListener('kaw-tone-row','tone', function(v){ tone=v; });
        chipListener('kaw-lang-row','lang', function(v){ lang=v; });
        $('#kaw-wordcount').on('input', function(){ $('#kaw-wc-display').text(this.value); });

        $('#kaw-generate-btn').on('click', function(){
            var subject = $('#kaw-subject').val().trim();
            if(!subject){ alert('Please enter a subject.'); return; }
            var articleLang=lang;
            var $output = $('#kaw-output');
            lastArticle='';
            $output.removeClass('kaw-empty').html('<span class="kaw-placeholder">Writing...</span>');
            $('#kaw-copy-btn, #kaw-insert-btn').hide();
            $('#kaw-wc-count').text(''); $('#kaw-seo-box').hide();

            doGenerate({
                type:type, subject:subject, notes:$('#kaw-notes').val().trim(),
                tone:tone, lang:articleLang, wordcount:$('#kaw-wordcount').val(),
                article_content:'', seo: $('#kaw-seo-toggle').is(':checked') ? '1' : '',
            }, $(this), $output, function(article, seo){
                article=arabicDigits(article);
                seo=arabicSeoDigits(seo);
                lastSeo = seo;
                lastArticle=article;
                $output.removeClass('kaw-empty');
                renderArticle($output, article);
                applyDir($output, article);
                $('#kaw-wc-count').text(countWords(article)+' words');
                $('#kaw-copy-btn, #kaw-insert-btn').show();
                renderSEO($('#kaw-seo-box'), seo);
            });
        });

        $('#kaw-copy-btn').on('click', function(){
            navigator.clipboard.writeText(plainArticle(lastArticle)).then(function(){
                var $b=$('#kaw-copy-btn'); $b.text('Copied!'); setTimeout(function(){ $b.text('Copy'); },1500);
            });
        });

        $('#kaw-insert-btn').on('click', function(){
            doInsert({
                content: lastArticle,
                subject: $('#kaw-subject').val().trim(),
                image_url: '',
                image_mode: $('#kaw-image-mode').val(),
                seo_title: lastSeo ? lastSeo.title : '',
                seo_meta:  lastSeo ? lastSeo.meta  : '',
                seo_slug:  lastSeo ? lastSeo.slug  : '',
                seo_tags:  lastSeo ? lastSeo.tags  : '',
            }, $(this));
        });
    }

    function initNewsfeed(){
        var allNews=[], newsErrors=[], activeFilter='all';
        var feedType='News', feedTone='Informative', feedLang='Arabic';
        var selectedItem=null, fetchedContent='', fetchedImage='', lastSeo=null, lastArticle='', fetchRequestId=0;

        typeListener('kaw-feed-type-grid', function(v){ feedType=v; });
        chipListener('kaw-feed-tone-row','tone', function(v){ feedTone=v; });
        chipListener('kaw-feed-lang-row','lang', function(v){ feedLang=v; });
        $('#kaw-feed-wordcount').on('input', function(){ $('#kaw-feed-wc-display').text(this.value); });

        function loadNews(force){
            $('#kaw-news-list').html('<div class="kaw-skeleton"></div>'.repeat(5));
            $.ajax({
                url:KAW.ajax_url, method:'POST', data:{ action:'kaw_fetch_news', nonce:KAW.nonce, force: force ? '1' : '' },
                success:function(res){ if(res.success){ allNews=Array.isArray(res.data)?res.data:(res.data.items||[]); newsErrors=Array.isArray(res.data)?[]:(res.data.errors||[]); renderNews(); } else { $('#kaw-news-list').html('<p style="color:#c0392b;padding:1rem;">Could not load news.</p>'); } },
                error:function(){ $('#kaw-news-list').html('<p style="color:#c0392b;padding:1rem;">Connection failed.</p>'); },
            });
        }

        function renderNews(){
            var items = activeFilter==='all' ? allNews : allNews.filter(function(n){ return n.source===activeFilter; });
            if(!items.length){
                $('#kaw-news-list').html('<p style="padding:1rem;color:#aaa;">No stories found.</p>');
                renderSourceErrors();
                return;
            }
            var html='';
            items.forEach(function(item){
                var idx=allNews.indexOf(item);
                var isSel = (selectedItem && selectedItem === item);
                var sel = isSel ? ' selected' : '';
                html += '<div class="kaw-news-card'+sel+'" data-idx="'+idx+'" role="button" tabindex="0" aria-pressed="'+(isSel?'true':'false')+'">'+
                        '<div class="kaw-card-title">'+$('<div>').text(item.title).html()+'</div>'+
                        '<div class="kaw-card-meta"><span class="kaw-src-tag">'+item.label+'</span></div></div>';
            });
            $('#kaw-news-list').html(html);
            renderSourceErrors();
        }

        function renderSourceErrors(){
            var visibleErrors=activeFilter==='all'?newsErrors:newsErrors.filter(function(error){ return error.source===activeFilter; });
            if(!visibleErrors.length) return;
            var $box=$('<div>').css({margin:'12px 8px',padding:'10px 12px',border:'1px solid #f0c7a8',borderRadius:'6px',background:'#fff8f2',color:'#8a4b20',fontSize:'13px'});
            visibleErrors.forEach(function(error){
                var label=error.label||'Source';
                var message=error.message||'Could not fetch this source.';
                $('<div>').text(label+': '+message).css({margin:'3px 0'}).appendTo($box);
            });
            $('#kaw-news-list').append($box);
        }

        $('#kaw-news-list').on('click', '.kaw-news-card', function(){
            var idx=parseInt($(this).data('idx'),10);
            selectedItem=allNews[idx]; fetchedContent=''; fetchedImage=''; lastSeo=null; lastArticle='';
            $('#kaw-feed-image-mode').val('none').find('option[value="link"]').prop('disabled', true);
            var requestId=++fetchRequestId;

            $('#kaw-news-list .kaw-news-card').removeClass('selected').attr('aria-pressed','false');
            $(this).addClass('selected').attr('aria-pressed','true');

            $('#kaw-sel-title').text(selectedItem.title);
            $('#kaw-sel-link').attr('href', selectedItem.url);
            $('#kaw-pick-prompt').hide(); $('#kaw-write-bar').show();
            $('#kaw-feed-output-panel').hide(); $('#kaw-img-preview').hide();

            var $status=$('#kaw-fetch-status');
            $status.attr('class','kaw-fetch-status kaw-fetch-loading').text('Fetching article content...');

            $.ajax({
                url:KAW.ajax_url, method:'POST',
                data:{ action:'kaw_fetch_article', nonce:KAW.nonce, url:selectedItem.url },
                success:function(res){
                    if(requestId!==fetchRequestId) return;
                    if(res.success && res.data.content){
                        fetchedContent=res.data.content;
                        fetchedImage=res.data.image||'';
                        $status.attr('class','kaw-fetch-status kaw-fetch-ok').text('Content loaded \u2014 '+res.data.length+' chars. Rewriting from source.');
                        if(fetchedImage){
                            $('#kaw-img-tag').attr('src', fetchedImage);
                            $('#kaw-img-preview').show();
                            $('#kaw-feed-image-mode').find('option[value="link"]').prop('disabled', false).end().val('link');
                        }
                    } else {
                        var errMsg = (res && res.data) ? res.data : 'Could not fetch full content';
                        $status.attr('class','kaw-fetch-status kaw-fetch-warn').text(errMsg + ' \u2014 will write from headline only.');
                    }
                },
                error:function(xhr){
                    if(requestId!==fetchRequestId) return;
                    var statusText = (xhr && xhr.status) ? ' (HTTP ' + xhr.status + ')' : '';
                    $status.attr('class','kaw-fetch-status kaw-fetch-warn').text('Could not fetch content' + statusText + ' \u2014 will write from headline only.');
                },
            });
        });
        $('#kaw-news-list').on('keydown', '.kaw-news-card', function(e){
            if(e.key==='Enter' || e.key===' '){ e.preventDefault(); $(this).trigger('click'); }
        });

        $('.kaw-src-btn').on('click', function(){
            $('.kaw-src-btn').removeClass('active'); $(this).addClass('active');
            activeFilter=$(this).data('src'); renderNews();
        });
        $('#kaw-refresh-btn').on('click', function(){ loadNews(true); });

        $('#kaw-feed-generate-btn').on('click', function(){
            if(!selectedItem) return;
            var articleLang=feedLang;
            var $output=$('#kaw-feed-output');
            lastArticle='';
            $('#kaw-feed-output-panel').show();
            $output.html('<span class="kaw-placeholder">Writing from source content...</span>');
            $('#kaw-feed-copy-btn, #kaw-feed-insert-btn').hide();
            lastSeo=null;
            $('#kaw-feed-wc-count').text(''); $('#kaw-feed-seo-box').hide();

            doGenerate({
                type:feedType, subject:selectedItem.title, notes:$('#kaw-feed-notes').val().trim(),
                tone:feedTone, lang:articleLang, wordcount:$('#kaw-feed-wordcount').val(),
                source:selectedItem.label, article_content:fetchedContent,
                seo: $('#kaw-feed-seo-toggle').is(':checked') ? '1' : '',
            }, $(this), $output, function(article, seo){
                article=arabicDigits(article);
                seo=arabicSeoDigits(seo);
                lastSeo=seo;
                lastArticle=article;
                renderArticle($output, article);
                applyDir($output, article);
                $('#kaw-feed-wc-count').text(countWords(article)+' words');
                renderSEO($('#kaw-feed-seo-box'), seo);
                $('#kaw-feed-copy-btn, #kaw-feed-insert-btn').show();
            });
        });

        $('#kaw-feed-copy-btn').on('click', function(){
            navigator.clipboard.writeText(plainArticle(lastArticle)).then(function(){
                var $b=$('#kaw-feed-copy-btn'); $b.text('Copied!'); setTimeout(function(){ $b.text('Copy'); },1500);
            });
        });

        $('#kaw-feed-insert-btn').on('click', function(){
            var imageMode = $('#kaw-feed-image-mode').val();
            doInsert({
                content: lastArticle,
                subject: selectedItem ? selectedItem.title : 'AI Draft',
                image_url: (imageMode==='link' && fetchedImage) ? fetchedImage : '',
                image_mode: imageMode,
                seo_title: lastSeo ? lastSeo.title : '',
                seo_meta:  lastSeo ? lastSeo.meta  : '',
                seo_slug:  lastSeo ? lastSeo.slug  : '',
                seo_tags:  lastSeo ? lastSeo.tags  : '',
            }, $(this));
        });

        loadNews();
    }

    $(function(){
        if(KAW.page==='newsfeed') initNewsfeed();
        else initWriter();
    });

}(jQuery));
