<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class VetrinaCartaCreditoShortcode
 *
 * Handles the [aos_vetrina_carta_credito] shortcode.
 */
class VetrinaCartaCreditoShortcode {

    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_vetrina_carta_credito', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        \AvsOperatorStatus\Plugin::instance()->asset_manager->request( [ 'aos-style', 'aos-frontend-main' ] );

        $atts = shortcode_atts( [ 'columns' => '3', 'gap' => 'medium' ], $atts, 'aos_vetrina_carta_credito' );

        // 1. Get all genres that are not "basso costo"
        $tutti_i_generi_slugs = get_terms( [
            'taxonomy'   => 'genere',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'fields'     => 'slugs'
        ] );

        $generi_da_mostrare = array_filter( $tutti_i_generi_slugs, function( $slug ) {
            $term = get_term_by( 'slug', $slug, 'genere' );
            return ( stripos( $term->name, 'Basso Costo' ) === false );
        } );

        if ( empty( $generi_da_mostrare ) ) {
            return '<p>Nessun genere trovato.</p>';
        }

        // 2. Select one operator for each genre and collect IDs
        $operatrici_ids = [];
        foreach ( $generi_da_mostrare as $genere_slug ) {
            $operatrice_post = get_posts( [
                'post_type'      => 'operatrice',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'rand',
                'tax_query'      => [ [ 'taxonomy' => 'genere', 'field' => 'slug', 'terms' => $genere_slug ] ]
            ] );
            if ( ! empty( $operatrice_post ) ) {
                $operatrici_ids[] = $operatrice_post[0]->ID;
            }
        }

        if ( empty( $operatrici_ids ) ) {
            return '<p>Nessuna operatrice disponibile.</p>';
        }

        // 3. Prepare the query and render with the centralized function
        $query = new WP_Query( [
            'post_type'      => 'operatrice',
            'post__in'       => $operatrici_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => -1
        ] );

        $grid_classes = 'aos-operators-grid uk-grid-match uk-child-width-1-2@s uk-child-width-1-3@l uk-grid-' . esc_attr( $atts['gap'] );

        $output = \AvsOperatorStatus\Utils\Helpers::avs_render_operator_grid_with_quiz_injection( $query, $grid_classes );
        wp_reset_postdata();

        return $output;
    }
}
