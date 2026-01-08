<?php

class CF_SEO_Brain {

    /**
     * Lee el CSV y lo convierte en un array asociativo.
     * Se asume formato: Keyword, Volume, CPC, Competition
     */
    public function leer_csv($filepath) {
        $filas = array_map('str_getcsv', file($filepath));
        $header = array_shift($filas); // Quitar encabezado si existe
        $datos = [];

        foreach ($filas as $fila) {
            // Validación básica para evitar filas vacías
            if (count($fila) < 2) continue;

            // Mapeo manual (Ajustar según tu CSV real)
            // [0] Keyword, [1] Volumen, [2] Competition, [3] CPC
            $datos[] = [
                'keyword' => $fila[0],
                'volumen' => (int) preg_replace('/[^0-9]/', '', $fila[1] ?? 0),
                'competition' => (float) ($fila[2] ?? 0), // Puede ser indice 0-100 o Low/High
                'cpc' => (float) ($fila[3] ?? 0)
            ];
        }
        return $datos;
    }

    /**
     * Ordena y selecciona las keywords según la estrategia
     */
    public function analizar_datos($datos, $estrategia) {
        
        if ($estrategia === 'trafico') {
            // ESTRATEGIA TRAFICO: Mayor volumen primero
            usort($datos, function($a, $b) {
                return $b['volumen'] - $a['volumen'];
            });
        } else {
            // ESTRATEGIA LEADS: Mayor CPC (Intención de compra)
            usort($datos, function($a, $b) {
                return $b['cpc'] <=> $a['cpc']; // Operador nave espacial para float
            });
        }

        // Seleccionar ganadora y secundarias
        $ganadora = $datos[0];
        $secundarias = array_slice($datos, 1, 5); // Tomamos las siguientes 5

        return [
            'ganadora' => $ganadora,
            'secundarias' => $secundarias
        ];
    }

    /**
     * Construye el Prompt Maestro
     */
    public function generar_prompt($analisis, $estrategia) {
        $k_main = $analisis['ganadora']['keyword'];
        $k_sec = implode(", ", array_column($analisis['secundarias'], 'keyword'));
        
        // Datos específicos de CoticeFácil
        $contexto = "
        ACTÚA COMO: Experto SEO Senior y Copywriter especializado en B2B para 'CoticeFácil' (Marketplace de cotizaciones en Colombia).
        TU MISIÓN: Redactar un artículo que posicione en Google y genere acciones del usuario.
        
        DATOS TÉCNICOS:
        - Keyword Principal (H1 y primer párrafo): '$k_main'
        - Keywords Secundarias (Distribuir en H2/H3): $k_sec
        ";

        if ($estrategia === 'leads') {
            $instrucciones = "
            ESTRATEGIA: **ALTA CONVERSIÓN (LEADS)**
            El usuario que busca esto tiene dinero y urgencia.
            1. Tono: Directo, profesional, orientado a la solución.
            2. Estructura:
               - Intro: Ataca el 'dolor' del usuario inmediatamente.
               - Cuerpo: Compara opciones y destaca por qué cotizar con varios proveedores ahorra dinero.
               - Cierre: CTA agresivo para usar el formulario de CoticeFácil.
            3. Objetivo: Que hagan clic en 'Solicitar Cotización'. NO hagas introducciones históricas largas.
            ";
        } else {
            $instrucciones = "
            ESTRATEGIA: **TRÁFICO MASIVO (BRANDING)**
            El usuario está investigando o aprendiendo.
            1. Tono: Educativo, autoritario, útil.
            2. Estructura:
               - Intro: Definición clara (para ganar Featured Snippet).
               - Cuerpo: Guía paso a paso, listas, consejos.
               - Cierre: Soft-CTA (ej: '¿Necesitas ayuda profesional? Cotiza aquí').
            3. Objetivo: Retener al usuario en la página y que comparta el contenido.
            ";
        }

        $formato = "
        FORMATO DE SALIDA:
        - Código HTML limpio (h2, h3, p, ul, strong).
        - Incluye un 'Meta Title' y 'Meta Description' atractivos al inicio.
        - Negritas en las frases clave importantes.
        ";

        return $contexto . $instrucciones . $formato;
    }
}
