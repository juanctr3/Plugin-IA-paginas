<?php

class CF_SEO_Brain {
    private $api_key;
    
    const URL_CHAT = 'https://api.openai.com/v1/chat/completions';
    const URL_IMAGE = 'https://api.openai.com/v1/images/generations';

    public function __construct($key = null) {
        $this->api_key = trim($key);
    }

    // --- 1. LEER CSV (Igual que antes) ---
    public function leer_csv($filepath) {
        if (!file_exists($filepath)) throw new Exception("El archivo no se pudo cargar.");
        ini_set('auto_detect_line_endings', true);
        $handle = fopen($filepath, "r");
        if (!$handle) throw new Exception("No se pudo abrir el archivo CSV.");

        $datos = []; $header_found = false;
        $idx_keyword = 0; $idx_volumen = 2; $idx_cpc = 6;

        while (($data = fgetcsv($handle, 10000, "\t")) !== FALSE) {
            if (isset($data[0])) $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);
            if (!$header_found) {
                $row_str = implode(" ", array_map('strtolower', $data));
                if (strpos($row_str, 'keyword') !== false || strpos($row_str, 'palabra clave') !== false) {
                    $header_found = true;
                    foreach ($data as $index => $col_name) {
                        $col = strtolower(trim($col_name));
                        if (strpos($col, 'keyword') !== false || strpos($col, 'palabra clave') !== false) $idx_keyword = $index;
                        elseif (strpos($col, 'avg. monthly') !== false || strpos($col, 'promedio de b') !== false) $idx_volumen = $index;
                        elseif (strpos($col, 'high range') !== false || strpos($col, 'intervalo alto') !== false) $idx_cpc = $index;
                    }
                }
                continue;
            }
            if (count($data) <= $idx_cpc) continue;
            $keyword = trim($data[$idx_keyword]);
            if (empty($keyword)) continue;
            $vol = (int) preg_replace('/[^0-9]/', '', $data[$idx_volumen]);
            $cpc = (float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $data[$idx_cpc]));
            $datos[] = ['keyword' => $keyword, 'volumen' => $vol, 'cpc' => $cpc];
        }
        fclose($handle);
        if (empty($datos)) throw new Exception("Datos no encontrados en el CSV de Google Ads.");
        return $datos;
    }

    // --- 2. ANALIZAR DATOS (Igual que antes) ---
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

    /**
     * --- 3. GENERAR PROMPT MAESTRO (¡AQUÍ ESTÁ EL CAMBIO CLAVE!) ---
     * Instrucciones detalladas para un artículo largo y estructurado.
     */
    public function generar_prompt_base($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec_array = array_column($analisis['secundarias'], 'keyword');
        $k_sec = !empty($k_sec_array) ? implode(", ", $k_sec_array) : "Ninguna";
        
        // Prompt de Ingeniería SEO Avanzada
        return "Actúa como un Editor Jefe de SEO y Copywriter Senior para 'CoticeFácil' (Colombia).
TU TAREA: Escribir el mejor artículo en español que exista en internet sobre: '$k_main'. Debe superar a la competencia actual en Google en profundidad y calidad.

DATOS CLAVE:
- Keyword Principal: $k_main
- Keywords Secundarias: $k_sec (intégralas naturalmente en el texto).
- Estrategia/Tono: $estrategia.

REQUISITOS OBLIGATORIOS DEL CONTENIDO (Google Quality E-E-A-T):
1. LONGITUD: Mínimo 1500 palabras. Debe ser un contenido pilar ("pillar page"), exhaustivo.
2. ESTRUCTURA:
   - H1: Título Ganador (debe incluir la keyword principal).
   - Introducción: Enganchadora, plantea el problema del usuario y la solución que ofrece el artículo.
   - Cuerpo: Organiza la información usando múltiples H2 y H3 para sub-secciones. Rompe los bloques de texto.
   - Listas: Usa listas con viñetas (<ul>) o numeradas (<ol>) siempre que sea posible para mejorar la legibilidad.
   - Negritas: Usa <strong> para resaltar las ideas o frases clave más importantes (sin abusar).
   - Conclusión: Resumen y un llamado a la acción (CTA) claro para que coticen en CoticeFácil.
   - Sección FAQ: Incluye al final una sección con H2 \"Preguntas Frecuentes\" y al menos 3 preguntas (H3) y respuestas relevantes sobre el tema.

IMPORTANTE - FORMATO DE RESPUESTA JSON:
Responde SOLO con este objeto JSON válido (asegúrate de que el campo 'html_content' contenga TODO el artículo largo y estructurado):
{
 \"title\": \"El Título H1 Optimizado\",
 \"meta_description\": \"Meta descripción persuasiva (<160 chars) con la keyword principal\",
 \"main_keyword\": \"$k_main\",
 \"secondary_keywords\": \"Lista de las keywords secundarias que usaste\",
 \"html_content\": \"<p>Introducción...</p> <h2>Sección 1</h2> <p>...</p> <ul><li>...</li></ul> <h2>Sección 2</h2> ... <h2>FAQ</h2> <h3>Pregunta 1</h3><p>Respuesta</p> ... (Todo el HTML masivo aquí)\",
 \"image_prompt\": \"Prompt detallado para DALL-E estilo corporativo moderno\"
}";
    }

    // --- 4. EJECUTAR TEXTO (Aumentado el timeout para artículos largos) ---
    public function ejecutar_prompt_texto($prompt) {
        if (empty($this->api_key)) throw new Exception("Falta la API Key.");

        $response = wp_remote_post(self::URL_CHAT, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'gpt-4o', 
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                // IMPORTANTE: Permitir respuesta máxima de tokens
                'max_tokens' => 4000 
            ]),
            // IMPORTANTE: Timeout de 3 minutos porque escribir 1500 palabras tarda
            'timeout' => 180 
        ]);

        if (is_wp_error($response)) throw new Exception("Error OpenAI: " . $response->get_error_message());
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->error)) throw new Exception("API Error: " . $body->error->message);
        if (!isset($body->choices[0]->message->content)) throw new Exception("Respuesta vacía de IA.");
        return json_decode($body->choices[0]->message->content);
    }

    // --- 5. EJECUTAR IMAGEN (Igual que antes) ---
    public function ejecutar_dall_e($prompt) {
        $response = wp_remote_post(self::URL_IMAGE, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode(['model' => 'dall-e-3', 'prompt' => "Corporate minimalist style: ".substr($prompt, 0, 900), 'n' => 1, 'size' => '1024x1024']),
            'timeout' => 90
        ]);
        if (is_wp_error($response)) throw new Exception("Error DALL-E: " . $response->get_error_message());
        $body = json_decode(wp_remote_retrieve_body($response));
        return $body->data[0]->url ?? '';
    }

    // --- 6. GUARDAR IMAGEN (Igual que antes) ---
    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        if (empty($image_url)) return false;
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) return false;
        $id_img = media_handle_sideload(['name' => sanitize_title($desc).'.jpg', 'tmp_name' => $tmp], $post_id);
        if (!is_wp_error($id_img)) set_post_thumbnail($post_id, $id_img);
        return true;
    }
}
