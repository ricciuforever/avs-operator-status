<?php
// includes/shortcode-vetrina-generi.php

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'aos_vetrina_generi', 'aos_display_vetrina_generi_shortcode' );

/**
 * Funzione di callback per lo shortcode [aos_vetrina_generi].
 * Utilizza la nuova funzione di rendering centralizzata avs_render_operator_grid_with_quiz_injection.
 */
function aos_display_vetrina_generi_shortcode( $atts ) {
    global $wpdb;

    // --- 1. Determina l'ordine dei generi basato sulla popolarità ---
    $tutti_i_generi = get_terms(['taxonomy' => 'genere', 'hide_empty' => true]);
    $excluded_genre_ids = [];
    foreach ($tutti_i_generi as $genere) { 
        if (stripos($genere->name, 'Basso Costo') !== false) { 
            $excluded_genre_ids[] = $genere->term_id; 
        } 
    }
    
    $exclusion_placeholder = !empty($excluded_genre_ids) ? 'AND t.term_id NOT IN (' . implode(',', array_fill(0, count($excluded_genre_ids), '%d')) . ')' : '';
    $table_name_tracking = $wpdb->prefix . 'aos_click_tracking';
    $trenta_giorni_fa = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $params = [$trenta_giorni_fa];
    if (!empty($excluded_genre_ids)) { 
        $params = array_merge($params, $excluded_genre_ids); 
    }
    
    $top_generi_ids = $wpdb->get_col($wpdb->prepare( 
        "SELECT t.term_id FROM {$table_name_tracking} AS c 
         INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id 
         INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
         INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id 
         WHERE tt.taxonomy = 'genere' AND c.click_timestamp >= %s {$exclusion_placeholder} 
         GROUP BY t.term_id ORDER BY COUNT(c.id) DESC LIMIT 3", 
        $params 
    ));
    
    $top_genre_slugs = []; 
    $other_genre_slugs = [];
    foreach ($tutti_i_generi as $genere) { 
        if (in_array($genere->term_id, $excluded_genre_ids)) { continue; } 
        if (in_array($genere->term_id, $top_generi_ids)) { 
            $top_genre_slugs[] = $genere->slug; 
        } else { 
            $other_genre_slugs[] = $genere->slug; 
        } 
    }
    
    shuffle($top_genre_slugs); 
    shuffle($other_genre_slugs);
    $generi_da_mostrare = array_merge($top_genre_slugs, $other_genre_slugs);
    
    // --- 2. Seleziona un'operatrice rappresentativa per ogni genere e raccoglie gli ID ---
    $operatrici_ids = [];
    foreach ( $generi_da_mostrare as $genere_slug ) {
        $genere_term = get_term_by('slug', $genere_slug, 'genere');
        if ($genere_term) {
            $operatrice_post = aos_get_weighted_random_operator_post($genere_term->term_id);
            if ($operatrice_post) {
                // Aggiungiamo solo l'ID del post all'array
                $operatrici_ids[] = $operatrice_post->ID;
            }
        }
    }
    
    if (empty($operatrici_ids)) { 
        return '<p>Nessuna operatrice disponibile.</p>'; 
    }

    // --- 3. Prepara gli argomenti per la funzione di rendering ---
    // Crea un oggetto WP_Query, come richiesto dalla nuova funzione.
    $query_args = [
        'post_type'      => 'operatrice',
        'post__in'       => $operatrici_ids,
        'orderby'        => 'post__in', // Mantiene l'ordine specifico degli ID
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($query_args);

    // Definisce le classi CSS per la griglia.
    $grid_classes = 'aos-operators-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-match';
    
    // --- 4. Renderizza l'output finale tramite la funzione centralizzata ---
    $output = '<p class="uk-text-center uk-margin-medium-bottom">Componi il numero e chiedi di parlare con operatrice scelta</p>';
    
    // La nuova funzione si occupa di tutto il rendering della griglia.
    $output .= avs_render_operator_grid_with_quiz_injection($query, $grid_classes);
    
    // WordPress ha bisogno che venga eseguito il reset della query dopo un loop personalizzato
    // anche se il loop è in un'altra funzione.
    wp_reset_postdata();

    return $output;
}