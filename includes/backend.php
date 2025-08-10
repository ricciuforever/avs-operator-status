<?php
// includes/backend.php - VERSIONE FINALE COMPLETA CON TUTTE LE FUNZIONI

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// FUNZIONI PER I CONTATORI "VIEWS" E "CLICKS" IN BACHECA
// =========================================================================

/**
 * Gestisce le visualizzazioni per il contatore della singola operatrice.
 */
function aos_handle_track_views_ajax() {
    // ==========================================================
    // --- CORREZIONE: Aggiunto filtro anti-bot ---
    if (aos_is_request_from_bot()) {
        wp_send_json_success(['message' => 'Bot ignored.']);
        return;
    }
    // ==========================================================

    if ( !isset($_POST['nonce']) || !wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) { wp_send_json_error('Security check failed.'); return; }
    if (empty($_POST['codes']) || !is_array($_POST['codes'])) { return; }
    
    $post_ids = array_map('intval', $_POST['codes']);
    foreach ($post_ids as $post_id) {
        if ($post_id > 0) {
            $views = (int) get_post_meta($post_id, 'aos_views', true);
            update_post_meta($post_id, 'aos_views', $views + 1);
        }
    }
    wp_send_json_success();
}
add_action('wp_ajax_nopriv_aos_track_views', 'aos_handle_track_views_ajax');



/**
 * Gestisce i click per il contatore della singola operatrice.
 */
function aos_handle_track_click_ajax() {
    // ==========================================================
    // --- CORREZIONE: Aggiunto filtro anti-bot ---
    if (aos_is_request_from_bot()) {
        wp_send_json_success(['message' => 'Bot ignored.']);
        return;
    }
    // ==========================================================

    if ( !isset($_POST['nonce']) || !wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) { wp_send_json_error('Security check failed.'); return; }
    if (empty($_POST['code'])) { return; }

    $post_id = intval($_POST['code']);
    if ($post_id > 0) {
        $clicks = (int) get_post_meta($post_id, 'aos_clicks', true);
        update_post_meta($post_id, 'aos_clicks', $clicks + 1);
    }
    wp_send_json_success();
}
add_action('wp_ajax_nopriv_aos_track_click', 'aos_handle_track_click_ajax');



/**
 * Controlla se la richiesta corrente proviene da un bot conosciuto.
 * Funzione centralizzata per il rilevamento dei bot.
 *
 * @return bool True se è un bot, altrimenti false.
 */
function aos_is_request_from_bot() {
    // Recupera lo user agent del visitatore, se esiste.
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($user_agent)) {
        return false; // Se non c'è user agent, assumiamo non sia un bot.
    }

    // Lista di parole chiave che identificano i bot.
    $bot_keywords = [
        'bot', 
        'spider', 
        'crawler', 
        'slurp', 
        'mediapartners',
        'AhrefsBot',
        'SemrushBot',
        'MegaIndex',
        'BLEXBot'
    ];

    foreach ($bot_keywords as $keyword) {
        if (stripos($user_agent, $keyword) !== false) {
            return true; // Trovata una corrispondenza, è un bot.
        }
    }

    return false; // Nessuna corrispondenza, non è un bot.
}

// =========================================================================
// FUNZIONE PER IL LOG GLOBALE DETTAGLIATO (PER PAGINA STATISTICHE)
// =========================================================================

/**
 * Logga un click globale (da qualsiasi punto del sito) nella tabella delle statistiche.
 */
function aos_log_global_click_ajax() {
    // Controllo anti-bot già presente e corretto
    if (aos_is_request_from_bot()) {
        wp_send_json_success(['message' => 'Bot ignored.']);
        return;
    }
    
    if ( !wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) { wp_send_json_error('Nonce non valido'); return; }
    if ( empty($_POST['numerazione_id']) ) { wp_send_json_error('Dati mancanti'); return; }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'aos_click_tracking';
    $numerazione_id = intval($_POST['numerazione_id']);
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $context_url = isset($_POST['context_url']) ? esc_url_raw($_POST['context_url']) : '';
    
    $wpdb->insert($table_name,
        ['post_id' => $post_id, 'numerazione_term_id' => $numerazione_id, 'click_context' => $context_url, 'click_timestamp' => current_time('mysql')],
        ['%d', '%d', '%s', '%s']
    );
    wp_send_json_success();
}
add_action('wp_ajax_nopriv_aos_log_global_click', 'aos_log_global_click_ajax');

