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
        if (!file_exists($filepath)) {
            throw new Exception("El archivo no se pudo cargar.");
        }

        // Abrimos el archivo en modo lectura
        $handle = fopen($filepath, "r");
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV.");
        }

        $datos = [];
        $header_found = false;
        
        // Índices por defecto (por si no encontramos headers, usamos la estructura estándar de Google)
        $idx_keyword = 0;
        $idx_volumen = 2; // Usualmente columna C
        $idx_cpc = 6;     // Usualmente Top of page bid (high range)

        // Google Ads usa TABULACIONES (\t) y codificación UTF-16LE a veces. 
        // Intentamos leer línea por línea.
        while (($data = fgetcsv($handle, 10000, "\t")) !== FALSE) {
            
            // 1. Limpieza de caracteres invisibles (BOM) en la primera columna
            $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);

            // 2. BUSCAR ENCABEZADOS: Si aún no encontramos la cabecera, buscamos palabras clave
            if (!$header_found) {
                // Convertimos la fila a minúsculas para buscar mejor
                $row_str = implode(" ", array_map('strtolower', $data));
                
                // Si encontramos "keyword" o "palabra clave", ESTA es la fila de encabezados
                if (strpos($row_str, 'keyword') !== false || strpos($row_str, 'palabra clave') !== false) {
                    $header_found = true;
                    
                    // Mapeo dinámico de columnas (Más robusto)
                    foreach ($data as $index => $col_name) {
                        $col = strtolower(trim($col_name));
                        
                        if (strpos($col, 'keyword') !== false || strpos($col, 'palabra clave') !== false) {
                            $idx_keyword = $index;
                        }
                        // Búsquedas mensuales (Avg. monthly searches / Promedio...)
                        elseif (strpos($col, 'avg. monthly') !== false || strpos($col, 'promedio de b') !== false) {
                            $idx_volumen = $index;
                        }
                        // CPC Alto (Top of page bid (high) / Puja... intervalo alto)
                        elseif (strpos($col, 'high range') !== false || strpos($col, 'intervalo alto') !== false) {
                            $idx_cpc = $index;
                        }
                    }
                }
                continue; // Saltamos la fila de encabezados y las anteriores
            }

            // 3. PROCESAR DATOS (Solo si ya pasamos la cabecera)
            
            // Validar que la fila tenga suficientes columnas
            if (count($data) <= $idx_cpc) continue;

            $keyword = trim($data[$idx_keyword]);
            if (empty($keyword)) continue;

            // Limpiar números (Google usa espacios o comas para miles y comas/puntos para decimales)
            // Ejemplo volumen: "10000" o "10,000" -> 10000
            $vol_raw = $data[$idx_volumen];
            $vol = (int) preg_replace('/[^0-9]/', '', $vol_raw);

            // Ejemplo CPC: "2.500,00" o "2500.00" -> 2500.00
            $cpc_raw = $data[$idx_cpc];
            // Quitamos moneda y espacios
            $cpc_clean = preg_replace('/[^0-9,.]/', '', $cpc_raw);
            // Normalizamos decimales (reemplazar coma decimal por punto si es necesario)
            $cpc_clean = str_replace(',', '.', $cpc_clean); 
            $cpc = (float) $cpc_clean;

            $datos[] = [
                'keyword' => $keyword,
                'volumen' => $vol,
                'cpc' => $cpc
            ];
        }
        
        fclose($handle);

        if (empty($datos)) {
            throw new Exception("El archivo se leyó pero no se encontraron datos válidos. Revisa que sea el archivo 'Keyword Stats' de Google Ads.");
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
