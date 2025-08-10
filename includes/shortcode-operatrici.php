<?php
// includes/shortcode-operatrici.php

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('aos_operatrici', 'aos_display_operatrici_shortcode');

/**
 * Funzione di callback per lo shortcode [aos_operatrici].
 * Genera una griglia di operatrici basato su CPT e attributi.
 * VERSIONE RIFATTORIZZATA con funzioni helper.
 *
 * @param array $atts Attributi dello shortcode.
 * @return string L'HTML generato.
 */
function aos_display_operatrici_shortcode($atts) {
    // 1. Attributi e pre-caricamento dati
    $atts = shortcode_atts([
        'genere' => '', 'numerazione' => '', 'orderby' => 'rand', 'order' => 'DESC', 
        'limit' => -1, 'meta_key' => '', 'columns' => '3', 'columns_tablet' => '2', 
        'columns_mobile' => '1', 'gap' => 'medium',
    ], $atts, 'aos_operatrici');

    // Usiamo la nostra nuova funzione helper!
    // Non sono usati direttamente qui ma possono essere passati a funzioni interne se necessario
    // $mappa_genere_ddi = aos_get_ddi_map();
    // $tutte_le_numerazioni = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false, 'orderby' => 'name']);

    // 2. Costruisci la query
    $args = [
        'post_type' => 'operatrice', 'post_status' => 'publish', 'posts_per_page' => intval($atts['limit']),
        'meta_key' => sanitize_text_field($atts['meta_key']), 'orderby' => ($atts['orderby'] === 'rand') ? 'rand' : sanitize_key($atts['orderby']),
    ];
    if ($atts['orderby'] !== 'rand') { $args['order'] = in_array(strtoupper($atts['order']), ['ASC', 'DESC']) ? strtoupper($atts['order']) : 'ASC'; }
    if (!empty($atts['genere'])) {
        $args['tax_query'] = [['taxonomy' => 'genere', 'field' => 'slug', 'terms' => sanitize_text_field($atts['genere'])]];
    }
    $query = new WP_Query($args);

    if (!$query->have_posts()) { return '<p>Nessuna operatrice trovata.</p>'; }

    // 3. Genera l'HTML
    ob_start(); // Inizia il buffering dell'output

    $grid_classes = "uk-child-width-1-{$atts['columns_mobile']}@s uk-child-width-1-{$atts['columns_tablet']}@m uk-child-width-1-{$atts['columns']}@l";

    // ✨ SOLUZIONE: Chiamiamo la funzione centralizzata e catturiamo il suo output.
    // Il messaggio "Componi il numero..." dovrebbe essere gestito da questa funzione
    // oppure lo aggiungiamo qui prima di essa.

    // Aggiungiamo il messaggio qui, prima della griglia generata dalla funzione helper
    // Assicurati che avs_render_operator_grid_with_quiz_injection non duplichi questo messaggio.
    echo '<p class="uk-text-center uk-margin-medium-bottom">Componi il numero e chiedi di parlare con l\'operatrice scelta</p>';
    
    // Ora, chiama la funzione helper che dovrebbe generare la griglia vera e propria
    echo avs_render_operator_grid_with_quiz_injection($query, $grid_classes, $atts['gap']); // Passiamo anche $atts['gap'] se serve

    wp_reset_postdata(); // Ripristina i dati globali del post di WordPress
    
    return ob_get_clean(); // Restituisce tutto l'output bufferizzato
}

// Nota: Se la funzione avs_render_operator_grid_with_quiz_injection includesse già il messaggio,
// allora dovresti rimuovere la riga 'echo <p class="uk-text-center...">...</p>' da qui.
// L'importante è che il messaggio non sia duplicato o mancante.