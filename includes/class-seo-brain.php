<?php

class CF_SEO_Brain {
    private $api_key;
    
    // Definimos las URLs aquí arriba para evitar errores de escritura o caracteres ocultos
    const URL_CHAT = 'https://api.openai.com/v1/chat/completions';
    const URL_IMAGE = 'https://api.openai.com/v1/images/generations';

    public function __construct($key = null) {
        $this->api_key = trim($key); // Limpiamos espacios accidentales en la llave
    }

    public function leer_csv($filepath) {
        // CORRECCIÓN CSV: Permitir leer archivos de Mac y Excel antiguo
        ini_set('auto_detect_line_endings', true);

        if (!file_exists($filepath)) {
            throw new Exception("El archivo temporal no se encuentra.");
        }

        $lineas = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (empty($lineas)) {
            throw new Exception("El archivo CSV está totalmente vacío.");
        }

        $datos = [];
        $filas = array_map('str_getcsv', $lineas);

        // CORRECCIÓN HEADER: Solo quitamos la primera fila si parece ser un encabezado (texto)
        // y si hay más de una fila de datos.
        if (count($filas) > 1) {
            // Verificamos si la columna de volumen (índice 1) NO es un número en la primera fila
            $segunda_columna = $filas[0][1] ?? '';
            if (!is_numeric(str_replace(['.', ','], '', $segunda_columna))) {
                array_shift($filas); // Es un encabezado, lo quitamos
            }
        }

        foreach ($filas as $index => $fila) {
            // Mínimo necesitamos la Keyword (0) y Volumen (1)
            if (count($fila) < 2) continue;

            $vol = isset($fila[1]) ? preg_replace('/[^0-9]/', '', $fila[1]) : 0;
            $cpc = isset($fila[3]) ? str_replace(',', '.', $fila[3]) : 0; // Normalizar decimales
            
            $datos[] = [
                'keyword' => trim($fila[0]),
                'volumen' => (int) $vol,
                'cpc' => (float) $cpc
            ];
        }

        if (empty($datos)) {
            throw new Exception("No se pudieron extraer datos válidos del CSV. Verifica que tenga columnas: Keyword, Volumen, Competencia, CPC.");
        }

        return $datos;
    }

    public function analizar_datos($datos, $estrategia) {
        if ($estrategia === 'trafico') {
            usort($datos, function($a, $b) { return $b['volumen'] - $a['volumen']; });
        } else {
            usort($datos, function($a, $b) { return $b['cpc'] <=> $a['cpc']; });
        }
        
        $ganadora = $datos[0];
        $secundarias = count($datos) > 1 ? array_slice($datos, 1, 5) : [];

        return ['ganadora' => $ganadora, 'secundarias' => $secundarias];
    }

    public function generar_prompt_base($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec_array = array_column($analisis['secundarias'], 'keyword');
        $k_sec = !empty($k_sec_array) ? implode(", ", $k_sec_array) : "Ninguna";
        
        return "Actúa como Experto SEO para 'CoticeFácil'.
Escribe un artículo completo sobre: '$k_main'.
Palabras clave secundarias a incluir: $k_sec.
Enfoque del artículo: $estrategia.

IMPORTANTE - FORMATO DE RESPUESTA:
Debes responder ÚNICAMENTE con un objeto JSON válido. No uses bloques de código markdown (```json). El JSON debe tener esta estructura exacta:
{
 \"title\": \"Título H1 Optimizado y Atractivo\",
 \"html_content\": \"<p>Aquí el contenido del artículo en HTML (usa h2, h3, p, ul). Incluye llamados a la acción para cotizar.</p>\",
 \"image_prompt\": \"Descripción detallada en inglés para generar una imagen corporativa y minimalista con DALL-E\"
}";
    }

    public function ejecutar_prompt_texto($prompt) {
        if (empty($this->api_key)) throw new Exception("Falta la API Key de OpenAI.");

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-4o', 
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object']
            ]),
            'timeout' => 120,
            'method'  => 'POST',
            'data_format' => 'body'
        ];

        // Usamos la constante definida arriba para evitar errores de string
        $response = wp_remote_post(self::URL_CHAT, $args);

        if (is_wp_error($response)) {
            throw new Exception("Error de conexión con OpenAI: " . $response->get_error_message());
        }

        $body_str = wp_remote_retrieve_body($response);
        $body = json_decode($body_str);

        if (isset($body->error)) {
            throw new Exception("OpenAI Error: " . $body->error->message);
        }
        
        if (!isset($body->choices[0]->message->content)) {
            throw new Exception("Respuesta vacía o inválida de la IA.");
        }

        return json_decode($body->choices[0]->message->content);
    }

    public function ejecutar_dall_e($prompt) {
        // Recortamos el prompt a 900 chars por seguridad (límite de DALL-E)
        $safe_prompt = substr($prompt, 0, 900);

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "Corporate minimalist style, professional, high quality. " . $safe_prompt,
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'timeout' => 90
        ];

        $response = wp_remote_post(self::URL_IMAGE, $args);

        if (is_wp_error($response)) {
            throw new Exception("Error generando imagen: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->error)) {
            // A veces DALL-E rechaza prompts por política de contenido, capturamos eso
            throw new Exception("DALL-E Error: " . $body->error->message);
        }

        return $body->data[0]->url ?? '';
    }

    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        if (empty($image_url)) return false;

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Descarga segura
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) return false;

        $file_array = [
            'name' => sanitize_title($desc) . '.jpg',
            'tmp_name' => $tmp
        ];

        $id_img = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id_img)) {
            @unlink($file_array['tmp_name']);
            return false;
        }

        set_post_thumbnail($post_id, $id_img);
        return true;
    }
}
