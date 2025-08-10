<?php

namespace AvsOperatorStatus\Shortcodes;

/**
 * Class BottoniSingolaShortcode
 *
 * Handles the [aos_bottoni_operatrice] shortcode.
 */
class BottoniSingolaShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_bottoni_operatrice', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        \AvsOperatorStatus\Plugin::instance()->asset_manager->request( [ 'aos-style', 'aos-frontend-main' ] );

        $atts = shortcode_atts( [ 'id' => '' ], $atts, 'aos_bottoni_operatrice' );

        $operatrice_id = $atts['id'];
        if ( empty( $operatrice_id ) ) {
            $operatrice_id = get_the_ID();
        }

        if ( empty( $operatrice_id ) || get_post_type( $operatrice_id ) !== 'operatrice' ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<p style="color:red;">[aos_bottoni_operatrice] non ha trovato un\'operatrice valida in questo contesto.</p>';
            }
            return '';
        }

        $generi = get_the_terms( $operatrice_id, 'genere' );
        if ( ! $generi || is_wp_error( $generi ) ) {
            return '';
        }

        $codice_da_tracciare = $operatrice_id;
        $genere_nome = $generi[0]->name;
        $genere_slug = $generi[0]->slug;

        $tutte_le_numerazioni = get_terms( [ 'taxonomy' => 'numerazione', 'hide_empty' => false ] );
        $mappa_genere_ddi = \AvsOperatorStatus\Utils\Helpers::aos_get_ddi_map();

        $numerazioni_filtrate = [];
        if ( ! empty( $genere_nome ) && ! is_wp_error( $tutte_le_numerazioni ) ) {
            foreach ( $tutte_le_numerazioni as $numerazione ) {
                if ( stripos( $numerazione->description, $genere_nome ) !== false && stripos( $numerazione->description, 'Svizzera' ) === false ) {
                    $numerazioni_filtrate[] = $numerazione;
                }
            }
        }

        ob_start();
        ?>
        <div class="cartomante" data-codice="<?php echo esc_attr( $codice_da_tracciare ); ?>">
            <?php
            if ( ! empty( $numerazioni_filtrate ) ) {
                usort( $numerazioni_filtrate, function( $a, $b ) {
                    return \AvsOperatorStatus\Utils\Helpers::aos_get_numerazione_priority( $a->description ) <=> \AvsOperatorStatus\Utils\Helpers::aos_get_numerazione_priority( $b->description );
                } );

                $ha_chiama_e_ricarica = false;
                foreach ( $numerazioni_filtrate as $numerazione_check ) {
                    if ( stripos( $numerazione_check->description, 'Carta di Credito' ) !== false ) {
                        $ha_chiama_e_ricarica = true;
                        break;
                    }
                }

                echo '<div class="aos-single-operator-buttons uk-margin-medium-top">';

                $operator_name = get_the_title( $operatrice_id );
                if ( function_exists( 'lineebollenti_display_operator_audio' ) ) {
                    lineebollenti_display_operator_audio( $operator_name );
                }

                foreach ( $numerazioni_filtrate as $numerazione ) {
                    echo \AvsOperatorStatus\Utils\Helpers::aos_render_payment_button(
                        $numerazione,
                        $genere_slug,
                        $mappa_genere_ddi,
                        $codice_da_tracciare,
                        $ha_chiama_e_ricarica
                    );
                }

                echo '</div>';

            } else {
                echo '<p class="uk-text-center uk-margin-medium-top"><em>Nessuna opzione di chiamata disponibile per questa operatrice.</em></p>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
