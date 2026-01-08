<?php
/**
 * Plugin Name: CoticeFácil SEO Wizard (AJAX Workflow)
 * Description: Flujo interactivo: Analizar CSV -> Editar Prompt -> Previsualizar -> Publicar.
 * Version: 3.0
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
    
    wp_enqueue_script('cf-seo-js', CF_SEO_URL . 'js/admin-script.js', ['jquery'], '3.0', true);
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
        $cerebro = new CF_SEO_Brain(); // No necesita API Key para este paso
        $datos = $cerebro->leer_csv($_FILES['csv']['tmp_name']);
        $analisis = $cerebro->analizar_datos($datos, $_POST['estrategia']);
        $prompt = $cerebro->generar_prompt_base($analisis, $_POST['estrategia']);
        
        wp_send_json_success(['prompt' => $prompt]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// Paso 2: Generar Previsualización (Llamada a OpenAI)
add_action('wp_ajax_cf_step_2_preview', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');
    
    $api_key = get_option('cf_openai_key');
    if (!$api_key) wp_send_json_error("Falta la API Key.");

    $prompt_usuario = stripslashes($_POST['prompt']);
    $usar_imagen = $_POST['usar_imagen'] === 'true';

    try {
        set_time_limit(120); // Damos 2 minutos al servidor
        $cerebro = new CF_SEO_Brain($api_key);
        
        // Generar Texto
        $contenido = $cerebro->ejecutar_prompt_texto($prompt_usuario);
        
        // Generar Imagen (si aplica)
        $img_url = '';
        if ($usar_imagen && !empty($contenido->image_prompt)) {
            $img_url = $cerebro->ejecutar_dall_e($contenido->image_prompt);
        }

        wp_send_json_success([
            'title' => $contenido->title,
            'html' => $contenido->html_content,
            'img_url' => $img_url
        ]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// Paso 3: Publicar (Guardar en WP)
add_action('wp_ajax_cf_step_3_publish', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');

    $title = sanitize_text_field($_POST['title']);
    // Permitimos HTML en el contenido pero quitamos scripts maliciosos
    $content = wp_kses_post(stripslashes($_POST['content'])); 
    $img_url = esc_url_raw($_POST['img_url']);

    try {
        // Crear Post
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft', // Borrador por seguridad
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);

        if (is_wp_error($post_id)) throw new Exception("Error creando post.");

        // Descargar Imagen
        if ($img_url) {
            $cerebro = new CF_SEO_Brain();
            $cerebro->asignar_imagen_destacada($img_url, $post_id, $title);
        }

        wp_send_json_success(['edit_link' => get_edit_post_link($post_id)]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});
