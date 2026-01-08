<?php
/*
Plugin Name: CF Auto Page Generator (OpenAI) - V2
Description: Genera borradores de páginas SEO con contexto avanzado e imágenes DALL-E.
Version: 2.0
Author: Tu Nombre
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class CF_AI_Page_Generator {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Redactor AI', 
            'Redactor AI', 
            'manage_options', 
            'cf-ai-generator', 
            array( $this, 'render_admin_page' ), 
            'dashicons-edit', 
            100
        );
    }

    public function register_settings() {
        register_setting( 'cf_ai_group', 'cf_openai_api_key' );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Redactor de Contenidos AI (Modo Borrador)</h1>
            
            <form method="post" action="options.php" style="background:#fff; padding:15px; border:1px solid #ddd; margin-bottom:20px;">
                <?php settings_fields( 'cf_ai_group' ); ?>
                <?php do_settings_sections( 'cf_ai_group' ); ?>
                <label><strong>OpenAI API Key:</strong></label>
                <input type="password" name="cf_openai_api_key" value="<?php echo esc_attr( get_option('cf_openai_api_key') ); ?>" style="width:100%; margin-top:5px;" />
                <?php submit_button('Guardar Key', 'secondary', 'submit', false); ?>
            </form>

            <hr>

            <div style="background:#fff; padding:20px; border:1px solid #ddd; max-width: 800px;">
                <h2>Crear Nuevo Artículo</h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'cf_generate_content', 'cf_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="topic">Título / Servicio:</label></th>
                            <td>
                                <input name="topic" type="text" id="topic" class="regular-text" placeholder="Ej: Servicio de limpieza de fachadas en altura" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="keywords">Palabras Clave (SEO):</label></th>
                            <td>
                                <input name="keywords" type="text" id="keywords" class="regular-text" placeholder="Ej: limpieza vidrios, mantenimiento edificios, cotización rápida">
                                <p class="description">La IA optimizará el texto para estas palabras.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tone">Tono del Texto:</label></th>
                            <td>
                                <select name="tone" id="tone">
                                    <option value="Profesional y Corporativo">Profesional y Corporativo (Recomendado B2B)</option>
                                    <option value="Persuasivo y de Venta">Persuasivo y de Venta</option>
                                    <option value="Informativo y Técnico">Informativo y Técnico</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="generate_page" id="submit" class="button button-primary button-hero" value="Generar Borrador con Vista Previa">
                    </p>
                </form>
            </div>
            
            <?php
            // Procesamiento
            if ( isset( $_POST['generate_page'] ) && check_admin_referer( 'cf_generate_content', 'cf_nonce' ) ) {
                $this->process_generation( 
                    sanitize_text_field( $_POST['topic'] ),
                    sanitize_text_field( $_POST['keywords'] ),
                    sanitize_text_field( $_POST['tone'] )
                );
            }
            ?>
        </div>
        <?php
    }

    private function process_generation( $topic, $keywords, $tone ) {
        $api_key = get_option('cf_openai_api_key');
        if ( empty( $api_key ) ) {
            echo '<div class="notice notice-error"><p>Falta la API Key.</p></div>';
            return;
        }

        set_time_limit(120); 
        echo '<div class="notice notice-info"><p>Trabajando en: <strong>' . $topic . '</strong>... Generando texto e imagen.</p></div>';

        // 1. Generar Texto con Prompt Avanzado
        $content_data = $this->call_openai_gpt( $topic, $keywords, $tone, $api_key );
        if ( ! $content_data ) {
            echo '<div class="notice notice-error"><p>Error al conectar con OpenAI (GPT).</p></div>';
            return;
        }

        // 2. Generar Imagen
        $image_url = $this->call_openai_dalle( $topic, $api_key );

        // 3. Crear Página como BORRADOR (Draft)
        $page_id = wp_insert_post( array(
            'post_title'    => $content_data['title'],
            'post_content'  => $content_data['body'],
            'post_status'   => 'draft',    // <--- CAMBIO CLAVE: Se guarda como borrador
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
        ) );

        if ( $page_id ) {
            if ( $image_url ) {
                $this->attach_image_to_post( $image_url, $page_id );
            }
            
            // 4. Mostrar enlaces de Vista Previa y Edición
            $edit_link = get_edit_post_link( $page_id );
            $preview_link = get_preview_post_link( $page_id );

            echo '<div class="notice notice-success" style="padding:15px;">';
            echo '<h3>¡Borrador Creado Exitosamente!</h3>';
            echo '<p>El artículo ha sido generado pero <strong>no es público aún</strong>.</p>';
            echo '<p>';
            echo '<a href="' . $preview_link . '" target="_blank" class="button button-primary">👁️ Ver Vista Previa (Como quedaría)</a> ';
            echo '<a href="' . $edit_link . '" target="_blank" class="button">✏️ Editar Contenido</a>';
            echo '</p></div>';
        }
    }

    private function call_openai_gpt( $topic, $keywords, $tone, $api_key ) {
        // PROMPT ESTRUCTURADO (Contexto Coticefacil)
        $system_prompt = "Eres un redactor experto en SEO y Copywriting B2B para 'Coticefacil', una plataforma que conecta empresas con proveedores. " .
                         "Tu objetivo es escribir artículos que informen sobre un servicio y persuadan al lector para pedir una cotización.";

        $user_prompt = "Escribe un artículo sobre el servicio: '$topic'. \n" .
                       "Palabras clave a incluir: $keywords. \n" .
                       "Tono del texto: $tone. \n\n" .
                       "Instrucciones de estructura:\n" .
                       "1. Usa etiquetas HTML (h2, h3, p, ul) para formatear.\n" .
                       "2. El primer párrafo debe atacar el problema o necesidad del cliente.\n" .
                       "3. Explica los beneficios de contratar este servicio profesionalmente.\n" .
                       "4. NO incluyas markdown (```html), solo devuelve el JSON puro.\n\n" .
                       "Formato de respuesta JSON requerido:\n" .
                       "{ \"title\": \"Un título SEO atractivo (max 60 chars)\", \"body\": \"El contenido HTML del artículo\" }";

        $body = [
            'model' => 'gpt-4o', 
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post( '[https://api.openai.com/v1/chat/completions](https://api.openai.com/v1/chat/completions)', [
            'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
            'body'    => json_encode( $body ),
            'timeout' => 60
        ]);

        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return json_decode( $data['choices'][0]['message']['content'], true );
    }

    private function call_openai_dalle( $topic, $api_key ) {
        $prompt = "Foto corporativa profesional de alta calidad sobre: $topic. Iluminación cinematográfica, estilo moderno, sin texto, aspecto realista para web de negocios.";

        $body = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024'
        ];

        $response = wp_remote_post( '[https://api.openai.com/v1/images/generations](https://api.openai.com/v1/images/generations)', [
            'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
            'body'    => json_encode( $body ),
            'timeout' => 60
        ]);

        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data'][0]['url'] ?? false;
    }

    private function attach_image_to_post( $image_url, $post_id ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $media_id = media_sideload_image( $image_url, $post_id, null, 'id' );
        if ( ! is_wp_error( $media_id ) ) {
            set_post_thumbnail( $post_id, $media_id );
        }
    }
}

new CF_AI_Page_Generator();
