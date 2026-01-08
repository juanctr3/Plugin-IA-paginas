<?php

class CF_SEO_Brain {
    private $api_key;

    public function __construct($key = null) {
        $this->api_key = $key;
    }

    public function leer_csv($filepath) {
        $filas = array_map('str_getcsv', file($filepath));
        array_shift($filas); // Quitar encabezado
        $datos = [];
        foreach ($filas as $fila) {
            if (count($fila) < 2) continue;
            // Aseguramos que existen los índices antes de usarlos para evitar notices
            $vol = isset($fila[1]) ? preg_replace('/[^0-9]/', '', $fila[1]) : 0;
            $cpc = isset($fila[3]) ? $fila[3] : 0;
            
            $datos[] = [
                'keyword' => $fila[0],
                'volumen' => (int) $vol,
                'cpc' => (float) $cpc
            ];
        }
        return $datos;
    }

    public function analizar_datos($datos, $estrategia) {
        if (empty($datos)) throw new Exception("El CSV parece estar vacío o no tiene el formato correcto.");
        
        if ($estrategia === 'trafico') {
            usort($datos, function($a, $b) { return $b['volumen'] - $a['volumen']; });
        } else {
            usort($datos, function($a, $b) { return $b['cpc'] <=> $a['cpc']; });
        }
        // Asegurar que hay suficientes datos para secundarias
        $ganadora = $datos[0];
        $secundarias = count($datos) > 1 ? array_slice($datos, 1, 5) : [];

        return ['ganadora' => $ganadora, 'secundarias' => $secundarias];
    }

    // SOLO genera el texto del prompt (Paso 1)
    public function generar_prompt_base($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec_array = array_column($analisis['secundarias'], 'keyword');
        $k_sec = !empty($k_sec_array) ? implode(", ", $k_sec_array) : "N/A";
        
        return "Actúa como Experto SEO para 'CoticeFácil'.
Escribe un artículo sobre: '$k_main'.
Keywords secundarias: $k_sec.
Objetivo: $estrategia.

REQUISITO TÉCNICO:
Tu respuesta DEBE ser solo un JSON válido (sin markdown ```json) con este formato:
{
 \"title\": \"Título H1 Atractivo\",
 \"html_content\": \"<p>Contenido HTML...</p>\",
 \"image_prompt\": \"Descripción en inglés para DALL-E\"
}";
    }

    // Ejecuta la llamada a OpenAI (Paso 2) - REVISADO URLs
    public function ejecutar_prompt_texto($prompt) {
        $url = '[https://api.openai.com/v1/chat/completions](https://api.openai.com/v1/chat/completions)';
        
        $response = wp_remote_post($url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'gpt-4o', // Asegúrate de tener acceso a gpt-4o, si no usa gpt-3.5-turbo
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object']
            ]),
            'timeout' => 120 // Aumentado a 120 segundos para evitar timeouts en GPT-4
        ]);

        if (is_wp_error($response)) {
             // Esto captura el error "No se ha facilitado una URL válida"
            throw new Exception("Error conexión OpenAI (Texto): " . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->error)) throw new Exception("Error API OpenAI: " . $body->error->message);
        if (!isset($body->choices[0]->message->content)) throw new Exception("Respuesta inesperada de OpenAI.");

        return json_decode($body->choices[0]->message->content);
    }

    // Ejecuta DALL-E (Paso 2b) - REVISADO URLs
    public function ejecutar_dall_e($prompt) {
        $url = '[https://api.openai.com/v1/images/generations](https://api.openai.com/v1/images/generations)';

        $response = wp_remote_post($url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "Corporate minimalist style, professional: " . substr($prompt, 0, 900), // Recortar prompt por si es muy largo
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
             throw new Exception("Error conexión OpenAI (Imagen): " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->error)) throw new Exception("Error API DALL-E: " . $body->error->message);

        return $body->data[0]->url ?? '';
    }

    // Guarda la imagen (Paso 3)
    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        if (empty($image_url)) return false;

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) return false;

        $file_array = ['name' => sanitize_title($desc).'.jpg', 'tmp_name' => $tmp];
        $id_img = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id_img)) {
            @unlink($file_array['tmp_name']); // Limpiar si falla
            return false;
        }

        set_post_thumbnail($post_id, $id_img);
        return true;
    }
}
