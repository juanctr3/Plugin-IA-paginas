jQuery(document).ready(function($) {
    
    // --- PASO 1: Analizar CSV ---
    $('#btn-analizar').click(function(e) {
        e.preventDefault();
        var file_data = $('#csv_file').prop('files')[0];
        var estrategia = $('#estrategia').val();
        
        if (!file_data) { alert("Selecciona un archivo CSV"); return; }

        var form_data = new FormData();
        form_data.append('file', file_data); // Dato crudo
        form_data.append('csv', file_data);  // Para PHP
        form_data.append('estrategia', estrategia);
        form_data.append('action', 'cf_step_1_analizar');
        form_data.append('nonce', cf_ajax.nonce);

        $('#loader-1').show();
        $('#btn-analizar').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url,
            type: 'POST',
            contentType: false,
            processData: false,
            data: form_data,
            success: function(response) {
                $('#loader-1').hide();
                $('#btn-analizar').prop('disabled', false);
                
                if (response.success) {
                    $('#prompt_area').val(response.data.prompt);
                    $('#step-1').hide();
                    $('#step-2').fadeIn();
                } else {
                    alert("Error: " + response.data);
                }
            }
        });
    });

    // --- PASO 2: Generar Preview ---
    function generarContenido(conImagen) {
        var prompt = $('#prompt_area').val();
        
        $('#loader-2').show();
        $('#btn-gen-completo, #btn-gen-texto').prop('disabled', true);

        $.ajax({
            url: cf_ajax.url,
            type: 'POST',
            data: {
                action: 'cf_step_2_preview',
                nonce: cf_ajax.nonce,
                prompt: prompt,
                usar_imagen: conImagen
            },
            success: function(response) {
                $('#loader-2').hide();
                $('#btn-gen-completo, #btn-gen-texto').prop('disabled', false);

                if (response.success) {
                    var data = response.data;
                    
                    // Llenar Preview
                    $('#preview-title').text(data.title);
                    $('#preview-content').html(data.html);
                    
                    if (data.img_url) {
                        $('#preview-img').attr('src', data.img_url).show();
                        $('#no-img-msg').hide();
                    } else {
                        $('#preview-img').hide();
                        $('#no-img-msg').show();
                    }

                    // Mostrar Paso 3
                    $('#step-2').hide();
                    $('#step-3').fadeIn();
                } else {
                    alert("Error IA: " + response.data);
                }
            },
            error: function() {
                alert("Error de conexión o Timeout. Intenta con un prompt más corto.");
                $('#loader-2').hide();
                $('#btn-gen-completo').prop('disabled', false);
            }
        });
    }

    $('#btn-gen-completo').click(function() { generarContenido(true); });
    $('#btn-gen-texto').click(function() { generarContenido(false); });

    // --- PASO 3: Publicar ---
    $('#btn-publish').click(function() {
        var title = $('#preview-title').text();
        var content = $('#preview-content').html(); // Obtenemos el HTML renderizado
        var img_url = $('#preview-img').attr('src'); // La URL temporal de DALL-E

        // Si la imagen está oculta (no se generó), mandamos vacío
        if ($('#preview-img').css('display') == 'none') img_url = '';

        $('#loader-3').show();
        $(this).prop('disabled', true);

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
                    $('#success-msg').html('✅ Publicado correctamente. <a href="'+response.data.edit_link+'" target="_blank">Ver Página</a>').show();
                    $('#btn-publish').hide(); // Evitar doble post
                } else {
                    alert("Error guardando: " + response.data);
                    $('#btn-publish').prop('disabled', false);
                }
            }
        });
    });

});
