<?php

namespace AvsOperatorStatus\Setup;

/**
 * Class PostTypes
 *
 * Handles the registration of Custom Post Types.
 */
class PostTypes {
    /**
     * Registers all post types for the plugin.
     */
    public function register() {
        add_action( 'init', [ $this, 'register_operatrice_cpt' ], 0 );
    }

    /**
     * Registers the "Operatrice" Custom Post Type.
     */
    public function register_operatrice_cpt() {
        $labels = [
            'name'                  => _x( 'Operatrici', 'Post Type General Name', 'avs-operator-status' ),
            'singular_name'         => _x( 'Operatrice', 'Post Type Singular Name', 'avs-operator-status' ),
            'menu_name'             => __( 'Operatrici', 'avs-operator-status' ),
            'name_admin_bar'        => __( 'Operatrice', 'avs-operator-status' ),
            'archives'              => __( 'Archivio Operatrici', 'avs-operator-status' ),
            'attributes'            => __( 'Attributi Operatrice', 'avs-operator-status' ),
            'parent_item_colon'     => __( 'Operatrice Padre:', 'avs-operator-status' ),
            'all_items'             => __( 'Tutte le Operatrici', 'avs-operator-status' ),
            'add_new_item'          => __( 'Aggiungi Nuova Operatrice', 'avs-operator-status' ),
            'add_new'               => __( 'Aggiungi Nuova', 'avs-operator-status' ),
            'new_item'              => __( 'Nuova Operatrice', 'avs-operator-status' ),
            'edit_item'             => __( 'Modifica Operatrice', 'avs-operator-status' ),
            'update_item'           => __( 'Aggiorna Operatrice', 'avs-operator-status' ),
            'view_item'             => __( 'Visualizza Operatrice', 'avs-operator-status' ),
            'view_items'            => __( 'Visualizza Operatrici', 'avs-operator-status' ),
            'search_items'          => __( 'Cerca Operatrice', 'avs-operator-status' ),
        ];
        $args = [
            'label'                 => __( 'Operatrice', 'avs-operator-status' ),
            'description'           => __( 'Profili delle operatrici del servizio', 'avs-operator-status' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-groups',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        ];
        register_post_type( 'operatrice', $args );
    }
}
