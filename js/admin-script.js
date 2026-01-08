jQuery(document).ready(function($) {
    
    // --- PASO 1: ANALIZAR CSV ---
    $('#btn-analizar').click(function(e) {
        e.preventDefault();
        
        var file_data = $('#csv_file').prop('files')[0];
        var estrategia = $('#estrategia').val();
        
        if (!file_data) {
            alert("⚠️ Por favor selecciona un archivo CSV primero.");
            return;
        }

        // Preparamos los datos para enviar (FormData es necesario para archivos)
        var form_data = new FormData();
        form_data.append('csv', file_data);
        form_data.append('estrategia', estrategia);
        form_data.append('action', 'cf_step_1_analizar');
        form_data.append('nonce', cf_ajax.nonce);

        // UI: Mostrar carga
        $('#loader-1').show();
        $('#btn-analizar').prop('disabled', true).text('Analizando...');

        $.ajax({
            url: cf_ajax.url,
            type: 'POST',
            contentType: false,
            processData: false,
            data: form_data,
            success: function(response) {
                $('#loader-1').hide();
                $('#btn-analizar').prop('disabled', false).text('Analizar CSV');
                
                if (response.success) {
                    // Si todo sale bien, pasamos al Paso 2
                    $('#prompt_area').val(response.data.prompt);
                    $('#step-1').slideUp();
                    $('#step-2').fadeIn();
                } else {
                    alert("❌ Error: " + response.data);
                }
            },
            error: function() {
                $('#loader-1').hide();
                $('#btn-analizar').prop('disabled', false).text('Analizar CSV');
                alert("❌ Error de conexión con el servidor.");
            }
        });
    });

    // --- PASO 2: GENERAR CONTENIDO (IA) ---
    
    // Función central para llamar a la IA
    function generarContenido(usarImagen) {
        var prompt_text = $('#prompt_area').val();
        
        if (!prompt_text) {
            alert("El prompt no puede estar vacío.");
            return;
        }

        // UI: Mostrar carga y bloquear botones
        $('#loader-2').show();
        $('#btn-gen-completo, #btn-gen-texto').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url,
            type: 'POST',
            data: {
                action: 'cf_step_2_preview',
                nonce: cf_ajax.nonce,
                prompt: prompt_text,
                usar_imagen: usarImagen
            },
            success: function(response) {
                // UI: Restaurar botones
                $('#loader-2').hide();
                $('#btn-gen-completo, #btn-gen-texto').prop('disabled', false);

                if (response.success) {
                    var data = response.data;
                    
                    // 1. LLENAR CAJA SEO (Datos Meta)
                    // Usamos || '' para evitar que salga "undefined" si la IA olvida un campo
                    $('#seo-main-kw').val(data.main_keyword || '');
                    $('#seo-sec-kw').val(data.secondary_keywords || '');
                    $('#seo-meta-desc').val(data.meta_description || '');

                    // 2. LLENAR PREVISUALIZACIÓN VISUAL
                    $('#preview-title').text(data.title);
                    $('#preview-content').html(data.html);
                    
                    // 3. MANEJAR IMAGEN
                    if (data.img_url) {
                        $('#preview-img').attr('src', data.img_url).show();
                        $('#no-img-msg').hide();
                    } else {
                        $('#preview-img').hide();
                        $('#no-img-msg').show();
                    }

                    // Transición al Paso 3
                    $('#step-2').slideUp();
                    $('#step-3').fadeIn();
                } else {
                    alert("❌ Error IA: " + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#loader-2').hide();
                $('#btn-gen-completo, #btn-gen-texto').prop('disabled', false);
                alert("❌ Error de conexión (Timeout o Servidor). Intenta de nuevo o reduce el prompt.");
                console.log(error);
            }
        });
    }

    // Botón: Generar Todo
    $('#btn-gen-completo').click(function() {
        generarContenido(true);
    });

    // Botón: Solo Texto (Más rápido)
    $('#btn-gen-texto').click(function() {
        generarContenido(false);
    });


    // --- PASO 3: PUBLICAR EN WORDPRESS ---
    $('#btn-publish').click(function() {
        var title = $('#preview-title').text();
        var content = $('#preview-content').html(); 
        var img_url = $('#preview-img').attr('src');
        
        // Si la imagen está oculta (display:none), enviamos vacío
        if ($('#preview-img').css('display') == 'none') {
            img_url = '';
        }

        // UI: Bloquear
        $('#loader-3').show();
        $(this).prop('disabled', true).text('Publicando...');

        $.ajax({
            url: cf_ajax.url,
            type: 'POST',
            data: {
                action: 'cf_step_3_publish',
                nonce: cf_ajax.nonce,
                title: title,
                content: content,
                img_url: img_url
            },
            success: function(response) {
                $('#loader-3').hide();
                
                if (response.success) {
                    // Mostrar mensaje de éxito con enlace
                    $('#success-msg').html(
                        '✅ <strong>¡Publicado con éxito!</strong> <br>' +
                        'Ahora ve a editar para pegar los datos SEO: ' +
                        '<a href="' + response.data.edit_link + '" target="_blank" class="button button-small">Editar Página</a>'
                    ).fadeIn();
                    
                    $('#btn-publish').hide(); // Ocultar botón para evitar duplicados
                } else {
                    alert("❌ Error al guardar: " + response.data);
                    $('#btn-publish').prop('disabled', false).text('🚀 PUBLICAR BORRADOR');
                }
            },
            error: function() {
                $('#loader-3').hide();
                $('#btn-publish').prop('disabled', false).text('🚀 PUBLICAR BORRADOR');
                alert("❌ Error de conexión al publicar.");
            }
        });
    });

});
