<?php
// includes/shortcode-miei-preferiti.php

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('aos_miei_preferiti', 'aos_display_miei_preferiti_shortcode');

/**
 * Funzione di callback per lo shortcode [aos_miei_preferiti].
 * Mostra la lista delle operatrici preferite salvate nel cookie dell'utente.
 */
function aos_display_miei_preferiti_shortcode($atts) {
    ob_start();
    ?>
    <div class="aos-favorites-page">

        <div class="uk-alert-primary" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p>In questa pagina trovi le tue operatrici preferite. La lista è salvata tramite cookie direttamente nel tuo browser. Se cancelli i cookie del sito, questa lista verrà eliminata.</p>
        </div>

        <button id="aos-clear-favorites" class="uk-button uk-button-danger uk-margin-bottom" style="display: none;">
            <span uk-icon="icon: trash"></span> Rimuovi tutti i preferiti
        </button>

        <div id="aos-favorites-container" class="uk-grid-match uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
            <div class="uk-width-1-1">
                <p><em>Caricamento dei tuoi preferiti...</em></p>
                <div uk-spinner="ratio: 2"></div>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}