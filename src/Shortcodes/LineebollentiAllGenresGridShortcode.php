<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class LineebollentiAllGenresGridShortcode
 *
 * Handles the [lineebollenti_all_genres_grid] shortcode.
 */
class LineebollentiAllGenresGridShortcode {

    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'lineebollenti_all_genres_grid', [ $this, 'render' ] );
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

        $excluded_genre_ids = [];
        $basso_costo_terms = get_terms( [
            'taxonomy'   => 'genere',
            'name__like' => 'Basso Costo',
            'fields'     => 'ids',
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $basso_costo_terms ) && ! empty( $basso_costo_terms ) ) {
            $excluded_genre_ids = $basso_costo_terms;
        }

        $all_generi = get_terms( [
            'taxonomy'   => 'genere',
            'hide_empty' => true,
            'exclude'    => $excluded_genre_ids,
        ] );

        if ( empty( $all_generi ) || is_wp_error( $all_generi ) ) {
            echo '<p>Nessun genere di operatrici disponibile per la visualizzazione.</p>';
            return ob_get_clean();
        }
        ?>
        <div class="uk-section uk-section-small">
            <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">Esplora i Generi</h2>
            <div class="uk-grid-match uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
                <?php
                foreach ( $all_generi as $genere ) {
                    $genere_link = get_term_link( $genere );
                    $description_excerpt = wp_trim_words( $genere->description, 20, '...' );

                    $random_operator_image_url = '';
                    $operatrici_query_args = [
                        'post_type' => 'operatrice',
                        'post_status' => 'publish',
                        'posts_per_page' => 1,
                        'orderby' => 'rand',
                        'tax_query' => [ [
                            'taxonomy' => 'genere',
                            'field' => 'term_id',
                            'terms' => $genere->term_id,
                        ] ],
                        'meta_query' => [ [
                            'key' => '_thumbnail_id',
                            'compare' => 'EXISTS',
                        ] ],
                    ];
                    $operatrici_random_query = new WP_Query( $operatrici_query_args );

                    if ( $operatrici_random_query->have_posts() ) {
                        $operatrici_random_query->the_post();
                        if ( has_post_thumbnail() ) {
                            $image_id = get_post_thumbnail_id();
                            $image_src = wp_get_attachment_image_src( $image_id, 'medium' );
                            if ( $image_src ) {
                                $random_operator_image_url = $image_src[0];
                            } else {
                                $random_operator_image_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
                            }
                        }
                        wp_reset_postdata();
                    }
                    ?>
                    <div>
                        <a href="<?php echo esc_url( $genere_link ); ?>" class="uk-link-reset">
                            <div class="uk-card uk-card-default uk-card-hover uk-card-body uk-text-center uk-border-rounded">
                                <?php if ( ! empty( $random_operator_image_url ) ) : ?>
                                    <div class="uk-card-media-top uk-margin-bottom">
                                        <img src="<?php echo esc_url( $random_operator_image_url ); ?>" alt="<?php echo esc_attr( $genere->name ); ?>" class="uk-border-circle" width="120" height="120" style="object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <h3 class="uk-card-title uk-margin-small-top"><?php echo esc_html( $genere->name ); ?></h3>
                                <?php if ( ! empty( $description_excerpt ) ) : ?>
                                    <p class="uk-text-small uk-text-muted"><?php echo esc_html( $description_excerpt ); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
