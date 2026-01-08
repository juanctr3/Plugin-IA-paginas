<div class="wrap">
    <h1>🚀 CoticeFácil SEO - Modo Interactivo</h1>
    
    <div style="margin-bottom: 20px;">
        <form method="post" action="options.php">
            <?php settings_fields('cf_seo_group'); ?>
            <label>API Key OpenAI:</label>
            <input type="password" name="cf_openai_key" value="<?php echo esc_attr(get_option('cf_openai_key')); ?>" placeholder="sk-..." size="40"/>
            <?php submit_button('Guardar', 'small', 'submit', false); ?>
        </form>
    </div>

    <div id="step-1" class="card" style="padding: 20px; max-width: 600px;">
        <h2>1. Cargar Datos</h2>
        <input type="file" id="csv_file" accept=".csv">
        <select id="estrategia">
            <option value="leads">💰 Leads (Ventas)</option>
            <option value="trafico">📢 Tráfico (Info)</option>
        </select>
        <button id="btn-analizar" class="button button-primary">Analizar CSV</button>
        <div id="loader-1" style="display:none; color: #2271b1; margin-top:5px;">⏳ Analizando...</div>
    </div>

    <div id="step-2" class="card" style="padding: 20px; max-width: 800px; display:none; margin-top: 20px; border-left: 4px solid #f0b849;">
        <h2>2. Configurar Prompt</h2>
        <textarea id="prompt_area" style="width:100%; height: 200px; font-family: monospace;"></textarea>
        <br><br>
        <button id="btn-gen-completo" class="button button-primary button-hero">✨ Generar Artículo + Imagen</button>
        <button id="btn-gen-texto" class="button button-secondary">📄 Solo Texto</button>
        <div id="loader-2" style="display:none; color: #2271b1; margin-top:10px;">⏳ La IA está escribiendo y diseñando... (Paciencia)</div>
    </div>

    <div id="step-3" class="card" style="padding: 20px; display:none; margin-top: 20px; border-left: 4px solid #46b450;">
        <h2>3. Revisión Final</h2>
        
        <div id="seo-box" style="background: #e5f6fd; border: 1px solid #00a0d2; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
            <h3 style="margin-top:0;">🔍 Datos para All in One SEO</h3>
            
            <p style="margin-bottom:5px;"><strong>Palabra Clave Principal:</strong></p>
            <input type="text" id="seo-main-kw" style="width:100%; background:#fff;" readonly onclick="this.select()">
            
            <p style="margin-bottom:5px; margin-top:10px;"><strong>Palabras Clave Secundarias (Tags):</strong></p>
            <input type="text" id="seo-sec-kw" style="width:100%; background:#fff;" readonly onclick="this.select()">
            
            <p style="margin-bottom:5px; margin-top:10px;"><strong>Meta Descripción:</strong></p>
            <textarea id="seo-meta-desc" style="width:100%; height:60px; background:#fff;" readonly onclick="this.select()"></textarea>
            
            <p class="description">💡 Haz clic en los campos para seleccionarlos y copiarlos.</p>
        </div>

        <div style="border: 1px solid #ddd; padding: 20px; background: #fff;">
            <h1 id="preview-title" style="margin-top:0;"></h1>
            <div style="text-align:center; background:#f0f0f1; padding:10px; margin-bottom:15px;">
                <img id="preview-img" src="" style="max-width: 100%; height: auto; display:none;">
                <p id="no-img-msg" style="display:none; color:#666;">(Sin imagen)</p>
            </div>
            <div id="preview-content"></div>
        </div>

        <br>
        <button id="btn-publish" class="button button-primary button-large">🚀 PUBLICAR BORRADOR</button>
        <button onclick="location.reload()" class="button">❌ Cancelar</button>
        
        <div id="loader-3" style="display:none; margin-top:10px;">⏳ Guardando en WordPress...</div>
        <div id="success-msg" style="display:none; margin-top: 10px; padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;"></div>
    </div>
</div>
