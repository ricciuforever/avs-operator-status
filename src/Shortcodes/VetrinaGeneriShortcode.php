<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class VetrinaGeneriShortcode
 *
 * Handles the [aos_vetrina_generi] shortcode.
 */
class VetrinaGeneriShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_vetrina_generi', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        \AvsOperatorStatus\Plugin::instance()->asset_manager->request( [ 'aos-style', 'aos-frontend-main' ] );

        global $wpdb;

        // 1. Determine genre order based on popularity
        $all_genres = get_terms( [ 'taxonomy' => 'genere', 'hide_empty' => true ] );
        $excluded_genre_ids = [];
        foreach ( $all_genres as $genre ) {
            if ( stripos( $genre->name, 'Basso Costo' ) !== false ) {
                $excluded_genre_ids[] = $genre->term_id;
            }
        }

        $exclusion_placeholder = ! empty( $excluded_genre_ids ) ? 'AND t.term_id NOT IN (' . implode( ',', array_fill( 0, count( $excluded_genre_ids ), '%d' ) ) . ')' : '';
        $table_name_tracking = $wpdb->prefix . 'aos_click_tracking';
        $thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $params = [ $thirty_days_ago ];
        if ( ! empty( $excluded_genre_ids ) ) {
            $params = array_merge( $params, $excluded_genre_ids );
        }

        $top_genre_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT t.term_id FROM {$table_name_tracking} AS c
             INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'genere' AND c.click_timestamp >= %s {$exclusion_placeholder}
             GROUP BY t.term_id ORDER BY COUNT(c.id) DESC LIMIT 3",
            $params
        ) );

        $top_genre_slugs = [];
        $other_genre_slugs = [];
        foreach ( $all_genres as $genre ) {
            if ( in_array( $genre->term_id, $excluded_genre_ids ) ) {
                continue;
            }
            if ( in_array( $genre->term_id, $top_genre_ids ) ) {
                $top_genre_slugs[] = $genre->slug;
            } else {
                $other_genre_slugs[] = $genre->slug;
            }
        }

        shuffle( $top_genre_slugs );
        shuffle( $other_genre_slugs );
        $genres_to_show = array_merge( $top_genre_slugs, $other_genre_slugs );

        // 2. Select a representative operator for each genre
        $operator_ids = [];
        foreach ( $genres_to_show as $genre_slug ) {
            $genre_term = get_term_by( 'slug', $genre_slug, 'genere' );
            if ( $genre_term ) {
                $operator_post = \AvsOperatorStatus\Utils\Helpers::aos_get_weighted_random_operator_post( $genre_term->term_id );
                if ( $operator_post ) {
                    $operator_ids[] = $operator_post->ID;
                }
            }
        }

        if ( empty( $operator_ids ) ) {
            return '<p>Nessuna operatrice disponibile.</p>';
        }

        // 3. Prepare arguments for the rendering function
        $query_args = [
            'post_type'      => 'operatrice',
            'post__in'       => $operator_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => -1,
        ];
        $query = new WP_Query( $query_args );

        $grid_classes = 'aos-operators-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-match';

        // 4. Render final output
        $output = '<p class="uk-text-center uk-margin-medium-bottom">Componi il numero e chiedi di parlare con operatrice scelta</p>';

        $output .= \AvsOperatorStatus\Utils\Helpers::avs_render_operator_grid_with_quiz_injection( $query, $grid_classes );

        wp_reset_postdata();

        return $output;
    }
}
