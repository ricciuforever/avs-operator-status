<?php

namespace AvsOperatorStatus\Admin;

/**
 * Class OperatriceAdminColumns
 *
 * Handles the customization of the admin list table for the "Operatrice" CPT.
 */
class OperatriceAdminColumns {
    /**
     * Registers all hooks for the admin columns.
     */
    public function register() {
        add_filter( 'manage_operatrice_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_operatrice_posts_custom_column', [ $this, 'display_column_content' ], 10, 2 );
        add_filter( 'manage_edit-operatrice_sortable_columns', [ $this, 'make_columns_sortable' ] );
        add_action( 'pre_get_posts', [ $this, 'order_by_custom_columns' ] );
        add_filter( 'posts_orderby', [ $this, 'force_numeric_orderby' ], 10, 2 );
        add_action( 'admin_head', [ $this, 'add_admin_styles' ] );
    }

    /**
     * Adds custom columns to the operatrice list table.
     */
    public function add_columns( $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            if ( $key === 'title' ) {
                $new_columns['featured_image'] = __( 'Immagine', 'avs-operator-status' );
                $new_columns['views'] = __( 'Views', 'avs-operator-status' );
                $new_columns['clicks'] = __( 'Clicks', 'avs-operator-status' );
                $new_columns['favorites'] = __( '❤️ Cuori', 'avs-operator-status' );
            }
        }
        return $new_columns;
    }

    /**
     * Displays the content for the custom columns.
     */
    public function display_column_content( $column_name, $post_id ) {
        switch ( $column_name ) {
            case 'featured_image':
                if ( has_post_thumbnail( $post_id ) ) {
                    echo get_the_post_thumbnail( $post_id, [ 60, 60 ] );
                } else {
                    echo '—';
                }
                break;
            case 'views':
                echo (int) get_post_meta( $post_id, 'aos_views', true );
                break;
            case 'clicks':
                echo (int) get_post_meta( $post_id, 'aos_clicks', true );
                break;
            case 'favorites':
                echo (int) get_post_meta( $post_id, '_aos_favorites_count', true );
                break;
        }
    }

    /**
     * Makes the custom columns sortable.
     */
    public function make_columns_sortable( $columns ): array {
        $columns['views'] = 'aos_views';
        $columns['clicks'] = 'aos_clicks';
        $columns['favorites'] = '_aos_favorites_count';
        return $columns;
    }

    /**
     * Handles the query for ordering by custom columns.
     */
    public function order_by_custom_columns( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'operatrice' ) {
            return;
        }

        $orderby = $query->get( 'orderby' );
        if ( in_array( $orderby, [ 'aos_views', 'aos_clicks', '_aos_favorites_count' ] ) ) {
            $query->set( 'meta_key', $orderby );
            $query->set( 'orderby', 'meta_value_num' );
        }
    }

    /**
     * Forces a numeric cast on the ORDER BY clause for custom columns.
     */
    public function force_numeric_orderby( $orderby, $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return $orderby;
        }

        $orderby_key = $query->get( 'orderby' );
        $meta_key = $query->get( 'meta_key' );

        if ( 'meta_value_num' === $orderby_key && in_array( $meta_key, [ 'aos_views', 'aos_clicks', '_aos_favorites_count' ] ) ) {
            global $wpdb;
            $order = $query->get( 'order' );
            $orderby = "CAST({$wpdb->postmeta}.meta_value AS SIGNED) " . $order;
        }

        return $orderby;
    }

    /**
     * Adds custom CSS to the admin head for styling the columns.
     */
    public function add_admin_styles() {
        $screen = get_current_screen();
        if ( $screen && 'edit-operatrice' === $screen->id ) {
            echo '<style type="text/css">
                .column-featured_image { width: 100px; text-align: center; }
                .column-featured_image img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
            </style>';
        }
    }
}
