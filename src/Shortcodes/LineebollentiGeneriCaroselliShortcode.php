<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class LineebollentiGeneriCaroselliShortcode
 *
 * Handles the [lineebollenti_generi_caroselli] shortcode.
 */
class LineebollentiGeneriCaroselliShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'lineebollenti_generi_caroselli', [ $this, 'render' ] );
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

        $excluded_genre_slugs = [];
        $basso_costo_terms = get_terms( [
            'taxonomy'   => 'genere',
            'name__like' => 'Basso Costo',
            'fields'     => 'slugs',
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $basso_costo_terms ) && ! empty( $basso_costo_terms ) ) {
            $excluded_genre_slugs = array_merge( $excluded_genre_slugs, $basso_costo_terms );
        }

        $generi_da_mostrare_ordinati = [];

        $etero_term = get_term_by( 'name', 'Etero', 'genere' );
        if ( $etero_term && ! in_array( $etero_term->slug, $excluded_genre_slugs ) ) {
            $generi_da_mostrare_ordinati[] = $etero_term;
        }

        $all_other_generi = get_terms( [
            'taxonomy' => 'genere',
            'hide_empty' => true,
            'exclude' => array_merge(
                ( ! empty( $etero_term ) ? [ $etero_term->term_id ] : [] ),
                array_map( function( $slug ) {
                    $term = get_term_by( 'slug', $slug, 'genere' );
                    return $term ? $term->term_id : 0;
                }, $excluded_genre_slugs )
            ),
        ] );

        $filtered_other_generi = [];
        $already_added_slugs = array_map( function( $term ) {
            return $term->slug;
        }, $generi_da_mostrare_ordinati );

        if ( ! empty( $all_other_generi ) && ! is_wp_error( $all_other_generi ) ) {
            foreach ( $all_other_generi as $genere ) {
                if ( ! in_array( $genere->slug, $excluded_genre_slugs ) && ! in_array( $genere->slug, $already_added_slugs ) ) {
                    $filtered_other_generi[] = $genere;
                }
            }
        }

        shuffle( $filtered_other_generi );
        $generi_da_mostrare_final = array_merge( $generi_da_mostrare_ordinati, $filtered_other_generi );

        if ( empty( $generi_da_mostrare_final ) ) {
            echo '<p>Nessun genere di operatrici trovato.</p>';
            return ob_get_clean();
        }

        foreach ( $generi_da_mostrare_final as $genere ) {
            $operatrici_query = new WP_Query( [
                'post_type' => 'operatrice',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'tax_query' => [ [
                    'taxonomy' => 'genere',
                    'field' => 'term_id',
                    'terms' => $genere->term_id,
                ] ],
                'orderby' => 'rand',
            ] );

            if ( $operatrici_query->have_posts() ) {
                ?>
                <hr>
                <div class="uk-section uk-section-small">
                    <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">Genere: <?php echo esc_html( $genere->name ); ?></h2>
                    <div class="uk-position-relative" tabindex="-1">
                        <div class="uk-position-relative uk-visible-toggle uk-light" tabindex="-1" uk-slider="sets: true">
                            <ul class="uk-slider-items uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-3@l uk-grid">
                                <?php
                                while ( $operatrici_query->have_posts() ) {
                                    $operatrici_query->the_post();
                                    ?>
                                    <li>
                                        <?php
                                        echo \AvsOperatorStatus\Utils\Helpers::aos_render_operator_card_html( get_the_ID() );
                                        ?>
                                    </li>
                                    <?php
                                }
                                wp_reset_postdata();
                                ?>
                            </ul>
                            <ul class="uk-slider-nav uk-dotnav uk-flex-center uk-margin"></ul>
                        </div>
                        <div class="uk-hidden@s uk-visible-toggle uk-position-center-left-out uk-position-center-right-out uk-position-small">
                            <a class="uk-slidenav-large uk-slidenav-previous uk-slidenav-contrast" href="#" uk-slider-item="previous"></a>
                            <a class="uk-slidenav-large uk-slidenav-next uk-slidenav-contrast" href="#" uk-slider-item="next"></a>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        return ob_get_clean();
    }
}
