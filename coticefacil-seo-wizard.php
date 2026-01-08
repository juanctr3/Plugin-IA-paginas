<?php
/**
 * Plugin Name: CoticeFácil SEO Wizard (AJAX Workflow)
 * Description: Flujo interactivo: Analizar CSV -> Editar Prompt -> Previsualizar (con SEO) -> Publicar.
 * Version: 3.5
 */

if (!defined('ABSPATH')) exit;

define('CF_SEO_PATH', plugin_dir_path(__FILE__));
define('CF_SEO_URL', plugin_dir_url(__FILE__));

require_once CF_SEO_PATH . 'includes/class-seo-brain.php';

// Configuración y Menú
add_action('admin_menu', function() {
    add_menu_page('CoticeFácil SEO', 'SEO Auto', 'manage_options', 'cf-seo-wizard', 'cf_seo_render_view', 'dashicons-superhero', 99);
    register_setting('cf_seo_settings', 'cf_openai_key');
});

add_action('admin_init', function() { register_setting('cf_seo_group', 'cf_openai_key'); });

// Encolar Scripts JS
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook != 'toplevel_page_cf-seo-wizard') return;
    
    wp_enqueue_script('cf-seo-js', CF_SEO_URL . 'js/admin-script.js', ['jquery'], '3.5', true);
    wp_localize_script('cf-seo-js', 'cf_ajax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cf_seo_nonce')
    ]);
});

function cf_seo_render_view() {
    include CF_SEO_PATH . 'admin/admin-view.php';
}

// --- AJAX HANDLERS (El Backend que responde al JS) ---

// Paso 1: Generar Prompt desde CSV
add_action('wp_ajax_cf_step_1_analizar', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');
    
    if (empty($_FILES['csv']['tmp_name'])) wp_send_json_error("Falta el archivo CSV");

    try {
        $cerebro = new CF_SEO_Brain();
        $datos = $cerebro->leer_csv($_FILES['csv']['tmp_name']);
        $analisis = $cerebro->analizar_datos($datos, sanitize_text_field($_POST['estrategia']));
        $prompt = $cerebro->generar_prompt_base($analisis, sanitize_text_field($_POST['estrategia']));
        
        wp_send_json_success(['prompt' => $prompt]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// Paso 2: Generar Previsualización (AQUÍ ESTABA EL PROBLEMA)
add_action('wp_ajax_cf_step_2_preview', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');
    
    $api_key = get_option('cf_openai_key');
    if (empty($api_key)) wp_send_json_error("Error: Falta la API Key de OpenAI en la configuración.");

    $prompt_usuario = stripslashes($_POST['prompt']);
    // Detección robusta de booleano
    $usar_imagen = filter_var($_POST['usar_imagen'] ?? false, FILTER_VALIDATE_BOOLEAN);

    try {
        set_time_limit(180); // 3 minutos máximo
        $cerebro = new CF_SEO_Brain($api_key);
        
        // 1. Generar Texto (Recibe objeto con title, html, meta_description, etc.)
        $contenido = $cerebro->ejecutar_prompt_texto($prompt_usuario);
        
        if (!is_object($contenido) || !isset($contenido->title)) {
             throw new Exception("La IA no devolvió el formato JSON correcto. Intenta de nuevo.");
        }

        // 2. Generar Imagen (si aplica)
        $img_url = '';
        if ($usar_imagen && !empty($contenido->image_prompt)) {
            sleep(1); // Pequeña pausa
            $img_url = $cerebro->ejecutar_dall_e($contenido->image_prompt);
        }

        // 3. RESPUESTA AL JAVASCRIPT (Aquí agregamos los campos SEO faltantes)
        wp_send_json_success([
            'title' => $contenido->title,
            'html' => $contenido->html_content,
            // Estos son los campos que faltaban para llenar la caja azul:
            'main_keyword' => $contenido->main_keyword ?? '',       
            'secondary_keywords' => $contenido->secondary_keywords ?? '',
            'meta_description' => $contenido->meta_description ?? '',
            'img_url' => $img_url
        ]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// Paso 3: Publicar (Guardar en WP)
add_action('wp_ajax_cf_step_3_publish', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');

    if (empty($_POST['title'])) wp_send_json_error("Falta el título.");

    $title = sanitize_text_field($_POST['title']);
    $content = wp_kses_post(stripslashes($_POST['content'])); 
    $img_url = esc_url_raw($_POST['img_url']);

    try {
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft', // Borrador
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);

        if (is_wp_error($post_id)) throw new Exception("Error creando el post: " . $post_id->get_error_message());

        if (!empty($img_url)) {
            $cerebro = new CF_SEO_Brain();
            $cerebro->asignar_imagen_destacada($img_url, $post_id, $title);
        }

        wp_send_json_success(['edit_link' => get_edit_post_link($post_id)]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});
