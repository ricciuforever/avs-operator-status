<?php

namespace AvsOperatorStatus\Core;

/**
 * Class Frontend
 *
 * Handles general frontend hooks and modifications.
 */
class Frontend {
    /**
     * Registers all hooks for the frontend.
     */
    public function register() {
        add_filter( 'term_link', [ $this, 'modify_numerazione_term_link' ], 10, 3 );
        add_action( 'template_redirect', [ $this, 'disable_numerazione_archive' ] );
    }

    /**
     * Modifies the link for 'numerazione' taxonomy terms to be a 'tel:' link.
     *
     * @param string $url The original term link.
     * @param \WP_Term $term The term object.
     * @param string $taxonomy The taxonomy slug.
     * @return string The modified URL.
     */
    public function modify_numerazione_term_link( string $url, \WP_Term $term, string $taxonomy ): string {
        if ( 'numerazione' === $taxonomy ) {
            return 'tel:' . esc_attr( $term->name );
        }
        return $url;
    }

    /**
     * Redirects the archive page for the 'numerazione' taxonomy to the homepage.
     */
    public function disable_numerazione_archive() {
        if ( is_tax( 'numerazione' ) ) {
            wp_redirect( home_url(), 301 );
            exit;
        }
    }
}
