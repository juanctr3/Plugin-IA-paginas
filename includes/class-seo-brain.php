<?php

class CF_SEO_Brain {
    private $api_key;
    
    // Definimos las URLs como constantes para evitar errores de escritura o caracteres ocultos
    const URL_CHAT = 'https://api.openai.com/v1/chat/completions';
    const URL_IMAGE = 'https://api.openai.com/v1/images/generations';

    public function __construct($key = null) {
        $this->api_key = trim($key); // Limpiamos espacios accidentales en la llave
    }

    /**
     * 1. LEER CSV/TSV
     * Detecta automáticamente si es un CSV normal o el archivo TSV exportado de Google Ads.
     */
    public function leer_csv($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception("El archivo temporal no se encuentra.");
        }

        // Permitir leer saltos de línea antiguos (Mac/Excel)
        ini_set('auto_detect_line_endings', true);

        $handle = fopen($filepath, "r");
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV.");
        }

        $datos = [];
        $header_found = false;
        
        // Índices por defecto (backup)
        $idx_keyword = 0;
        $idx_volumen = 2; 
        $idx_cpc = 6;     

        // Leemos línea por línea buscando tabulaciones (\t) típicas de Google Ads
        while (($data = fgetcsv($handle, 10000, "\t")) !== FALSE) {
            
            // Limpieza de caracteres invisibles (BOM) en la primera columna
            if (isset($data[0])) {
                $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);
            }

            // A. BUSCAR ENCABEZADOS
            if (!$header_found) {
                $row_str = implode(" ", array_map('strtolower', $data));
                
                // Si encontramos "keyword" o "palabra clave", esta es la fila de encabezados
                if (strpos($row_str, 'keyword') !== false || strpos($row_str, 'palabra clave') !== false) {
                    $header_found = true;
                    
                    // Mapeo dinámico de columnas
                    foreach ($data as $index => $col_name) {
                        $col = strtolower(trim($col_name));
                        if (strpos($col, 'keyword') !== false || strpos($col, 'palabra clave') !== false) {
                            $idx_keyword = $index;
                        }
                        elseif (strpos($col, 'avg. monthly') !== false || strpos($col, 'promedio de b') !== false) {
                            $idx_volumen = $index;
                        }
                        elseif (strpos($col, 'high range') !== false || strpos($col, 'intervalo alto') !== false) {
                            $idx_cpc = $index;
                        }
                    }
                }
                continue; // Saltamos la fila de encabezados
            }

            // B. PROCESAR DATOS
            if (count($data) <= $idx_cpc) continue;

            $keyword = trim($data[$idx_keyword]);
            if (empty($keyword)) continue;

            // Limpiar números (10,000 -> 10000)
            $vol = (int) preg_replace('/[^0-9]/', '', $data[$idx_volumen]);
            
            // Limpiar CPC (2.500,00 -> 2500.00)
            $cpc_clean = preg_replace('/[^0-9,.]/', '', $data[$idx_cpc]);
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
            throw new Exception("No se encontraron datos válidos. Asegúrate de subir el archivo 'Keyword Stats' original de Google Ads.");
        }

        return $datos;
    }

    /**
     * 2. ANALIZAR DATOS
     * Ordena el array según la estrategia (Tráfico vs Leads)
     */
    public function analizar_datos($datos, $estrategia) {
        if ($estrategia === 'trafico') {
            // Mayor volumen primero
            usort($datos, function($a, $b) { return $b['volumen'] - $a['volumen']; });
        } else {
            // Mayor CPC (intención de compra) primero
            usort($datos, function($a, $b) { return $b['cpc'] <=> $a['cpc']; });
        }

        $ganadora = $datos[0];
        $secundarias = count($datos) > 1 ? array_slice($datos, 1, 5) : [];

        return [
            'ganadora' => $ganadora, 
            'secundarias' => $secundarias
        ];
    }

    /**
     * 3. GENERAR PROMPT BASE
     * Crea las instrucciones para la IA pidiendo JSON con campos SEO.
     */
    public function generar_prompt_base($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec_array = array_column($analisis['secundarias'], 'keyword');
        $k_sec = !empty($k_sec_array) ? implode(", ", $k_sec_array) : "Ninguna";
        
        return "Actúa como Experto SEO Senior para 'CoticeFácil'.
        
OBJETIVO: Crear un artículo perfectamente optimizado para la keyword: '$k_main'.
ESTRATEGIA DE NEGOCIO: $estrategia.

INSTRUCCIONES DE REDACCIÓN SEO:
1. Keyword Principal ('$k_main'): Debe aparecer en el H1, en el primer párrafo (negrita), y en al menos un H2.
2. Keywords Secundarias ($k_sec): Deben integrarse de forma natural en el contenido.
3. Meta Descripción: Crea un resumen persuasivo de menos de 160 caracteres.

IMPORTANTE - FORMATO DE RESPUESTA JSON:
Responde SOLO con este objeto JSON válido (sin markdown):
{
 \"title\": \"Título H1 Optimizado\",
 \"meta_description\": \"Meta descripción para SEO (máx 160 chars)\",
 \"main_keyword\": \"$k_main\",
 \"secondary_keywords\": \"Lista de keywords secundarias usadas separadas por coma\",
 \"html_content\": \"<p>El artículo en HTML (h2, h3, p, ul). NO incluyas h1 ni body.</p>\",
 \"image_prompt\": \"Prompt detallado para DALL-E (estilo corporativo moderno)\"
}";
    }

    /**
     * 4. EJECUTAR PROMPT (TEXTO - GPT-4o)
     */
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
                'response_format' => ['type' => 'json_object'] // Forzamos JSON
            ]),
            'timeout' => 120, // 2 minutos para redacción larga
            'method'  => 'POST'
        ];

        $response = wp_remote_post(self::URL_CHAT, $args);

        if (is_wp_error($response)) {
            throw new Exception("Error conexión OpenAI: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->error)) {
            throw new Exception("OpenAI API Error: " . $body->error->message);
        }
        
        if (!isset($body->choices[0]->message->content)) {
            throw new Exception("La IA devolvió una respuesta vacía.");
        }

        return json_decode($body->choices[0]->message->content);
    }

    /**
     * 5. EJECUTAR IMAGEN (DALL-E 3)
     */
    public function ejecutar_dall_e($prompt) {
        $safe_prompt = substr($prompt, 0, 900); // Límite de caracteres

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "Professional corporate minimalist style: " . $safe_prompt,
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'timeout' => 90, // DALL-E es lento
            'method'  => 'POST'
        ];

        $response = wp_remote_post(self::URL_IMAGE, $args);

        if (is_wp_error($response)) {
            throw new Exception("Error conexión DALL-E: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->error)) {
            throw new Exception("DALL-E API Error: " . $body->error->message);
        }

        return $body->data[0]->url ?? '';
    }

    /**
     * 6. GUARDAR IMAGEN EN WORDPRESS
     */
    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        if (empty($image_url)) return false;

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Descarga segura del archivo temporal
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) return false;

        $file_array = [
            'name' => sanitize_title($desc) . '.jpg',
            'tmp_name' => $tmp
        ];

        // "Sideload" mueve el archivo a la carpeta uploads de WP
        $id_img = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id_img)) {
            @unlink($file_array['tmp_name']); // Borrar basura si falla
            return false;
        }

        set_post_thumbnail($post_id, $id_img);
        return true;
    }
}
