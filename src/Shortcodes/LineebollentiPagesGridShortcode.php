<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class LineebollentiPagesGridShortcode
 *
 * Handles the [lineebollenti_pages_grid] shortcode.
 */
class LineebollentiPagesGridShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'lineebollenti_pages_grid', [ $this, 'render' ] );
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

        $all_genres = get_terms( [
            'taxonomy'   => 'genere',
            'hide_empty' => true,
        ] );

        $genre_names_to_ids = [];
        $excluded_genre_ids = [];

        if ( ! is_wp_error( $all_genres ) && ! empty( $all_genres ) ) {
            foreach ( $all_genres as $genere_term ) {
                if ( stripos( $genere_term->name, 'Basso Costo' ) !== false ) {
                    $excluded_genre_ids[] = $genere_term->term_id;
                    continue;
                }
                $genre_names_to_ids[ strtolower( $genere_term->name ) ] = $genere_term->term_id;
            }
        }

        krsort( $genre_names_to_ids );

        $etero_genre_term = get_term_by( 'name', 'Etero', 'genere' );
        $etero_genre_id = $etero_genre_term ? $etero_genre_term->term_id : 0;

        $pages_to_exclude = [ 2, 298, 1323, 406, 965, 235 ];

        $all_pages = get_pages( [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'sort_column'    => 'menu_order',
            'exclude'        => $pages_to_exclude,
        ] );

        if ( empty( $all_pages ) ) {
            echo '<p>Nessuna pagina disponibile per la visualizzazione.</p>';
            return ob_get_clean();
        }
        ?>
        <div class="uk-section uk-section-small">
            <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">I Nostri Numeri Erotici</h2>
            <div class="uk-grid-match uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
                <?php
                foreach ( $all_pages as $page ) {
                    $page_link = get_permalink( $page->ID );
                    $page_title = $page->post_title;

                    $cleaned_content = strip_shortcodes( $page->post_content );
                    $page_excerpt = wp_trim_words( $cleaned_content, 20, '...' );

                    $page_image_url = '';
                    $matched_genre_id = 0;

                    foreach ( $genre_names_to_ids as $genre_name_lower => $genre_id ) {
                        if ( preg_match( '/\b' . preg_quote( $genre_name_lower, '/' ) . '\b/i', strtolower( $page_title ) ) ) {
                            $matched_genre_id = $genre_id;
                            break;
                        }
                    }

                    if ( $matched_genre_id === 0 && $etero_genre_id > 0 ) {
                        $matched_genre_id = $etero_genre_id;
                    }

                    if ( $matched_genre_id > 0 ) {
                        $operatrici_query_args = [
                            'post_type'      => 'operatrice',
                            'post_status'    => 'publish',
                            'posts_per_page' => 1,
                            'orderby'        => 'rand',
                            'tax_query'      => [ [
                                'taxonomy' => 'genere',
                                'field'    => 'term_id',
                                'terms'    => $matched_genre_id,
                                'operator' => 'IN',
                            ] ],
                            'meta_query'     => [ [
                                'key'     => '_thumbnail_id',
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
                                    $page_image_url = $image_src[0];
                                } else {
                                    $page_image_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
                                }
                            }
                            wp_reset_postdata();
                        }
                    }
                    ?>
                    <div>
                        <a href="<?php echo esc_url( $page_link ); ?>" class="uk-link-reset">
                            <div class="uk-card uk-card-default uk-card-hover uk-card-body uk-text-center uk-border-rounded">
                                <?php if ( ! empty( $page_image_url ) ) : ?>
                                    <div class="uk-card-media-top uk-margin-bottom">
                                        <img src="<?php echo esc_url( $page_image_url ); ?>" alt="<?php echo esc_attr( $page_title ); ?>" class="uk-border-circle" width="120" height="120" style="object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <h3 class="uk-card-title uk-margin-small-top"><?php echo esc_html( $page_title ); ?></h3>
                                <?php if ( ! empty( $page_excerpt ) ) : ?>
                                    <p class="uk-text-small uk-text-muted"><?php echo wp_kses_post( $page_excerpt ); ?></p>
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
