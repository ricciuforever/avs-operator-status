<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;
use WP_Term;

/**
 * Class VetrinaGenereCorrenteShortcode
 *
 * Handles the [aos_vetrina_genere_corrente] shortcode.
 */
class VetrinaGenereCorrenteShortcode {

    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_vetrina_genere_corrente', [ $this, 'render' ] );
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

        $current_term = get_queried_object();
        if ( ! $current_term instanceof WP_Term || $current_term->taxonomy !== 'genere' ) {
            return '';
        }
        $genere_term_id = $current_term->term_id;

        $table_name_tracking = $wpdb->prefix . 'aos_click_tracking';
        $thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $term_taxonomy_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'genere'", $genere_term_id ) );
        $top_operatrici_ids = [];
        if ( $term_taxonomy_id ) {
            $top_operatrici_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT tr.object_id FROM {$wpdb->term_relationships} AS tr
                 INNER JOIN {$table_name_tracking} AS c ON tr.object_id = c.post_id
                 WHERE tr.term_taxonomy_id = %d AND c.click_timestamp >= %s
                 GROUP BY tr.object_id ORDER BY COUNT(c.id) DESC LIMIT 3",
                $term_taxonomy_id,
                $thirty_days_ago
            ) );
        }

        $all_operator_ids_query = new WP_Query( [
            'post_type'      => 'operatrice',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => [ [ 'taxonomy' => 'genere', 'field' => 'term_id', 'terms' => $genere_term_id ] ],
            'fields'         => 'ids'
        ] );
        $all_operator_ids = $all_operator_ids_query->posts;

        if ( empty( $all_operator_ids ) ) {
            return '<p class="uk-text-center">Al momento non ci sono operatrici disponibili per la categoria ' . esc_html( $current_term->name ) . '.</p>';
        }

        $operator_pool = [];
        $peso_top_op = 5;
        $peso_normale_op = 1;

        foreach ( $all_operator_ids as $op_id ) {
            $peso = in_array( $op_id, $top_operatrici_ids ) ? $peso_top_op : $peso_normale_op;
            for ( $i = 0; $i < $peso; $i++ ) {
                $operator_pool[] = $op_id;
            }
        }

        shuffle( $operator_pool );
        $final_ids_to_show = [];

        foreach ( $operator_pool as $op_id ) {
            if ( ! in_array( $op_id, $final_ids_to_show ) ) {
                $final_ids_to_show[] = $op_id;
            }
            if ( count( $final_ids_to_show ) >= 25 ) {
                break;
            }
        }

        if ( empty( $final_ids_to_show ) ) {
            return '<p class="uk-text-center">Nessuna operatrice trovata per questo genere.</p>';
        }

        $query_args = [
            'post_type'      => 'operatrice',
            'post_status'    => 'publish',
            'post__in'       => $final_ids_to_show,
            'orderby'        => 'post__in',
            'posts_per_page' => 25,
        ];
        $query = new WP_Query( $query_args );

        $grid_classes = 'aos-operators-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-match';

        $output = \AvsOperatorStatus\Utils\Helpers::avs_render_operator_grid_with_quiz_injection( $query, $grid_classes );

        wp_reset_postdata();
        return $output;
    }
}
