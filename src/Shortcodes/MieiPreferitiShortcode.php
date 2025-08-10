<?php

namespace AvsOperatorStatus\Shortcodes;

/**
 * Class MieiPreferitiShortcode
 *
 * Handles the [aos_miei_preferiti] shortcode.
 */
class MieiPreferitiShortcode {

    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_miei_preferiti', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        \AvsOperatorStatus\Plugin::instance()->asset_manager->request( [ 'aos-style', 'aos-frontend-main' ] );

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
}
