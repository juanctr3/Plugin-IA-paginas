<?php

class CF_SEO_Brain {
    private $api_key;
    
    // Constantes para evitar errores de escritura en las URLs
    const URL_CHAT = 'https://api.openai.com/v1/chat/completions';
    const URL_IMAGE = 'https://api.openai.com/v1/images/generations';

    public function __construct($key = null) {
        // Limpiamos la llave de posibles espacios en blanco accidentales
        $this->api_key = trim($key);
    }

    /**
     * 1. LEER CSV / TSV (Compatible con Google Ads)
     * Detecta tabulaciones, saltos de línea extraños y busca la cabecera correcta.
     */
    public function leer_csv($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception("El archivo temporal no se pudo cargar.");
        }

        // Configuración crítica para leer archivos creados en Mac o versiones viejas de Excel
        ini_set('auto_detect_line_endings', true);

        $handle = fopen($filepath, "r");
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV en el servidor.");
        }

        $datos = [];
        $header_found = false;
        
        // Índices por defecto (se sobreescriben al encontrar la cabecera)
        $idx_keyword = 0;
        $idx_volumen = 2; 
        $idx_cpc = 6;     

        // Leemos línea por línea usando TABULADOR (\t) como separador (estándar de Google Ads)
        while (($data = fgetcsv($handle, 10000, "\t")) !== FALSE) {
            
            // Limpieza de caracteres invisibles (BOM) que a veces vienen al inicio del archivo
            if (isset($data[0])) {
                $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);
            }

            // A. BUSCAR LA FILA DE ENCABEZADOS
            if (!$header_found) {
                // Convertimos toda la fila a texto minúscula para buscar palabras clave
                $row_str = implode(" ", array_map('strtolower', $data));
                
                // Si encontramos "keyword" o "palabra clave", sabemos que esta es la fila header
                if (strpos($row_str, 'keyword') !== false || strpos($row_str, 'palabra clave') !== false) {
                    $header_found = true;
                    
                    // Mapeo dinámico: Buscamos en qué columna exacta está cada dato
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
                // Saltamos las líneas anteriores al encabezado (títulos del reporte, fechas, etc.)
                continue; 
            }

            // B. PROCESAR DATOS (Solo si ya pasamos el encabezado)
            
            // Verificamos que la fila tenga suficientes columnas
            if (count($data) <= $idx_cpc) continue;

            $keyword = trim($data[$idx_keyword]);
            if (empty($keyword)) continue;

            // Limpieza de números: "10,000" -> 10000
            $vol_raw = $data[$idx_volumen];
            $vol = (int) preg_replace('/[^0-9]/', '', $vol_raw);
            
            // Limpieza de CPC: "2.500,00" -> 2500.00
            $cpc_raw = $data[$idx_cpc];
            $cpc_clean = preg_replace('/[^0-9,.]/', '', $cpc_raw); // Quitar moneda
            $cpc_clean = str_replace(',', '.', $cpc_clean); // Normalizar decimales
            $cpc = (float) $cpc_clean;

            $datos[] = [
                'keyword' => $keyword,
                'volumen' => $vol,
                'cpc' => $cpc
            ];
        }
        
        fclose($handle);

        if (empty($datos)) {
            throw new Exception("El archivo se leyó pero no se encontraron datos válidos. Verifica que sea un archivo de Google Keyword Planner.");
        }

        return $datos;
    }

    /**
     * 2. ANALIZAR DATOS
     * Ordena las palabras clave según la estrategia elegida por el usuario.
     */
    public function analizar_datos($datos, $estrategia) {
        if ($estrategia === 'trafico') {
            // Estrategia Tráfico: Ordenar por Volumen (Descendente)
            usort($datos, function($a, $b) { return $b['volumen'] - $a['volumen']; });
        } else {
            // Estrategia Leads: Ordenar por CPC / Intención de Compra (Descendente)
            usort($datos, function($a, $b) { return $b['cpc'] <=> $a['cpc']; });
        }

        // La primera es la ganadora
        $ganadora = $datos[0];
        
        // Tomamos hasta 5 palabras secundarias para enriquecer el SEO
        $secundarias = count($datos) > 1 ? array_slice($datos, 1, 5) : [];

        return [
            'ganadora' => $ganadora, 
            'secundarias' => $secundarias
        ];
    }

    /**
     * 3. GENERAR PROMPT MAESTRO (INGENIERÍA SEO AVANZADA)
     * Construye las instrucciones para que GPT-4o escriba un artículo largo y estructurado.
     */
    public function generar_prompt_base($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec_array = array_column($analisis['secundarias'], 'keyword');
        $k_sec = !empty($k_sec_array) ? implode(", ", $k_sec_array) : "Ninguna específica";
        
        return "Actúa como un Editor Jefe de SEO y Copywriter Senior para el portal 'CoticeFácil' (Colombia).
TU MISIÓN: Escribir el artículo más completo y útil que exista en internet sobre: '$k_main'.
OBJETIVO: Posicionar en el Top 1 de Google superando a la competencia en profundidad y calidad (E-E-A-T).

DATOS TÉCNICOS:
- Palabra Clave Principal: $k_main
- Palabras Clave Secundarias (LSI): $k_sec (Debes integrarlas de forma natural en el texto).
- Enfoque del Artículo: $estrategia.

REQUISITOS OBLIGATORIOS DE ESTRUCTURA Y CONTENIDO:
1. LONGITUD EXHAUSTIVA: El artículo debe tener MÍNIMO 1500 palabras. No seas breve. Profundiza.
2. ESTRUCTURA HTML:
   - Título H1: Debe ser irresistible e incluir la keyword principal.
   - Introducción: Plantea el problema del lector y presenta la solución. Usa negritas en la frase clave.
   - Cuerpo del Artículo: Divide el contenido en múltiples secciones con H2 y H3.
   - Listas: Usa obligatoriamente listas con viñetas (<ul>) o numeradas (<ol>) para mejorar la legibilidad.
   - Conclusión: Resumen final y un llamado a la acción (CTA) claro invitando a cotizar en CoticeFácil.
   - Sección FAQ: Al final, agrega un H2 'Preguntas Frecuentes sobre $k_main' con al menos 3 preguntas y respuestas detalladas.

FORMATO DE RESPUESTA JSON:
Tu respuesta debe ser EXCLUSIVAMENTE un objeto JSON válido con esta estructura exacta (sin markdown):
{
 \"title\": \"El Título H1 generado\",
 \"meta_description\": \"Meta descripción persuasiva de menos de 160 caracteres optimizada para CTR\",
 \"main_keyword\": \"$k_main\",
 \"secondary_keywords\": \"Lista de las keywords secundarias que realmente usaste\",
 \"html_content\": \"<p>Aquí va todo el contenido HTML del artículo (intro, h2, h3, listas, faq, cierre)...</p>\",
 \"image_prompt\": \"Descripción detallada en inglés para generar una imagen destacada profesional estilo corporativo minimalista con DALL-E\"
}";
    }

    /**
     * 4. EJECUTAR PROMPT DE TEXTO (GPT-4o)
     * Configurado para tiempos de espera largos y respuestas extensas.
     */
    public function ejecutar_prompt_texto($prompt) {
        if (empty($this->api_key)) throw new Exception("Error: Falta la API Key de OpenAI en la configuración.");

        // Configuración de la petición a OpenAI
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-4o', 
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'], // Forzamos respuesta JSON limpia
                'max_tokens' => 4000, // Permitimos respuesta larga (aprox 3000 palabras)
                'temperature' => 0.7  // Creatividad balanceada
            ]),
            'timeout' => 180, // 3 MINUTOS de espera (vital para artículos largos)
            'method'  => 'POST'
        ];

        $response = wp_remote_post(self::URL_CHAT, $args);

        if (is_wp_error($response)) {
            throw new Exception("Error de conexión con OpenAI: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->error)) {
            throw new Exception("OpenAI API Error: " . $body->error->message);
        }
        
        if (!isset($body->choices[0]->message->content)) {
            throw new Exception("La IA devolvió una respuesta vacía o inválida.");
        }

        return json_decode($body->choices[0]->message->content);
    }

    /**
     * 5. EJECUTAR GENERACIÓN DE IMAGEN (DALL-E 3)
     */
    public function ejecutar_dall_e($prompt) {
        // Recortamos el prompt a 900 caracteres por seguridad (límite de DALL-E)
        $safe_prompt = substr($prompt, 0, 900);

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "Corporate minimalist style, professional, high quality: " . $safe_prompt,
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'timeout' => 90, // DALL-E suele tardar 15-30 segundos, damos 90 por seguridad
            'method'  => 'POST'
        ];

        $response = wp_remote_post(self::URL_IMAGE, $args);

        if (is_wp_error($response)) {
            throw new Exception("Error de conexión con DALL-E: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->error)) {
            throw new Exception("DALL-E API Error: " . $body->error->message);
        }

        // Devolvemos la URL temporal de la imagen
        return $body->data[0]->url ?? '';
    }

    /**
     * 6. GUARDAR IMAGEN EN LA LIBRERÍA DE WORDPRESS
     * Descarga la URL temporal de OpenAI y la convierte en un archivo local en tu servidor.
     */
    public function asignar_imagen_destacada($image_url, $post_id, $desc) {
        if (empty($image_url)) return false;

        // Cargamos las librerías necesarias de WordPress
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Descargamos el archivo a la carpeta temporal del servidor
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return false; // Falló la descarga
        }

        // Preparamos el array del archivo
        $file_array = [
            'name' => sanitize_title($desc) . '.jpg', // Nombre del archivo basado en el título
            'tmp_name' => $tmp
        ];

        // "Sideload": Mueve el archivo temporal a la carpeta /uploads/ de WordPress y crea el registro en la base de datos
        $id_img = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id_img)) {
            @unlink($file_array['tmp_name']); // Borramos el temporal si falló
            return false;
        }

        // Asignamos la imagen como "Imagen Destacada" (Thumbnail) del post
        set_post_thumbnail($post_id, $id_img);
        return true;
    }
}
