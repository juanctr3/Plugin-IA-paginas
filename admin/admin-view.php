<div class="wrap">
    <h1>🚀 CoticeFácil SEO - Modo Interactivo</h1>
    
    <div style="margin-bottom: 20px;">
        <form method="post" action="options.php">
            <?php settings_fields('cf_seo_group'); ?>
            <label>API Key:</label>
            <input type="password" name="cf_openai_key" value="<?php echo esc_attr(get_option('cf_openai_key')); ?>" placeholder="sk-..." />
            <?php submit_button('Guardar', 'small', 'submit', false); ?>
        </form>
    </div>

    <div id="step-1" class="card" style="padding: 20px; max-width: 600px;">
        <h2>1. Análisis de Datos</h2>
        <input type="file" id="csv_file" accept=".csv">
        <select id="estrategia">
            <option value="leads">💰 Leads (Ventas)</option>
            <option value="trafico">📢 Tráfico (Info)</option>
        </select>
        <button id="btn-analizar" class="button button-primary">Analizar y Crear Prompt</button>
        <div id="loader-1" style="display:none; color: #2271b1;">⏳ Analizando datos...</div>
    </div>

    <div id="step-2" class="card" style="padding: 20px; max-width: 800px; display:none; margin-top: 20px; border-left: 4px solid #f0b849;">
        <h2>2. Revisa el Prompt Maestro</h2>
        <p>Puedes editar las instrucciones antes de enviarlas a la IA.</p>
        <textarea id="prompt_area" style="width:100%; height: 200px; font-family: monospace;"></textarea>
        <br><br>
        <button id="btn-gen-completo" class="button button-primary button-hero">✨ Generar Artículo + Imagen</button>
        <button id="btn-gen-texto" class="button button-secondary">📄 Generar Solo Texto</button>
        <div id="loader-2" style="display:none; color: #2271b1; margin-top:10px;">
            ⏳ Conectando con GPT-4 y DALL-E... (Esto puede tardar 30-60 seg)
        </div>
    </div>

    <div id="step-3" class="card" style="padding: 20px; display:none; margin-top: 20px; border-left: 4px solid #46b450;">
        <h2>3. Previsualización</h2>
        
        <div style="background: #fff; border: 1px solid #ddd; padding: 20px;">
            <h1 id="preview-title" style="margin-top:0;"></h1>
            
            <div style="text-align:center; background:#f0f0f1; padding:10px; margin-bottom:15px;">
                <img id="preview-img" src="" style="max-width: 100%; height: auto; display:none; border: 1px solid #999;">
                <p id="no-img-msg" style="display:none; color:#666;">(Sin imagen generada)</p>
            </div>
            
            <div id="preview-content"></div>
        </div>

        <br>
        <button id="btn-publish" class="button button-primary button-large">🚀 PUBLICAR EN WORDPRESS</button>
        <button onclick="location.reload()" class="button">❌ Cancelar / Empezar de nuevo</button>
        
        <div id="loader-3" style="display:none; color: #2271b1;">⏳ Descargando imagen y guardando...</div>
        <div id="success-msg" style="display:none; background: #d4edda; color: #155724; padding: 10px; margin-top: 10px;"></div>
    </div>
</div>
