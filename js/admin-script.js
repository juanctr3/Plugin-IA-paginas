jQuery(document).ready(function($) {
    
    // PASO 1: Analizar
    $('#btn-analizar').click(function(e) {
        e.preventDefault();
        var file = $('#csv_file').prop('files')[0];
        if (!file) { alert("Sube un CSV primero"); return; }

        var fd = new FormData();
        fd.append('csv', file);
        fd.append('estrategia', $('#estrategia').val());
        fd.append('action', 'cf_step_1_analizar');
        fd.append('nonce', cf_ajax.nonce);

        $('#loader-1').show();
        $('#btn-analizar').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url, type: 'POST', contentType: false, processData: false, data: fd,
            success: function(res) {
                $('#loader-1').hide();
                $('#btn-analizar').prop('disabled', false);
                if (res.success) {
                    $('#prompt_area').val(res.data.prompt);
                    $('#step-1').hide(); $('#step-2').fadeIn();
                } else { alert(res.data); }
            }
        });
    });

    // PASO 2: Generar (TEXTO + IMAGEN + SEO)
    function generar(conImg) {
        var p = $('#prompt_area').val();
        $('#loader-2').show();
        $('#btn-gen-completo, #btn-gen-texto').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url, type: 'POST',
            data: { 
                action: 'cf_step_2_preview', 
                nonce: cf_ajax.nonce, 
                prompt: p, 
                usar_imagen: conImg 
            },
            success: function(res) {
                $('#loader-2').hide();
                $('#btn-gen-completo, #btn-gen-texto').prop('disabled', false);

                if (res.success) {
                    var d = res.data;
                    
                    // LLENAR CAJA SEO (NUEVO)
                    $('#seo-main-kw').val(d.main_keyword || '');
                    $('#seo-sec-kw').val(d.secondary_keywords || '');
                    $('#seo-meta-desc').val(d.meta_description || '');

                    // LLENAR VISUAL
                    $('#preview-title').text(d.title);
                    $('#preview-content').html(d.html);
                    
                    if (d.img_url) {
                        $('#preview-img').attr('src', d.img_url).show();
                        $('#no-img-msg').hide();
                    } else {
                        $('#preview-img').hide();
                        $('#no-img-msg').show();
                    }

                    $('#step-2').hide(); $('#step-3').fadeIn();
                } else { alert("Error IA: " + res.data); }
            },
            error: function() { 
                alert("Error de conexión. Intenta de nuevo."); 
                $('#loader-2').hide();
                $('#btn-gen-completo').prop('disabled', false);
            }
        });
    }

    $('#btn-gen-completo').click(function() { generar(true); });
    $('#btn-gen-texto').click(function() { generar(false); });

    // PASO 3: Publicar
    $('#btn-publish').click(function() {
        var t = $('#preview-title').text();
        var c = $('#preview-content').html();
        var i = $('#preview-img').attr('src');
        if ($('#preview-img').css('display') == 'none') i = '';

        $('#loader-3').show();
        $(this).prop('disabled', true);

        $.ajax({
            url: cf_ajax.url, type: 'POST',
            data: { action: 'cf_step_3_publish', nonce: cf_ajax.nonce, title: t, content: c, img_url: i },
            success: function(res) {
                $('#loader-3').hide();
                if (res.success) {
                    $('#success-msg').html('✅ <strong>Publicado.</strong> <a href="'+res.data.edit_link+'" target="_blank">Editar Página</a> para pegar los datos SEO.').show();
                    $('#btn-publish').hide();
                } else { 
                    alert(res.data); 
                    $('#btn-publish').prop('disabled', false); 
                }
            }
        });
    });
});
