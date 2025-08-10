<?php
// includes/admin-sync-page.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Aggiunge la pagina di sincronizzazione come sottomenu di "Operatrici".
 */
add_action('admin_menu', 'aos_add_sync_page');
function aos_add_sync_page() {
    add_submenu_page(
        'edit.php?post_type=operatrice',
        'Sincronizza Numerazioni',
        'Sincronizza Numerazioni',
        'manage_options',
        'aos-sync-numerazioni',
        'aos_render_sync_page_html'
    );
}

/**
 * Renderizza l'HTML e gestisce la logica della pagina di sincronizzazione.
 */
function aos_render_sync_page_html() {
    ?>
    <div class="wrap">
        <h1>Sincronizzazione di Massa delle Numerazioni</h1>
        <p>
            Questa utility assegnerà automaticamente le numerazioni corrette a **tutte** le operatrici presenti nel database.<br>
            L'associazione si basa sulla corrispondenza tra il <strong>Genere</strong> dell'operatrice e la <strong>Descrizione</strong> di una numerazione.
        </p>
        <p>
            <strong>Attenzione:</strong> Questa operazione sovrascriverà le attuali associazioni delle numerazioni. Usala per allineare tutti i dati la prima volta.
        </p>

        <form method="post">
            <?php wp_nonce_field('aos_sync_nonce_action', 'aos_sync_nonce_field'); ?>
            <input type="hidden" name="aos_start_sync" value="1">
            <?php submit_button('Avvia Sincronizzazione Adesso'); ?>
        </form>
        <hr>

        <?php
        // Controlla se il form è stato inviato e il nonce è valido
        if (isset($_POST['aos_start_sync']) && check_admin_referer('aos_sync_nonce_action', 'aos_sync_nonce_field')) {
            
            echo '<h2>Risultati Sincronizzazione:</h2>';
            echo '<p>Sincronizzazione avviata...</p>';

            // Recupera tutte le operatrici
            $operatrici = get_posts([
                'post_type'      => 'operatrice',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids', // Ci basta l'ID
            ]);

            if (empty($operatrici)) {
                echo '<p>Nessuna operatrice trovata da sincronizzare.</p>';
            } else {
                $conteggio = 0;
                // Per ogni operatrice, esegui la funzione di sincronizzazione che abbiamo già scritto
                foreach ($operatrici as $post_id) {
                    aos_sincronizza_numerazioni_per_post($post_id);
                    $conteggio++;
                }
                echo '<div class="notice notice-success is-dismissible"><p><strong>Operazione completata!</strong> Sono state processate ' . $conteggio . ' operatrici.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}