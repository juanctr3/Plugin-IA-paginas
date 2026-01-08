<?php
// Obtener categorías para el selector
$categories = get_categories(['hide_empty' => false]);
?>

<div class="wrap">
    <h1>🚀 CoticeFácil - Generador de Contenido IA</h1>
    
    <div style="margin-bottom: 20px; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
        <form method="post" action="options.php" style="display:flex; align-items:center; gap:10px;">
            <?php settings_fields('cf_seo_group'); ?>
            <label><strong>API Key OpenAI:</strong></label>
            <input type="password" name="cf_openai_key" value="<?php echo esc_attr(get_option('cf_openai_key')); ?>" placeholder="sk-..." size="40"/>
            <?php submit_button('Guardar Key', 'small', 'submit', false); ?>
        </form>
    </div>

    <h2 class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" id="tab-csv">📂 Modo Automático (CSV)</a>
        <a href="#" class="nav-tab" id="tab-manual">✍️ Modo Manual</a>
    </h2>

    <div id="step-1" class="card" style="padding: 20px; max-width: 800px; margin-top:0;">
        
        <div id="panel-csv">
            <h3>1. Cargar Archivo de Keywords</h3>
            <p>Sube tu archivo de Google Ads y analizaremos la mejor oportunidad.</p>
            <input type="file" id="csv_file" accept=".csv">
        </div>

        <div id="panel-manual" style="display:none;">
            <h3>1. Definición Manual</h3>
            <p>Indica directamente sobre qué quieres escribir.</p>
            
            <table class="form-table">
                <tr>
                    <th>Palabra Clave Principal</th>
                    <td><input type="text" id="manual_main_kw" class="regular-text" placeholder="Ej: Servicio de destrucción de documentos"></td>
                </tr>
                <tr>
                    <th>Palabras Clave Secundarias</th>
                    <td><input type="text" id="manual_sec_kw" class="large-text" placeholder="Ej: trituración segura, reciclaje papel, bogotá (separadas por coma)"></td>
                </tr>
                <tr>
                    <th>Instrucción Adicional (Prompt)</th>
                    <td><textarea id="manual_prompt" class="large-text" rows="2" placeholder="Ej: Enfócate en la seguridad industrial y menciona que tenemos certificación ISO."></textarea></td>
                </tr>
            </table>
        </div>

        <hr style="margin: 20px 0;">

        <table class="form-table">
            <tr>
                <th>Tipo de Contenido</th>
                <td>
                    <select id="post_type_selector">
                        <option value="page">📄 Página Estática</option>
                        <option value="post">📝 Entrada de Blog (Post)</option>
                        <option value="portfolio">💼 Elemento de Portafolio</option>
                    </select>
                </td>
            </tr>
            <tr id="category_row" style="display:none;">
                <th>Categoría (Blog)</th>
                <td>
                    <select id="post_category">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Estrategia / Tono</th>
                <td>
                    <select id="estrategia">
                        <option value="leads">💰 Leads (Ventas / Persuasivo)</option>
                        <option value="trafico">📢 Tráfico (Educativo / Informativo)</option>
                    </select>
                </td>
            </tr>
        </table>

        <br>
        <button id="btn-analizar" class="button button-primary button-large">🚀 Generar Prompt Maestro</button>
        <div id="loader-1" style="display:none; color: #2271b1; margin-top:10px;">⏳ Procesando solicitud...</div>
    </div>

    <div id="step-2" class="card" style="padding: 20px; max-width: 800px; display:none; margin-top: 20px; border-left: 4px solid #f0b849;">
        <h2>2. Revisar Prompt</h2>
        <p>Este es el prompt que enviaremos a la IA. Puedes editarlo si falta algo.</p>
        <textarea id="prompt_area" style="width:100%; height: 250px; font-family: monospace; font-size:13px;"></textarea>
        <br><br>
        <button id="btn-gen-completo" class="button button-primary button-hero">✨ Generar Contenido + Imagen</button>
        <button id="btn-gen-texto" class="button button-secondary">📄 Solo Texto</button>
        <div id="loader-2" style="display:none; color: #2271b1; margin-top:10px;">⏳ Escribiendo artículo de 1500+ palabras... (Puede tardar 2-3 mins)</div>
    </div>

    <div id="step-3" class="card" style="padding: 20px; display:none; margin-top: 20px; border-left: 4px solid #46b450;">
        <h2>3. Revisión y Publicación</h2>
        
        <div id="seo-box" style="background: #e5f6fd; border: 1px solid #00a0d2; padding: 15px; margin-bottom: 20px;">
            <h3>🔍 Datos SEO (Copiar a All in One SEO)</h3>
            <label><strong>Main Keyword:</strong></label>
            <input type="text" id="seo-main-kw" style="width:100%; margin-bottom:10px;" readonly>
            <label><strong>Tags / Secundarias:</strong></label>
            <input type="text" id="seo-sec-kw" style="width:100%; margin-bottom:10px;" readonly>
            <label><strong>Meta Descripción:</strong></label>
            <textarea id="seo-meta-desc" style="width:100%; height:60px;" readonly></textarea>
        </div>

        <div style="border: 1px solid #ddd; padding: 20px; background: #fff;">
            <h1 id="preview-title" style="margin-top:0;"></h1>
            <div style="text-align:center; background:#f0f0f1; padding:10px; margin-bottom:15px;">
                <img id="preview-img" src="" style="max-width: 100%; height: auto; display:none;">
            </div>
            <div id="preview-content"></div>
        </div>

        <br>
        <button id="btn-publish" class="button button-primary button-large">🚀 PUBLICAR AHORA</button>
        <button onclick="location.reload()" class="button">❌ Cancelar</button>
        <div id="loader-3" style="display:none; margin-top:10px;">⏳ Guardando en WordPress...</div>
        <div id="success-msg" style="display:none; margin-top: 10px; padding: 10px; background: #d4edda; color: #155724;"></div>
    </div>
</div>
