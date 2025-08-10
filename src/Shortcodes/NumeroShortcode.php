<?php

namespace AvsOperatorStatus\Shortcodes;

/**
 * Class NumeroShortcode
 *
 * Handles the legacy [numero] shortcode.
 */
class NumeroShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'numero', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        $atts = shortcode_atts( [
            'numerazione' => '',
            'genere'      => '',
            'testo'       => ''
        ], $atts, 'numero' );

        $numero_tel = sanitize_text_field( $atts['numerazione'] );
        $genere_slug = sanitize_text_field( $atts['genere'] );

        if ( empty( $numero_tel ) ) {
            return current_user_can( 'manage_options' ) ? '<p style="color:red;">Shortcode [numero]: Manca l\'attributo "numerazione".</p>' : '';
        }

        if ( ! empty( $genere_slug ) ) {
            if ( is_singular( 'operatrice' ) ) {
                if ( ! has_term( $genere_slug, 'genere', get_the_ID() ) ) {
                    return '';
                }
            } else {
                return '';
            }
        }

        $testo_btn = ! empty( $atts['testo'] ) ? sanitize_text_field( $atts['testo'] ) : 'Chiama ' . $numero_tel;

        return sprintf(
            '<a href="tel://%1$s" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom">%2$s</a>',
            esc_attr( $numero_tel ),
            esc_html( $testo_btn )
        );
    }
}
