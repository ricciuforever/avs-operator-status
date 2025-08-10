<?php

namespace AvsOperatorStatus\Integrations;

/**
 * Class Yoast
 *
 * Handles integrations with the Yoast SEO plugin.
 */
class Yoast {
    /**
     * Registers all hooks for Yoast integration.
     */
    public function register() {
        add_filter( 'wpseo_title', [ $this, 'custom_yoast_title_operatrice_archive' ], 100, 1 );
    }

    /**
     * Modifies the SEO title for the 'operatrice' CPT archive page.
     *
     * @param string $title The original title from Yoast.
     * @return string The modified title.
     */
    public function custom_yoast_title_operatrice_archive( string $title ): string {
        if ( is_post_type_archive( 'operatrice' ) ) {
            return 'Elenco Completo delle Nostre Operatrici | Linee Bollenti';
        }
        return $title;
    }
}