// =========================================================================
// FUNZIONE HELPER PER L'EDITOR DI WORDPRESS
// =========================================================================

/**
 * Aggiunge la descrizione al nome del termine nell'editor Gutenberg.
 */
add_filter( 'rest_prepare_numerazione', 'aos_aggiungi_descrizione_a_termini_rest_api', 10, 2 );
function aos_aggiungi_descrizione_a_termini_rest_api( $response, $term ) {
    if ( ! empty( $term->description ) ) { $response->data['name'] .= ' (' . $term->description . ')'; }
    return $response;
}

// =========================================================================
// ENDPOINT AJAX PER IL SISTEMA DI PREFERITI BASATO SU COOKIE
// =========================================================================

add_action('wp_ajax_nopriv_aos_get_favorite_cards_html', 'aos_handle_get_favorite_cards_html_ajax');
add_action('wp_ajax_aos_get_favorite_cards_html', 'aos_handle_get_favorite_cards_html_ajax'); // Anche per utenti loggati

/**
 * Riceve una lista di ID di operatrici e restituisce l'HTML delle loro card.
 */
function aos_handle_get_favorite_cards_html_ajax() {
    if ( !isset($_POST['nonce']) || !wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
        wp_send_json_error('Security check failed.');
        return;
    }

    if ( empty($_POST['operator_ids']) || !is_array($_POST['operator_ids']) ) {
        wp_send_json_error('Dati mancanti o non validi.');
        return;
    }

    // Sanifichiamo gli ID ricevuti per sicurezza
    $operator_ids = array_map('intval', $_POST['operator_ids']);

    // Prepariamo la query per ottenere solo le operatrici richieste
    $query_args = [
        'post_type'      => 'operatrice',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'post__in'       => $operator_ids,
        'orderby'        => 'post__in', // Mantiene l'ordine degli ID
    ];

    $query = new WP_Query($query_args);

    if ( ! $query->have_posts() ) {
        wp_send_json_success('<p>Nessuna operatrice trovata tra i preferiti.</p>');
        return;
    }

    // Usiamo il nostro nuovo helper per generare l'HTML di ogni card
    $html_output = '';
    while ( $query->have_posts() ) {
        $query->the_post();
        // Aggiungiamo un div contenitore per la griglia UIkit
        $html_output .= '<div>' . aos_render_operator_card_html( get_post() ) . '</div>';
    }
    wp_reset_postdata();

    // Inviamo l'HTML generato al frontend
    wp_send_json_success($html_output);
}

// =========================================================================
// ENDPOINT AJAX PER L'AGGIORNAMENTO DEL CONTEGGIO DEI PREFERITI
// =========================================================================

add_action('wp_ajax_nopriv_aos_update_favorite_count', 'aos_handle_update_favorite_count_ajax');
add_action('wp_ajax_aos_update_favorite_count', 'aos_handle_update_favorite_count_ajax');

/**
 * Riceve un ping dal frontend e incrementa o decrementa
 * il contatore dei preferiti per una specifica operatrice.
 */
function aos_handle_update_favorite_count_ajax() {
    if ( !isset($_POST['nonce']) || !wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
        wp_send_json_error('Security check failed.');
        return;
    }

    if ( empty($_POST['operator_id']) || empty($_POST['action_type']) ) {
        wp_send_json_error('Dati mancanti.');
        return;
    }

    $operator_id = intval($_POST['operator_id']);
    $action_type = sanitize_text_field($_POST['action_type']);

    if ($operator_id > 0 && in_array($action_type, ['add', 'remove'])) {
        
        // Recupera il conteggio attuale, o lo imposta a 0 se non esiste
        $current_count = (int) get_post_meta($operator_id, '_aos_favorites_count', true);

        if ($action_type === 'add') {
            $new_count = $current_count + 1;
        } else {
            // Impedisce che il conteggio vada sotto lo zero
            $new_count = max(0, $current_count - 1);
        }

        update_post_meta($operator_id, '_aos_favorites_count', $new_count);

        wp_send_json_success(['new_count' => $new_count]);

    } else {
        wp_send_json_error('Dati non validi.');
    }
}