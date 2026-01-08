<?php
/**
 * Plugin Name: CoticeFácil SEO Wizard
 * Description: Analiza CSVs de palabras clave y genera Prompts Maestros para IA basados en estrategias de tráfico o leads.
 * Version: 1.0
 * Author: Tu Equipo de Desarrollo
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes de rutas
define('CF_SEO_PATH', plugin_dir_path(__FILE__));

// Incluir la clase lógica
require_once CF_SEO_PATH . 'includes/class-seo-brain.php';

// Crear el menú en el admin
function cf_seo_add_admin_menu() {
    add_menu_page(
        'CoticeFácil SEO',           // Título de página
        'SEO Wizard',                // Título del menú
        'manage_options',            // Capacidad requerida
        'coticefacil-seo-wizard',    // Slug del menú
        'cf_seo_render_admin_page',  // Función que renderiza la vista
        'dashicons-chart-line',      // Icono
        99                           // Posición
    );
}
add_action('admin_menu', 'cf_seo_add_admin_menu');

// Función controladora para mostrar la vista
function cf_seo_render_admin_page() {
    // Lógica de procesamiento de formulario
    $resultado_prompt = null;
    $error_msg = null;

    if (isset($_POST['cf_seo_submit']) && check_admin_referer('cf_seo_action', 'cf_seo_nonce')) {
        
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $estrategia = sanitize_text_field($_POST['estrategia']);
            
            try {
                // Instanciar el cerebro
                $cerebro = new CF_SEO_Brain();
                // 1. Leer CSV
                $datos = $cerebro->leer_csv($_FILES['csv_file']['tmp_name']);
                // 2. Analizar datos
                $analisis = $cerebro->analizar_datos($datos, $estrategia);
                // 3. Generar Prompt
                $resultado_prompt = $cerebro->generar_prompt($analisis, $estrategia);
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        } else {
            $error_msg = "Por favor sube un archivo CSV válido.";
        }
    }

    // Incluir el archivo de vista (HTML)
    include CF_SEO_PATH . 'admin/admin-view.php';
}
