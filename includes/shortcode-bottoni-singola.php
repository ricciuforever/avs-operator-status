<?php
/**
 * Plugin Name: AOS Bottoni Operatrice
 * Author: Emanuele Tolomei
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Rimuovo il vecchio shortcode per sicurezza prima di aggiungerlo di nuovo
remove_shortcode('aos_bottoni_operatrice');
add_shortcode('aos_bottoni_operatrice', 'aos_display_bottoni_operatrice_shortcode_v2');

/**
 * Funzione di callback per lo shortcode [aos_bottoni_operatrice].
 *
 * Versione 2.1: Unisce la flessibilità della v2 con il wrapper '.cartomante'
 * per garantire la compatibilità con lo script JS principale.
 *
 * @param array $atts Attributi dello shortcode (es. 'id').
 * @return string L'HTML dei bottoni o un messaggio di errore/debug.
 */
function aos_display_bottoni_operatrice_shortcode_v2($atts) {
    // 1. Unisco gli attributi passati con i valori di default.
    $atts = shortcode_atts(
        array(
            'id' => '', // L'ID di default è vuoto.
        ),
        $atts,
        'aos_bottoni_operatrice'
    );

    // 2. Determino l'ID dell'operatrice
    $operatrice_id = $atts['id'];
    if (empty($operatrice_id)) {
        $operatrice_id = get_the_ID();
    }

    // 3. Controllo di sicurezza: se l'ID non è valido o il post non è un'operatrice, esco.
    if (empty($operatrice_id) || get_post_type($operatrice_id) !== 'operatrice') {
        if (current_user_can('manage_options')) {
            return '<p style="color:red;">[aos_bottoni_operatrice] non ha trovato un\'operatrice valida in questo contesto.</p>';
        }
        return '';
    }

    // --- Da qui in poi la logica è la stessa, ma usa $operatrice_id ---

    // 4. Raccogliamo tutti i dati necessari per questa operatrice
    $generi = get_the_terms($operatrice_id, 'genere');
    $codice_da_tracciare = $operatrice_id;

    if (!$generi || is_wp_error($generi)) {
        return '';
    }
    
    $genere_nome = $generi[0]->name;
    $genere_slug = $generi[0]->slug;

    // 5. Recuperiamo tutte le numerazioni e la mappa DDI
    $tutte_le_numerazioni = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false]);
    $mappa_genere_ddi = function_exists('aos_get_ddi_map') ? aos_get_ddi_map() : array();

    // 6. Filtriamo le numerazioni pertinenti
    $numerazioni_filtrate = [];
    if (!empty($genere_nome) && !is_wp_error($tutte_le_numerazioni)) {
        foreach ($tutte_le_numerazioni as $numerazione) {
            if (stripos($numerazione->description, $genere_nome) !== false && stripos($numerazione->description, 'Svizzera') === false) {
                $numerazioni_filtrate[] = $numerazione;
            }
        }
    }

    // Inizia l'output buffering
    ob_start();

    // --- MODIFICA CHIAVE: Aggiungiamo il contenitore '.cartomante' ---
    // Questo permette allo script aos-frontend-main.js di "vedere" questo blocco
    // e di applicare tutte le sue funzionalità (preferiti, etichette, tracking).
    ?>
    <div class="cartomante" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
    <?php

    // 7. Se abbiamo trovato dei pulsanti, li prepariamo e li stampiamo
    if (!empty($numerazioni_filtrate)) {
        
        if (function_exists('aos_get_numerazione_priority')) {
            usort($numerazioni_filtrate, function($a, $b) {
                return aos_get_numerazione_priority($a->description) <=> aos_get_numerazione_priority($b->description);
            });
        }

        $ha_chiama_e_ricarica = false;
        foreach ($numerazioni_filtrate as $numerazione_check) {
            if (stripos($numerazione_check->description, 'Carta di Credito') !== false) {
                $ha_chiama_e_ricarica = true;
                break;
            }
        }
        
        echo '<div class="aos-single-operator-buttons uk-margin-medium-top">';
        // Assumendo che il nome dell'operatrice sia il titolo del post
// Questa è la situazione più comune in WordPress.
$operator_name = get_the_title();

lineebollenti_display_operator_audio($operator_name);
        if (function_exists('aos_render_payment_button')) {
            foreach ($numerazioni_filtrate as $numerazione) {
                echo aos_render_payment_button(
                    $numerazione,
                    $genere_slug,
                    $mappa_genere_ddi,
                    $codice_da_tracciare,
                    $ha_chiama_e_ricarica
                );
            }
        }
        
        echo '</div>';

    } else {
        echo '<p class="uk-text-center uk-margin-medium-top"><em>Nessuna opzione di chiamata disponibile per questa operatrice.</em></p>';
    }

    // --- MODIFICA CHIAVE: Chiudiamo il contenitore '.cartomante' ---
    ?>
    </div> 
    <?php

    return ob_get_clean();
}