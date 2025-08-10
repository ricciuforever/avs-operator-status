<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class OperatriciShortcode
 *
 * Handles the [aos_operatrici] shortcode.
 */
class OperatriciShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_operatrici', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        \AvsOperatorStatus\Plugin::instance()->asset_manager->request( [ 'aos-style', 'aos-frontend-main' ] );

        $atts = shortcode_atts( [
            'genere'           => '',
            'numerazione'      => '',
            'orderby'          => 'rand',
            'order'            => 'DESC',
            'limit'            => -1,
            'meta_key'         => '',
            'columns'          => '3',
            'columns_tablet'   => '2',
            'columns_mobile'   => '1',
            'gap'              => 'medium',
        ], $atts, 'aos_operatrici' );

        $args = [
            'post_type'      => 'operatrice',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'meta_key'       => sanitize_text_field( $atts['meta_key'] ),
            'orderby'        => ( $atts['orderby'] === 'rand' ) ? 'rand' : sanitize_key( $atts['orderby'] ),
        ];

        if ( $atts['orderby'] !== 'rand' ) {
            $args['order'] = in_array( strtoupper( $atts['order'] ), [ 'ASC', 'DESC' ] ) ? strtoupper( $atts['order'] ) : 'ASC';
        }

        if ( ! empty( $atts['genere'] ) ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'genere',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $atts['genere'] ),
                ],
            ];
        }

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return '<p>Nessuna operatrice trovata.</p>';
        }

        ob_start();

        $grid_classes = "uk-child-width-1-{$atts['columns_mobile']}@s uk-child-width-1-{$atts['columns_tablet']}@m uk-child-width-1-{$atts['columns']}@l";

        echo '<p class="uk-text-center uk-margin-medium-bottom">Componi il numero e chiedi di parlare con l\'operatrice scelta</p>';

        echo \AvsOperatorStatus\Utils\Helpers::avs_render_operator_grid_with_quiz_injection( $query, $grid_classes, $atts['gap'] );

        wp_reset_postdata();

        return ob_get_clean();
    }
}
