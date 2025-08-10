<?php
// includes/frontend.php - VERSIONE RIFATTORIZZATA E UNIFICATA

if ( ! defined( 'ABSPATH' ) ) exit;

// Aggancia la funzione principale di setup per il frontend.
add_action('init', 'aos_frontend_setup');

/**
 * Registra tutti gli hooks per l'area frontend.
 */
function aos_frontend_setup() {
    add_action('wp_enqueue_scripts', 'aos_enqueue_frontend_assets');
    add_filter('term_link', 'aos_modifica_link_numerazione', 10, 3);
    add_action('template_redirect', 'aos_disabilita_archivio_numerazione');
}

/**
 * Accoda lo stile CSS e lo script JS UNIFICATO per il frontend.
 * Passa tutti i dati necessari in un unico oggetto.
 */
function aos_enqueue_frontend_assets() {
    // 1. Carica lo stile CSS principale del plugin.
    wp_enqueue_style('aos-style', plugin_dir_url(__FILE__) . '../css/style.css', [], '2.5');

    // 2. Raccogli i dati necessari per JavaScript.
    $frontend_params = [
        'ajax_url'            => admin_url('admin-ajax.php'),
        'nonce'               => wp_create_nonce('aos_tracking_nonce'),
        'is_operatrice_page'  => is_singular('operatrice'),
        'post_id'             => is_singular() ? get_the_ID() : 0,
        'promo_list'          => [],
        'mappa_numeri'        => [],
        'mappa_tariffe'       => [],
    ];

    $all_terms = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false]);
    if (!is_wp_error($all_terms) && !empty($all_terms)) {
        foreach ($all_terms as $term) {
            $numero_pulito = preg_replace('/[^0-9]/', '', $term->name);
            if (empty($numero_pulito)) continue;
            $frontend_params['mappa_numeri'][$numero_pulito] = $term->term_id;
            if (get_term_meta($term->term_id, '_aos_promo_attiva', true) === 'si') {
                $messaggio = get_term_meta($term->term_id, '_aos_promo_messaggio', true);
                if (!empty($messaggio)) {
                    $frontend_params['promo_list'][$numero_pulito] = ['messaggio' => $messaggio];
                }
            }
            $tariffe = get_term_meta($term->term_id, '_aos_tariffe_meta', true);
            if (!empty($tariffe) && is_array($tariffe)) {
                $frontend_params['mappa_tariffe'][$numero_pulito] = $tariffe;
            }
        }
    }

    // 3. Accoda lo script UNIFICATO.
    wp_enqueue_script('aos-frontend-main', plugin_dir_url(__FILE__) . '../js/aos-frontend-main.js', ['jquery'], '2.5', true);
    wp_localize_script('aos-frontend-main', 'aos_frontend_params', $frontend_params);
    
    // 4. Aggiunge il CSS inline per tutte le funzionalità dinamiche.
    $inline_css = "
        .promo-wrapper { position: relative !important; display: flex !important; width: 100% !important; margin-bottom: 10px; }
        .promo-wrapper > a { flex-grow: 1; }
        .promo-toggle-container { position: absolute; top: -8px; left: -8px; z-index: 3; }
        .promo-label, .promo-label:hover { color: #ffffff !important; text-decoration: none; }

        /* --- CSS CORRETTO PER I PREFERITI --- */
        .aos-favorite-container {
            position: absolute !important; /* <-- CORREZIONE CHIAVE: Aggiunto posizionamento assoluto */
            top: 0px;
            right: 0px;
            z-index: 5;
            display: flex;
            align-items: center;
                display: inline-block;
                padding: 2px 7px;
                background: #ccc;
                color:#000; 
    line-height: 1.5;
    font-size: 12px;
    vertical-align: middle;
    white-space: nowrap;
    font-family: Inter;
    font-weight: 600;
    border-radius: 5px;
} 
        }
        .aos-favorite-toggle {
            color: #ccc;
            transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        .aos-favorite-toggle:hover {
            color: #F0506E;
            transform: scale(1.1);
        }
        .aos-favorite-toggle.is-favorite {
            color: #F0506E;
        }
        .aos-view-favorites-link {
            font-size: 0.75rem;
            margin-left: 5px;
            color: #fff;
            text-decoration: underline;
            padding: 2px 7px;
    background: #ca0710;
    line-height: 1.5;
    font-size: 12px;
    vertical-align: middle;
    white-space: nowrap;
    font-family: Inter;
    font-weight: 600;
    border-radius: 5px;
        }
        .aos-view-favorites-link:hover {
            color: #333;
        }
    ";
    wp_add_inline_style('aos-style', $inline_css);
}

/**
 * Modifica il link per i termini della tassonomia 'numerazione'.
 */
function aos_modifica_link_numerazione( $url, $term, $taxonomy ) {
    if ('numerazione' === $taxonomy) {
        $url = 'tel:' . esc_attr($term->name);
    }
    return $url;
}

/**
 * Reindirizza alla homepage gli archivi della tassonomia 'numerazione'.
 */
function aos_disabilita_archivio_numerazione() {
    if (is_tax('numerazione')) {
        wp_redirect(home_url(), 301);
        exit;
    }
}