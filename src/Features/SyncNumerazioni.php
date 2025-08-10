<?php

namespace AvsOperatorStatus\Features;

/**
 * Class SyncNumerazioni
 *
 * Handles the automatic synchronization of "Numerazione" terms to an "Operatrice" post.
 */
class SyncNumerazioni {
    /**
     * Registers the sync functionality.
     * The action is commented out by default, as it was in the original code.
     */
    public function register() {
        // To enable, uncomment the line below.
        // add_action( 'save_post_operatrice', [ $this, 'sync_on_save' ] );
    }

    /**
     * Synchronizes the numerazioni for a given post.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function sync_on_save( int $post_id ) {
        if ( get_post_type( $post_id ) !== 'operatrice' ) {
            return;
        }

        $generi_operatrice = get_the_terms( $post_id, 'genere' );

        if ( empty( $generi_operatrice ) || is_wp_error( $generi_operatrice ) ) {
            wp_set_post_terms( $post_id, null, 'numerazione' );
            return;
        }

        $genere_nome = $generi_operatrice[0]->name;

        $tutte_le_numerazioni = get_terms( [
            'taxonomy'   => 'numerazione',
            'hide_empty' => false,
        ] );

        $ids_numerazioni_da_assegnare = [];
        foreach ( $tutte_le_numerazioni as $numerazione ) {
            if ( stripos( $numerazione->description, $genere_nome ) !== false ) {
                $ids_numerazioni_da_assegnare[] = $numerazione->term_id;
            }
        }

        wp_set_post_terms( $post_id, $ids_numerazioni_da_assegnare, 'numerazione', false );
    }
}
