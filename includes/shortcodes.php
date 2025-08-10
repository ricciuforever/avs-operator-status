<?php
// includes/shortcodes.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Funzione helper globale per la priorità delle numerazioni.
 * Restituisce un valore numerico per ordinare i pulsanti.
 *
 * @param string $description La descrizione della numerazione.
 * @return int La priorità.
 */
function aos_get_numerazione_priority( $description ) {
    if ( stripos( $description, 'Carta di Credito' ) !== false ) return 1;
    if ( stripos( $description, 'Ricarica Online' ) !== false ) return 2;
    if ( stripos( $description, 'Svizzera' ) !== false ) return 99;
    return 10; // Priorità di default per 899 etc.
}


// =================================================================
// CARICATORE UNICO PER TUTTI I FILE DEGLI SHORTCODE
// =================================================================
require_once plugin_dir_path( __FILE__ ) . 'shortcode-operatrici.php';          // Lo shortcode [aos_operatrici] che abbiamo appena spostato.
require_once plugin_dir_path( __FILE__ ) . 'shortcode-vetrina-generi.php';      // Lo shortcode [aos_vetrina_generi].
require_once plugin_dir_path( __FILE__ ) . 'shortcode-vetrina-svizzera.php';    // Lo shortcode [aos_generi_svizzera].
require_once plugin_dir_path( __FILE__ ) . 'shortcode-basso-costo.php';         // Lo shortcode [aos_basso_costo_vetrina].
require_once plugin_dir_path( __FILE__ ) . 'shortcode-carta.php';               // Lo shortcode [aos_vetrina_carta_credito].
require_once plugin_dir_path( __FILE__ ) . 'shortcode-tariffe-numerazioni.php'; // Lo shortcode [tariffe_numerazioni].
require_once plugin_dir_path( __FILE__ ) . 'shortcode-miei-preferiti.php'; // Lo shortcode [aos_miei_preferiti].
require_once plugin_dir_path( __FILE__ ) . 'shortcode-bottoni-singola.php';
require_once plugin_dir_path( __FILE__ ) . 'shortcode-vetrina-genere-corrente.php';


// Registra solo gli shortcode "minori" o legacy che non hanno un file dedicato.
add_action('init', function() {
    add_shortcode('numero', 'aos_display_numero_button_shortcode');
    // Gli altri shortcode principali sono registrati all'interno dei loro rispettivi file.
});

/**
 * Funzione di callback per lo shortcode legacy [numero].
 *
 * @param array $atts Attributi dello shortcode.
 * @return string L'HTML del pulsante o una stringa vuota.
 */
function aos_display_numero_button_shortcode($atts) {
    $atts = shortcode_atts(['numerazione' => '', 'genere' => '', 'testo' => ''], $atts, 'numero');

    $numero_tel = sanitize_text_field($atts['numerazione']);
    $genere_slug = sanitize_text_field($atts['genere']);

    if (empty($numero_tel)) {
        return current_user_can('manage_options') ? '<p style="color:red;">Shortcode [numero]: Manca l\'attributo "numerazione".</p>' : '';
    }
    if (!empty($genere_slug)) {
        if (is_singular('operatrice')) {
            if (!has_term($genere_slug, 'genere', get_the_ID())) {
                return ''; 
            }
        } else {
            return '';
        }
    }
    
    $testo_btn = !empty($atts['testo']) ? sanitize_text_field($atts['testo']) : 'Chiama ' . $numero_tel;
    
    return sprintf(
        '<a href="tel://%1$s" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom">%2$s</a>',
        esc_attr($numero_tel),
        esc_html($testo_btn)
    );
}