<?php

class CF_SEO_Brain {
    private $api_key;

    public function __construct($key = null) {
        $this->api_key = $key;
    }

    // ... (Mantener funciones leer_csv y analizar_datos del código anterior) ...
    // ... COPIA AQUÍ leer_csv y analizar_datos del código previo ...

    public function generar_articulo_ia($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec = implode(", ", array_column($analisis['secundarias'], 'keyword'));

        // Instrucción del Sistema para JSON Estricto
        $system_prompt = "Eres un experto SEO para 'CoticeFácil'. 
        Tu respuesta DEBE ser un objeto JSON válido con esta estructura:
        {
            'title': 'Título optimizado (H1)',
            'html_content': 'El cuerpo del artículo en HTML (h2, h3, p, ul). NO incluyas h1 ni body.',
            'image_prompt': 'Una descripción detallada en inglés para DALL-E 3 que represente el tema principal de forma profesional y moderna.'
        }";

        $user_prompt = "Escribe un artículo sobre '$k_main'.
        Estrategia: $estrategia.
        Keywords secundarias: $k_sec.
        
        REGLAS:
        1. En html_content, incluye un CTA que diga 'Solicita tu cotización ahora'.
        2. El tono debe ser profesional y persuasivo.";

        // Llamada a OpenAI (GPT-4o)
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4o', // O 'gpt-3.5-turbo' si quieres ahorrar
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt]
                ],
                'response_format' => ['type' => 'json_object'] // Fuerza JSON
            ]),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->error)) throw new Exception($body->error->message);

        return json_decode($body->choices[0]->message->content);
    }

    public function generar_imagen_ia($prompt) {
        // Llamada a DALL-E 3
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "Professional corporate illustration, minimalist, high quality: " . $prompt,
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) throw new Exception("Error imagen: " . $response->get_error_message());
        
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->error)) throw new Exception("Error API Imagen: " . $body->error->message);

        return $body->data[0]->url;
    }

    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        // Cargar librerías de WP para manejo de medios
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Descargar imagen temporalmente
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) return false;

        $file_array = [
            'name' => sanitize_title($desc) . '.jpg',
            'tmp_name' => $tmp
        ];

        // Insertar en la librería
        $id_img = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id_img)) {
            @unlink($file_array['tmp_name']);
            return false;
        }

        // Asignar como Featured Image
        set_post_thumbnail($post_id, $id_img);
        return true;
    }
}
