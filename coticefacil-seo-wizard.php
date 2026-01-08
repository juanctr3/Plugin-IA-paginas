<?php
/**
 * Plugin Name: CoticeFácil SEO Wizard (Pro)
 * Description: Generador IA con soporte para CSV, Manual, Blog, Páginas y Portafolios.
 * Version: 4.0
 */

if (!defined('ABSPATH')) exit;

define('CF_SEO_PATH', plugin_dir_path(__FILE__));
define('CF_SEO_URL', plugin_dir_url(__FILE__));

require_once CF_SEO_PATH . 'includes/class-seo-brain.php';

// Menú y Settings
add_action('admin_menu', function() {
    add_menu_page('CoticeFácil SEO', 'SEO Auto', 'manage_options', 'cf-seo-wizard', 'cf_seo_render_view', 'dashicons-superhero', 99);
    register_setting('cf_seo_settings', 'cf_openai_key');
});
add_action('admin_init', function() { register_setting('cf_seo_group', 'cf_openai_key'); });

// Assets
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook != 'toplevel_page_cf-seo-wizard') return;
    wp_enqueue_script('cf-seo-js', CF_SEO_URL . 'js/admin-script.js', ['jquery'], '4.0', true);
    wp_localize_script('cf-seo-js', 'cf_ajax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cf_seo_nonce')
    ]);
});

function cf_seo_render_view() { include CF_SEO_PATH . 'admin/admin-view.php'; }

// --- AJAX HANDLERS ---

// PASO 1: ANÁLISIS Y PROMPT
add_action('wp_ajax_cf_step_1_analizar', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');
    
    $mode = $_POST['mode'];
    $estrategia = sanitize_text_field($_POST['estrategia']);
    $cerebro = new CF_SEO_Brain();
    $analisis = [];
    $extra_instruction = "";

    try {
        if ($mode === 'csv') {
            // MODO AUTOMÁTICO
            if (empty($_FILES['csv']['tmp_name'])) wp_send_json_error("Falta el archivo CSV");
            $datos = $cerebro->leer_csv($_FILES['csv']['tmp_name']);
            $analisis = $cerebro->analizar_datos($datos, $estrategia);
        } else {
            // MODO MANUAL
            $main = sanitize_text_field($_POST['manual_main_kw']);
            $sec_raw = sanitize_text_field($_POST['manual_sec_kw']);
            $extra_instruction = sanitize_textarea_field($_POST['manual_extra_prompt']);
            
            // Convertimos input manual a la estructura que espera el cerebro
            $sec_array = !empty($sec_raw) ? array_map(function($k){ return ['keyword' => trim($k)]; }, explode(',', $sec_raw)) : [];
            
            $analisis = [
                'ganadora' => ['keyword' => $main],
                'secundarias' => $sec_array
            ];
        }

        // Generamos el prompt base
        $prompt = $cerebro->generar_prompt_base($analisis, $estrategia);

        // Si hay instrucción manual extra, la añadimos al final del prompt
        if (!empty($extra_instruction)) {
            $prompt .= "\n\nINSTRUCCIÓN ADICIONAL DEL USUARIO:\n" . $extra_instruction;
        }
        
        wp_send_json_success(['prompt' => $prompt]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// PASO 2: PREVIEW (Igual que antes)
add_action('wp_ajax_cf_step_2_preview', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');
    $api_key = get_option('cf_openai_key');
    if (empty($api_key)) wp_send_json_error("Falta API Key.");

    $prompt = stripslashes($_POST['prompt']);
    $usar_img = filter_var($_POST['usar_imagen'] ?? false, FILTER_VALIDATE_BOOLEAN);

    try {
        set_time_limit(180);
        $cerebro = new CF_SEO_Brain($api_key);
        
        $contenido = $cerebro->ejecutar_prompt_texto($prompt);
        if (!isset($contenido->title)) throw new Exception("Error formato JSON IA.");

        $img_url = '';
        if ($usar_img && !empty($contenido->image_prompt)) {
            sleep(1);
            $img_url = $cerebro->ejecutar_dall_e($contenido->image_prompt);
        }

        wp_send_json_success([
            'title' => $contenido->title,
            'html' => $contenido->html_content,
            'main_keyword' => $contenido->main_keyword ?? '',       
            'secondary_keywords' => $contenido->secondary_keywords ?? '',
            'meta_description' => $contenido->meta_description ?? '',
            'img_url' => $img_url
        ]);
    } catch (Exception $e) { wp_send_json_error($e->getMessage()); }
});

// PASO 3: PUBLICAR (Ahora soporta Post Types y Categorías)
add_action('wp_ajax_cf_step_3_publish', function() {
    check_ajax_referer('cf_seo_nonce', 'nonce');

    $title = sanitize_text_field($_POST['title']);
    $content = wp_kses_post(stripslashes($_POST['content'])); 
    $img_url = esc_url_raw($_POST['img_url']);
    
    // Nuevos campos
    $post_type = sanitize_text_field($_POST['post_type']); // page, post, portfolio
    $category_id = intval($_POST['post_category']);

    try {
        $args = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => $post_type, // Dinámico
            'post_author'  => get_current_user_id()
        ];

        // Si es una entrada de blog, asignamos categoría
        if ($post_type === 'post' && $category_id > 0) {
            $args['post_category'] = [$category_id];
        }

        $post_id = wp_insert_post($args);

        if (is_wp_error($post_id)) throw new Exception("Error WP: " . $post_id->get_error_message());

        if (!empty($img_url)) {
            $cerebro = new CF_SEO_Brain();
            $cerebro->asignar_imagen_destacada($img_url, $post_id, $title);
        }

        wp_send_json_success(['edit_link' => get_edit_post_link($post_id)]);

    } catch (Exception $e) { wp_send_json_error($e->getMessage()); }
});
