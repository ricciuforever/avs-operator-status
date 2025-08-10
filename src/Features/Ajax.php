<?php

namespace AvsOperatorStatus\Features;

use AvsOperatorStatus\Utils\Helpers;
use WP_Query;

/**
 * Class Ajax
 *
 * Handles all public-facing AJAX endpoints for the plugin.
 */
class Ajax {
    /**
     * Registers all AJAX hooks.
     */
    public function register() {
        add_action( 'wp_ajax_nopriv_aos_track_views', [ $this, 'track_views' ] );
        add_action( 'wp_ajax_nopriv_aos_track_click', [ $this, 'track_click' ] );
        add_action( 'wp_ajax_nopriv_aos_log_global_click', [ $this, 'log_global_click' ] );
        add_action( 'wp_ajax_nopriv_aos_get_favorite_cards_html', [ $this, 'get_favorite_cards' ] );
        add_action( 'wp_ajax_aos_get_favorite_cards_html', [ $this, 'get_favorite_cards' ] );
        add_action( 'wp_ajax_nopriv_aos_update_favorite_count', [ $this, 'update_favorite_count' ] );
        add_action( 'wp_ajax_aos_update_favorite_count', [ $this, 'update_favorite_count' ] );
    }

    /**
     * AJAX handler to track post views.
     */
    public function track_views() {
        if ( $this->is_bot() ) {
            wp_send_json_success( [ 'message' => 'Bot ignored.' ] );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( empty( $_POST['codes'] ) || ! is_array( $_POST['codes'] ) ) {
            return;
        }
        $post_ids = array_map( 'intval', $_POST['codes'] );
        foreach ( $post_ids as $post_id ) {
            if ( $post_id > 0 ) {
                $views = (int) get_post_meta( $post_id, 'aos_views', true );
                update_post_meta( $post_id, 'aos_views', $views + 1 );
            }
        }
        wp_send_json_success();
    }

    /**
     * AJAX handler to track clicks on an operator.
     */
    public function track_click() {
        if ( $this->is_bot() ) {
            wp_send_json_success( [ 'message' => 'Bot ignored.' ] );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( empty( $_POST['code'] ) ) {
            return;
        }
        $post_id = intval( $_POST['code'] );
        if ( $post_id > 0 ) {
            $clicks = (int) get_post_meta( $post_id, 'aos_clicks', true );
            update_post_meta( $post_id, 'aos_clicks', $clicks + 1 );
        }
        wp_send_json_success();
    }

    /**
     * AJAX handler to log a detailed click event in the custom table.
     */
    public function log_global_click() {
        if ( $this->is_bot() ) {
            wp_send_json_success( [ 'message' => 'Bot ignored.' ] );
        }
        if ( ! wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
            wp_send_json_error( 'Nonce non valido' );
        }
        if ( empty( $_POST['numerazione_id'] ) ) {
            wp_send_json_error( 'Dati mancanti' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        $numerazione_id = intval( $_POST['numerazione_id'] );
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $context_url = isset( $_POST['context_url'] ) ? esc_url_raw( $_POST['context_url'] ) : '';

        $wpdb->insert( $table_name,
            [ 'post_id' => $post_id, 'numerazione_term_id' => $numerazione_id, 'click_context' => $context_url, 'click_timestamp' => current_time( 'mysql' ) ],
            [ '%d', '%d', '%s', '%s' ]
        );
        wp_send_json_success();
    }

    /**
     * AJAX handler to get HTML for favorite operator cards.
     */
    public function get_favorite_cards() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( empty( $_POST['operator_ids'] ) || ! is_array( $_POST['operator_ids'] ) ) {
            wp_send_json_error( 'Dati mancanti o non validi.' );
        }

        $operator_ids = array_map( 'intval', $_POST['operator_ids'] );
        $query = new WP_Query( [
            'post_type' => 'operatrice',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $operator_ids,
            'orderby' => 'post__in',
        ] );

        if ( ! $query->have_posts() ) {
            wp_send_json_success( '<p>Nessuna operatrice trovata tra i preferiti.</p>' );
        }

        $html_output = '';
        while ( $query->have_posts() ) {
            $query->the_post();
            $html_output .= '<div>' . Helpers::aos_render_operator_card_html( get_post() ) . '</div>';
        }
        wp_reset_postdata();
        wp_send_json_success( $html_output );
    }

    /**
     * AJAX handler to update the favorite count for an operator.
     */
    public function update_favorite_count() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aos_tracking_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        if ( empty( $_POST['operator_id'] ) || empty( $_POST['action_type'] ) ) {
            wp_send_json_error( 'Dati mancanti.' );
        }

        $operator_id = intval( $_POST['operator_id'] );
        $action_type = sanitize_text_field( $_POST['action_type'] );

        if ( $operator_id > 0 && in_array( $action_type, [ 'add', 'remove' ] ) ) {
            $current_count = (int) get_post_meta( $operator_id, '_aos_favorites_count', true );
            $new_count = ( $action_type === 'add' ) ? $current_count + 1 : max( 0, $current_count - 1 );
            update_post_meta( $operator_id, '_aos_favorites_count', $new_count );
            wp_send_json_success( [ 'new_count' => $new_count ] );
        } else {
            wp_send_json_error( 'Dati non validi.' );
        }
    }

    /**
     * Checks if the current request is from a known bot.
     * @return bool True if it is a bot, false otherwise.
     */
    private function is_bot(): bool {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ( empty( $user_agent ) ) {
            return false;
        }
        $bot_keywords = [ 'bot', 'spider', 'crawler', 'slurp', 'mediapartners', 'AhrefsBot', 'SemrushBot', 'MegaIndex', 'BLEXBot' ];
        foreach ( $bot_keywords as $keyword ) {
            if ( stripos( $user_agent, $keyword ) !== false ) {
                return true;
            }
        }
        return false;
    }
}
