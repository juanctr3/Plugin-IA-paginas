<?php
/*
Plugin Name: CF Auto Page Generator (OpenAI)
Description: Genera páginas profesionales con imágenes usando OpenAI (GPT + DALL-E) automáticamente.
Version: 1.0
Author: Tu Nombre
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class CF_AI_Page_Generator {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    // 1. Crear el menú en el admin
    public function add_admin_menu() {
        add_menu_page(
            'Generador AI', 
            'Generador AI', 
            'manage_options', 
            'cf-ai-generator', 
            array( $this, 'render_admin_page' ), 
            'dashicons-superhero', 
            100
        );
    }

    // 2. Registrar la opción para guardar la API Key
    public function register_settings() {
        register_setting( 'cf_ai_group', 'cf_openai_api_key' );
    }

    // 3. Renderizar la página de administración
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Generador Automático de Páginas (AI)</h1>
            
            <form method="post" action="options.php" style="background:#fff; padding:20px; border:1px solid #ccc; margin-bottom:20px;">
                <?php settings_fields( 'cf_ai_group' ); ?>
                <?php do_settings_sections( 'cf_ai_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                    <th scope="row">OpenAI API Key</th>
                    <td><input type="password" name="cf_openai_api_key" value="<?php echo esc_attr( get_option('cf_openai_api_key') ); ?>" style="width:100%;" /></td>
                    </tr>
                </table>
                <?php submit_button('Guardar API Key'); ?>
            </form>

            <hr>

            <h2>Crear Nueva Página</h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'cf_generate_content', 'cf_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="topic">Tema del Artículo:</label></th>
                        <td>
                            <input name="topic" type="text" id="topic" class="regular-text" placeholder="Ej: Servicios de destrucción de documentos">
                            <p class="description">La IA escribirá un artículo profesional y generará una imagen sobre este tema.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="generate_page" id="submit" class="button button-primary" value="Generar y Publicar Página">
                </p>
            </form>
            
            <?php
            // Procesar la solicitud si se envió el formulario
            if ( isset( $_POST['generate_page'] ) && check_admin_referer( 'cf_generate_content', 'cf_nonce' ) ) {
                $this->process_generation( sanitize_text_field( $_POST['topic'] ) );
            }
            ?>
        </div>
        <?php
    }

    // 4. Lógica principal de generación
    private function process_generation( $topic ) {
        if ( empty( $topic ) ) return;

        $api_key = get_option('cf_openai_api_key');
        if ( empty( $api_key ) ) {
            echo '<div class="notice notice-error"><p>Por favor guarda tu API Key primero.</p></div>';
            return;
        }

        // Aumentar tiempo de ejecución (la IA tarda)
        set_time_limit(120); 

        echo '<div class="notice notice-info"><p>Generando contenido e imagen para: <strong>' . $topic . '</strong>... Por favor espera.</p></div>';

        // A) Generar Texto (GPT)
        $content_data = $this->call_openai_gpt( $topic, $api_key );
        if ( ! $content_data ) {
            echo '<div class="notice notice-error"><p>Error generando texto.</p></div>';
            return;
        }

        // B) Generar Imagen (DALL-E)
        $image_url = $this->call_openai_dalle( $topic, $api_key );

        // C) Crear la Página en WordPress
        $page_id = wp_insert_post( array(
            'post_title'    => $content_data['title'],
            'post_content'  => $content_data['body'],
            'post_status'   => 'publish', // Publicar directamente
            'post_type'     => 'page',    // Importante: Tipo Página
            'post_author'   => get_current_user_id(),
        ) );

        if ( $page_id ) {
            // D) Subir y asignar imagen destacada
            if ( $image_url ) {
                $this->attach_image_to_post( $image_url, $page_id );
            }
            echo '<div class="notice notice-success"><p>¡Éxito! Página creada: <a href="' . get_permalink( $page_id ) . '" target="_blank">Ver Página</a></p></div>';
        }
    }

    // Función auxiliar: Llamada a GPT (Texto)
    private function call_openai_gpt( $topic, $api_key ) {
        $prompt = "Actúa como un redactor SEO profesional. Escribe un artículo completo, detallado y persuasivo sobre: '$topic'. " .
                  "El artículo es para una empresa de servicios. " .
                  "Devuelve la respuesta en formato JSON con dos claves: 'title' (un título atractivo) y 'body' (el contenido en HTML usando etiquetas h2, h3, p, ul). No incluyas markdown, solo el JSON.";

        $body = [
            'model' => 'gpt-4o', // O gpt-3.5-turbo
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un experto redactor web. Respondes solo en JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'] // Fuerza respuesta JSON limpia
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => json_encode( $body ),
            'timeout' => 60
        ]);

        if ( is_wp_error( $response ) ) return false;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $content_json = json_decode( $data['choices'][0]['message']['content'], true );

        return $content_json;
    }

    // Función auxiliar: Llamada a DALL-E (Imagen)
    private function call_openai_dalle( $topic, $api_key ) {
        $prompt = "Una imagen profesional, estilo fotográfico corporativo y moderno sobre: $topic. Alta calidad, iluminación natural.";

        $body = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024'
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => json_encode( $body ),
            'timeout' => 60
        ]);

        if ( is_wp_error( $response ) ) return false;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data'][0]['url'] ?? false;
    }

    // Función auxiliar: Descargar imagen y asignar como destacada
    private function attach_image_to_post( $image_url, $post_id ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Descarga la imagen y la añade a la biblioteca de medios
        $media_id = media_sideload_image( $image_url, $post_id, null, 'id' );

        if ( ! is_wp_error( $media_id ) ) {
            set_post_thumbnail( $post_id, $media_id );
        }
    }
}

new CF_AI_Page_Generator();
