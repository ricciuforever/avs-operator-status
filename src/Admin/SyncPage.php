<?php

namespace AvsOperatorStatus\Admin;

use AvsOperatorStatus\Features\SyncNumerazioni;

/**
 * Class SyncPage
 *
 * Handles the admin page for manually syncing numerazioni to all operatrici.
 */
class SyncPage {
    /**
     * Registers all hooks for the sync page.
     */
    public function register() {
        add_action( 'admin_menu', [ $this, 'add_sync_page' ] );
    }

    /**
     * Adds the submenu page under the "Operatrici" CPT menu.
     */
    public function add_sync_page() {
        add_submenu_page(
            'edit.php?post_type=operatrice',
            'Sincronizza Numerazioni',
            'Sincronizza Numerazioni',
            'manage_options',
            'aos-sync-numerazioni',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Renders the HTML and handles the logic of the sync page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Sincronizzazione di Massa delle Numerazioni</h1>
            <p>Questa utility assegnerà automaticamente le numerazioni corrette a <strong>tutte</strong> le operatrici presenti nel database.<br>
            L'associazione si basa sulla corrispondenza tra il <strong>Genere</strong> dell'operatrice e la <strong>Descrizione</strong> di una numerazione.</p>
            <p><strong>Attenzione:</strong> Questa operazione sovrascriverà le attuali associazioni delle numerazioni. Usala per allineare tutti i dati la prima volta.</p>

            <form method="post">
                <?php wp_nonce_field( 'aos_sync_nonce_action', 'aos_sync_nonce_field' ); ?>
                <input type="hidden" name="aos_start_sync" value="1">
                <?php submit_button( 'Avvia Sincronizzazione Adesso' ); ?>
            </form>
            <hr>

            <?php
            if ( isset( $_POST['aos_start_sync'] ) && check_admin_referer( 'aos_sync_nonce_action', 'aos_sync_nonce_field' ) ) {
                echo '<h2>Risultati Sincronizzazione:</h2>';
                echo '<p>Sincronizzazione avviata...</p>';

                $operatrici = get_posts( [
                    'post_type'      => 'operatrice',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                ] );

                if ( empty( $operatrici ) ) {
                    echo '<p>Nessuna operatrice trovata da sincronizzare.</p>';
                } else {
                    $sync_feature = new SyncNumerazioni();
                    $conteggio = 0;
                    foreach ( $operatrici as $post_id ) {
                        $sync_feature->sync_on_save( $post_id );
                        $conteggio++;
                    }
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Operazione completata!</strong> Sono state processate ' . $conteggio . ' operatrici.</p></div>';
                }
            }
            ?>
        </div>
        <?php
    }
}
