<?php

class CF_SEO_Brain {
    private $api_key;

    public function __construct($key = null) {
        $this->api_key = $key;
    }

    public function leer_csv($filepath) {
        // (Mismo código de antes: leer CSV)
        $filas = array_map('str_getcsv', file($filepath));
        array_shift($filas); 
        $datos = [];
        foreach ($filas as $fila) {
            if (count($fila) < 2) continue;
            $datos[] = [
                'keyword' => $fila[0],
                'volumen' => (int) preg_replace('/[^0-9]/', '', $fila[1] ?? 0),
                'cpc' => (float) ($fila[3] ?? 0)
            ];
        }
        return $datos;
    }

    public function analizar_datos($datos, $estrategia) {
        if ($estrategia === 'trafico') {
            usort($datos, function($a, $b) { return $b['volumen'] - $a['volumen']; });
        } else {
            usort($datos, function($a, $b) { return $b['cpc'] <=> $a['cpc']; });
        }
        return ['ganadora' => $datos[0], 'secundarias' => array_slice($datos, 1, 5)];
    }

    // SOLO genera el texto del prompt (Paso 1)
    public function generar_prompt_base($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec = implode(", ", array_column($analisis['secundarias'], 'keyword'));
        
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

    // Ejecuta la llamada a OpenAI (Paso 2)
    public function ejecutar_prompt_texto($prompt) {
        $response = wp_remote_post('[https://api.openai.com/v1/chat/completions](https://api.openai.com/v1/chat/completions)', [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'gpt-4o',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object']
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->error)) throw new Exception($body->error->message);

        return json_decode($body->choices[0]->message->content);
    }

    // Ejecuta DALL-E (Paso 2b)
    public function ejecutar_dall_e($prompt) {
        $response = wp_remote_post('[https://api.openai.com/v1/images/generations](https://api.openai.com/v1/images/generations)', [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "Corporate minimalist style: " . $prompt,
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) throw new Exception("Error DALL-E");
        $body = json_decode(wp_remote_retrieve_body($response));
        return $body->data[0]->url ?? '';
    }

    // Guarda la imagen (Paso 3)
    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) return false;

        $id_img = media_handle_sideload(['name' => sanitize_title($desc).'.jpg', 'tmp_name' => $tmp], $post_id);
        if (!is_wp_error($id_img)) set_post_thumbnail($post_id, $id_img);
    }
}
