<div class="wrap">
    <h1>🚀 CoticeFácil SEO Auto-Pilot</h1>
    
    <div style="background: #fff; padding: 15px; margin-bottom: 20px; border-left: 4px solid #72aee6;">
        <form method="post" action="options.php">
            <?php settings_fields('cf_seo_group'); ?>
            <label><strong>OpenAI API Key:</strong></label>
            <input type="password" name="cf_openai_key" value="<?php echo esc_attr(get_option('cf_openai_key')); ?>" size="50" placeholder="sk-..." />
            <?php submit_button('Guardar Key', 'small', 'submit', false); ?>
        </form>
    </div>

    <?php if (isset($error)): ?>
        <div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>
    
    <?php if (isset($mensaje)): ?>
        <div class="notice notice-success inline"><p><?php echo $mensaje; ?></p></div>
    <?php endif; ?>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
        <h3>Generar Nueva Página</h3>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('cf_seo_action', 'cf_seo_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th>Archivo CSV</th>
                    <td><input type="file" name="csv_file" accept=".csv" required></td>
                </tr>
                <tr>
                    <th>Estrategia</th>
                    <td>
                        <select name="estrategia">
                            <option value="leads">💰 Leads (Ventas)</option>
                            <option value="trafico">📢 Tráfico (Info)</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="cf_seo_submit" class="button button-primary button-hero" value="✨ Generar Página e Imagen Automáticamente">
            </p>
            <p class="description">Nota: Esto tomará unos 30-60 segundos. No cierres la ventana.</p>
        </form>
    </div>
</div>
