jQuery(document).ready(function($) {
    
    // --- GESTIÓN DE UI (PESTAÑAS Y SELECTORES) ---
    var activeMode = 'csv'; // Por defecto

    // Cambio de Pestañas
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        if ($(this).attr('id') === 'tab-csv') {
            $('#panel-csv').show();
            $('#panel-manual').hide();
            activeMode = 'csv';
        } else {
            $('#panel-csv').hide();
            $('#panel-manual').show();
            activeMode = 'manual';
        }
    });

    // Mostrar Categorías solo si es "Entrada de Blog"
    $('#post_type_selector').change(function() {
        if ($(this).val() === 'post') {
            $('#category_row').fadeIn();
        } else {
            $('#category_row').fadeOut();
        }
    });

    // --- PASO 1: GENERAR PROMPT (Dual Mode) ---
    $('#btn-analizar').click(function(e) {
        e.preventDefault();
        
        var estrategia = $('#estrategia').val();
        var form_data = new FormData();
        
        form_data.append('action', 'cf_step_1_analizar');
        form_data.append('nonce', cf_ajax.nonce);
        form_data.append('mode', activeMode);
        form_data.append('estrategia', estrategia);

        // Lógica según el modo
        if (activeMode === 'csv') {
            var file = $('#csv_file').prop('files')[0];
            if (!file) { alert("⚠️ Sube un CSV primero."); return; }
            form_data.append('csv', file);
        } else {
            // Modo Manual
            var main_kw = $('#manual_main_kw').val();
            var sec_kw = $('#manual_sec_kw').val();
            var extra_prompt = $('#manual_prompt').val();

            if (!main_kw) { alert("⚠️ Debes escribir una Palabra Clave Principal."); return; }
            
            form_data.append('manual_main_kw', main_kw);
            form_data.append('manual_sec_kw', sec_kw);
            form_data.append('manual_extra_prompt', extra_prompt);
        }

        $('#loader-1').show();
        $('#btn-analizar').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url, type: 'POST', contentType: false, processData: false, data: form_data,
            success: function(res) {
                $('#loader-1').hide();
                $('#btn-analizar').prop('disabled', false);
                if (res.success) {
                    $('#prompt_area').val(res.data.prompt);
                    $('#step-1').slideUp();
                    $('#step-2').fadeIn();
                } else { alert("❌ Error: " + res.data); }
            }
        });
    });

    // --- PASO 2: GENERAR CONTENIDO (Igual que antes) ---
    function generarContenido(conImg) {
        var p = $('#prompt_area').val();
        $('#loader-2').show();
        $('#btn-gen-completo, #btn-gen-texto').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url, type: 'POST',
            data: { action: 'cf_step_2_preview', nonce: cf_ajax.nonce, prompt: p, usar_imagen: conImg },
            success: function(res) {
                $('#loader-2').hide();
                $('#btn-gen-completo, #btn-gen-texto').prop('disabled', false);

                if (res.success) {
                    var d = res.data;
                    $('#seo-main-kw').val(d.main_keyword || '');
                    $('#seo-sec-kw').val(d.secondary_keywords || '');
                    $('#seo-meta-desc').val(d.meta_description || '');
                    $('#preview-title').text(d.title);
                    $('#preview-content').html(d.html);
                    
                    if (d.img_url) { $('#preview-img').attr('src', d.img_url).show(); } 
                    else { $('#preview-img').hide(); }

                    $('#step-2').slideUp(); $('#step-3').fadeIn();
                } else { alert("❌ Error IA: " + res.data); }
            },
            error: function() { 
                $('#loader-2').hide(); $('#btn-gen-completo').prop('disabled', false);
                alert("Timeout: El artículo es muy largo. Intenta de nuevo."); 
            }
        });
    }
    $('#btn-gen-completo').click(function() { generarContenido(true); });
    $('#btn-gen-texto').click(function() { generarContenido(false); });

    // --- PASO 3: PUBLICAR (Con Post Type y Categoría) ---
    $('#btn-publish').click(function() {
        var t = $('#preview-title').text();
        var c = $('#preview-content').html();
        var i = $('#preview-img').attr('src');
        if ($('#preview-img').css('display') == 'none') i = '';
        
        // Recoger configuración de publicación
        var p_type = $('#post_type_selector').val();
        var p_cat = $('#post_category').val();

        $('#loader-3').show();
        $(this).prop('disabled', true);

        $.ajax({
            url: cf_ajax.url, type: 'POST',
            data: { 
                action: 'cf_step_3_publish', 
                nonce: cf_ajax.nonce, 
                title: t, content: c, img_url: i,
                // Datos nuevos
                post_type: p_type,
                post_category: p_cat
            },
            success: function(res) {
                $('#loader-3').hide();
                if (res.success) {
                    $('#success-msg').html('✅ <strong>¡Publicado!</strong> <a href="'+res.data.edit_link+'" target="_blank" class="button">Ver/Editar</a>').show();
                    $('#btn-publish').hide();
                } else { 
                    alert("Error: " + res.data); 
                    $('#btn-publish').prop('disabled', false); 
                }
            }
        });
    });
});
