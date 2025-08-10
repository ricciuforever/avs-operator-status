<?php

namespace AvsOperatorStatus\Core;

/**
 * Class AssetManager
 *
 * Handles registration and conditional loading of CSS and JS assets.
 */
class AssetManager {
    private array $enqueued_scripts = [];
    private array $enqueued_styles = [];

    /**
     * Registers all hooks for the asset manager.
     */
    public function register() {
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 5 );
        add_action( 'wp_footer', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Registers all plugin assets with WordPress.
     * They are registered but not enqueued here.
     */
    public function register_assets() {
        // Register main stylesheet
        wp_register_style( 'aos-style', AOS_PLUGIN_URL . 'css/style.css', [], AOS_VERSION );

        // Register main frontend script
        wp_register_script( 'aos-frontend-main', AOS_PLUGIN_URL . 'js/aos-frontend-main.js', [ 'jquery' ], AOS_VERSION, true );

        // Register other scripts if necessary
        // wp_register_script( 'quiz-script', AOS_PLUGIN_URL . 'js/quiz-script.js', ['jquery'], AOS_VERSION, true );
    }

    /**
     * Marks a script or style to be enqueued later.
     *
     * @param string|array $handles The handle(s) of the asset(s) to enqueue.
     */
    public function request( $handles ) {
        $handles = (array) $handles;
        foreach ( $handles as $handle ) {
            if ( wp_style_is( $handle, 'registered' ) ) {
                $this->enqueued_styles[ $handle ] = $handle;
            }
            if ( wp_script_is( $handle, 'registered' ) ) {
                $this->enqueued_scripts[ $handle ] = $handle;
            }
        }
    }

    /**
     * Enqueues the requested scripts and styles in the footer.
     * This is called late in the page load to ensure all shortcodes have made their requests.
     */
    public function enqueue_assets() {
        foreach ( $this->enqueued_styles as $handle ) {
            wp_enqueue_style( $handle );
        }

        // If the main script is requested, also output its localized data.
        if ( isset( $this->enqueued_scripts['aos-frontend-main'] ) ) {
            $data = $this->get_localized_data();
            wp_localize_script( 'aos-frontend-main', 'aos_frontend_params', $data );
        }

        foreach ( $this->enqueued_scripts as $handle ) {
            wp_enqueue_script( $handle );
        }
    }

    /**
     * Gets the expensive data that was previously localized on every page load.
     * This should be called only when needed and the data passed directly to the script.
     *
     * @return array The localized data array.
     */
    public function get_localized_data(): array {
        $params = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'aos_tracking_nonce' ),
            'promo_list' => [],
            'mappa_numeri' => [],
            'mappa_tariffe' => [],
        ];

        $all_terms = get_terms( [ 'taxonomy' => 'numerazione', 'hide_empty' => false ] );
        if ( ! is_wp_error( $all_terms ) && ! empty( $all_terms ) ) {
            foreach ( $all_terms as $term ) {
                $numero_pulito = preg_replace( '/[^0-9]/', '', $term->name );
                if ( empty( $numero_pulito ) ) continue;

                $params['mappa_numeri'][ $numero_pulito ] = $term->term_id;

                if ( get_term_meta( $term->term_id, '_aos_promo_attiva', true ) === 'si' ) {
                    $messaggio = get_term_meta( $term->term_id, '_aos_promo_messaggio', true );
                    if ( ! empty( $messaggio ) ) {
                        $params['promo_list'][ $numero_pulito ] = [ 'messaggio' => $messaggio ];
                    }
                }

                $tariffe = get_term_meta( $term->term_id, '_aos_tariffe_meta', true );
                if ( ! empty( $tariffe ) && is_array( $tariffe ) ) {
                    $params['mappa_tariffe'][ $numero_pulito ] = $tariffe;
                }
            }
        }
        return $params;
    }
}
