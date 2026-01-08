<?php
/**
 * Plugin Name: CoticeFácil SEO Wizard (Auto)
 * Description: Analiza CSV, Genera Contenido con GPT-4, Crea Imágenes con DALL-E y Publica en WP.
 * Version: 2.0
 */

if (!defined('ABSPATH')) exit;

define('CF_SEO_PATH', plugin_dir_path(__FILE__));
require_once CF_SEO_PATH . 'includes/class-seo-brain.php';

// Añadir menú y configuración
function cf_seo_add_admin_menu() {
    add_menu_page('CoticeFácil SEO', 'SEO Auto', 'manage_options', 'coticefacil-seo-wizard', 'cf_seo_render_admin_page', 'dashicons-superhero', 99);
    register_setting('cf_seo_settings', 'cf_openai_key');
}
add_action('admin_menu', 'cf_seo_add_admin_menu');
add_action('admin_init', function() { register_setting('cf_seo_group', 'cf_openai_key'); });

// Lógica Principal
function cf_seo_render_admin_page() {
    $mensaje = null;
    $error = null;

    if (isset($_POST['cf_seo_submit']) && check_admin_referer('cf_seo_action', 'cf_seo_nonce')) {
        $api_key = get_option('cf_openai_key');
        
        if (empty($api_key)) {
            $error = "Falta la API Key de OpenAI en la configuración.";
        } elseif (!empty($_FILES['csv_file']['tmp_name'])) {
            
            // Aumentar tiempo de espera (Generar imágenes tarda)
            set_time_limit(300); 

            try {
                $cerebro = new CF_SEO_Brain($api_key);
                $estrategia = sanitize_text_field($_POST['estrategia']);

                // 1. Analizar CSV
                $datos = $cerebro->leer_csv($_FILES['csv_file']['tmp_name']);
                $analisis = $cerebro->analizar_datos($datos, $estrategia);

                // 2. Generar Contenido (Texto + Prompt de Imagen) via API
                $contenido_generado = $cerebro->generar_articulo_ia($analisis, $estrategia);

                // 3. Generar Imagen via API (DALL-E)
                $url_imagen = $cerebro->generar_imagen_ia($contenido_generado->image_prompt);

                // 4. Crear Página en WordPress
                $page_id = wp_insert_post([
                    'post_title'   => $contenido_generado->title,
                    'post_content' => $contenido_generado->html_content,
                    'post_status'  => 'draft', // Lo dejamos en borrador por seguridad
                    'post_type'    => 'page',  // IMPORTANTE: Crea una PAGINA, no una entrada
                    'post_author'  => get_current_user_id()
                ]);

                // 5. Subir y Asignar Imagen Destacada
                if ($page_id && $url_imagen) {
                    $cerebro->asignar_imagen_destacada($url_imagen, $page_id, $contenido_generado->title);
                }

                $mensaje = "¡Éxito! Página creada: <a href='".get_edit_post_link($page_id)."'>Editar Página</a>";

            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
    include CF_SEO_PATH . 'admin/admin-view.php';
}
