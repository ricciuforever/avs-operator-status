<?php
// includes/shortcode-vetrina-genere-corrente.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registra lo shortcode [aos_vetrina_genere_corrente].
 */
add_shortcode( 'aos_vetrina_genere_corrente', 'aos_display_vetrina_genere_corrente_shortcode' );

/**
 * Funzione di callback per lo shortcode [aos_vetrina_genere_corrente].
 * VERSIONE DEFINITIVA: Mostra fino a 5 operatrici di un genere,
 * selezionate tramite una "lotteria pesata" basata sulla popolarità.
 */
function aos_display_vetrina_genere_corrente_shortcode( $atts ) {
    
    // 1. Rileva il genere della pagina corrente.
    $current_term = get_queried_object();
    if ( ! $current_term instanceof WP_Term || $current_term->taxonomy !== 'genere' ) {
        return '';
    }
    $genere_term_id = $current_term->term_id;

    // --- 2. CREA LA "LOTTERIA" PESATA (logica ispirata da aos_get_weighted_random_operator_post) ---
    global $wpdb;
    $table_name_tracking = $wpdb->prefix . 'aos_click_tracking';
    $trenta_giorni_fa = date('Y-m-d H:i:s', strtotime('-30 days'));

    // A. Identifica le 3 operatrici più popolari per questo genere.
    $term_taxonomy_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'genere'", $genere_term_id));
    $top_operatrici_ids = [];
    if ($term_taxonomy_id) {
        $top_operatrici_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT tr.object_id FROM {$wpdb->term_relationships} AS tr
             INNER JOIN {$table_name_tracking} AS c ON tr.object_id = c.post_id
             WHERE tr.term_taxonomy_id = %d AND c.click_timestamp >= %s
             GROUP BY tr.object_id ORDER BY COUNT(c.id) DESC LIMIT 3",
            $term_taxonomy_id, $trenta_giorni_fa
        ));
    }

    // B. Prendi TUTTE le operatrici di questo genere.
    $all_operator_ids_query = new WP_Query([
        'post_type' => 'operatrice', 'post_status' => 'publish', 'posts_per_page' => -1,
        'tax_query' => [['taxonomy' => 'genere', 'field' => 'term_id', 'terms' => $genere_term_id]],
        'fields' => 'ids'
    ]);
    $all_operator_ids = $all_operator_ids_query->posts;

    if ( empty($all_operator_ids) ) {
        return '<p class="uk-text-center">Al momento non ci sono operatrici disponibili per la categoria ' . esc_html($current_term->name) . '.</p>';
    }

    // C. Crea il "pool" di ID pesato. Le top operatrici hanno più "biglietti".
    $operator_pool = [];
    $peso_top_op = 5; // Puoi cambiare questo peso se vuoi
    $peso_normale_op = 1;

    foreach ($all_operator_ids as $op_id) {
        $peso = in_array($op_id, $top_operatrici_ids) ? $peso_top_op : $peso_normale_op;
        for ($i = 0; $i < $peso; $i++) {
            $operator_pool[] = $op_id;
        }
    }
    
    // --- 3. ESTRAI 5 OPERATRICI UNICHE DALLA LOTTERIA ---
    shuffle($operator_pool); // Mescola il pool pesato
    $final_ids_to_show = [];

    // Estrai ID unici finché non ne abbiamo 5 (o finché il pool non è finito)
    foreach ($operator_pool as $op_id) {
        if (!in_array($op_id, $final_ids_to_show)) {
            $final_ids_to_show[] = $op_id;
        }
        if (count($final_ids_to_show) >= 25) {
            break;
        }
    }

    if (empty($final_ids_to_show)) {
        return '<p class="uk-text-center">Nessuna operatrice trovata per questo genere.</p>';
    }
    
    // --- 4. PREPARA LA QUERY FINALE E RENDERIZZA ---
    $query_args = [
        'post_type'      => 'operatrice',
        'post_status'    => 'publish',
        'post__in'       => $final_ids_to_show,
        'orderby'        => 'post__in', // Mantiene l'ordine dell'estrazione
        'posts_per_page' => 25,
    ];
    $query = new WP_Query( $query_args );

    $grid_classes = 'aos-operators-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-match';
    
    $output = '';
    if ( function_exists('avs_render_operator_grid_with_quiz_injection') ) {
        $output = avs_render_operator_grid_with_quiz_injection($query, $grid_classes);
    } else {
        $output = '<p>Errore: Funzione di rendering non trovata.</p>';
    }

    wp_reset_postdata();
    return $output;
}