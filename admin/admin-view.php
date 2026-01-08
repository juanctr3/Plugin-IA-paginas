<div class="wrap">
    <h1>🚀 CoticeFácil SEO Wizard</h1>
    <p>Sube tu archivo CSV de palabras clave y deja que la IA estructure tu estrategia de contenidos.</p>
    
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px;">
        
        <?php if (isset($error_msg)): ?>
            <div class="notice notice-error inline"><p><?php echo esc_html($error_msg); ?></p></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('cf_seo_action', 'cf_seo_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="csv_file">Archivo CSV (Google/Excel)</label></th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">Formato requerido: Columna A: Keyword, B: Volumen, C: Competencia, D: CPC.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="estrategia">Objetivo del Artículo</label></th>
                    <td>
                        <select name="estrategia" id="estrategia">
                            <option value="leads">💰 Calidad & Leads (Prioriza CPC alto)</option>
                            <option value="trafico">📢 Tráfico Masivo (Prioriza Volumen)</option>
                        </select>
                        <p class="description">
                            <strong>Calidad:</strong> Para servicios que venden. 
                            <strong>Tráfico:</strong> Para blog posts educativos.
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="cf_seo_submit" id="submit" class="button button-primary" value="Generar Prompt Maestro">
            </p>
        </form>
    </div>

    <?php if (isset($resultado_prompt)): ?>
        <br>
        <div style="background: #f0f6fc; padding: 20px; border: 1px solid #2271b1; max-width: 800px;">
            <h2>✨ Tu Prompt Generado</h2>
            <p>Copia el siguiente texto y pégalo en ChatGPT o Gemini para generar tu artículo:</p>
            
            <textarea id="prompt_area" style="width:100%; height: 300px; font-family: monospace; font-size: 13px; background: #fff;"><?php echo esc_textarea($resultado_prompt); ?></textarea>
            
            <br><br>
            <button class="button button-secondary" onclick="copiarPrompt()">📋 Copiar al Portapapeles</button>
            
            <script>
            function copiarPrompt() {
                var copyText = document.getElementById("prompt_area");
                copyText.select();
                document.execCommand("copy");
                alert("Prompt copiado. ¡Ahora ve a la IA!");
            }
            </script>
        </div>
    <?php endif; ?>
</div>
