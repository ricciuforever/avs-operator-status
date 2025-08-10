<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class BassoCostoVetrinaShortcode
 *
 * Handles the [aos_basso_costo_vetrina] shortcode.
 */
class BassoCostoVetrinaShortcode {

    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'aos_basso_costo_vetrina', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        \AvsOperatorStatus\Plugin::instance()->asset_manager->request( [ 'aos-style', 'aos-frontend-main' ] );

        $atts = shortcode_atts( [ 'genere' => '' ], $atts, 'aos_basso_costo_vetrina' );
        $genere_prioritario_slug = sanitize_text_field( $atts['genere'] );

        if ( empty( $genere_prioritario_slug ) ) {
            return current_user_can( 'manage_options' ) ? '<p style="color:red;">Shortcode [aos_basso_costo_vetrina]: specificare un "genere".</p>' : '';
        }

        $altri_generi_slugs = [];
        $tutti_i_generi = get_terms( [ 'taxonomy' => 'genere', 'hide_empty' => true ] );

        foreach ( $tutti_i_generi as $genere ) {
            if ( $genere->slug === $genere_prioritario_slug ) continue;
            if ( stripos( $genere->name, 'Basso Costo' ) !== false ) continue;
            $altri_generi_slugs[] = $genere->slug;
        }
        shuffle( $altri_generi_slugs );
        $generi_da_mostrare = array_merge( [ $genere_prioritario_slug ], $altri_generi_slugs );

        $tutte_le_numerazioni = get_terms( [ 'taxonomy' => 'numerazione', 'hide_empty' => false, 'orderby' => 'name' ] );
        if ( is_wp_error( $tutte_le_numerazioni ) || empty( $tutte_le_numerazioni ) ) {
            return '<p>Nessuna numerazione configurata.</p>';
        }

        $mappa_genere_ddi = \AvsOperatorStatus\Utils\Helpers::aos_get_ddi_map();

        $operatrici_per_vetrina = [];
        foreach ( $generi_da_mostrare as $genere_slug ) {
            $args = [
                'post_type' => 'operatrice',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'rand',
                'tax_query' => [ [ 'taxonomy' => 'genere', 'field' => 'slug', 'terms' => $genere_slug ] ]
            ];
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) {
                $genere_term = get_term_by( 'slug', $genere_slug, 'genere' );
                $operatrici_per_vetrina[] = [
                    'post' => $query->posts[0],
                    'genere_nome' => $genere_term ? $genere_term->name : '',
                    'genere_slug' => $genere_term ? $genere_term->slug : ''
                ];
            }
            wp_reset_postdata();
        }

        if ( empty( $operatrici_per_vetrina ) ) {
            return '<p>Nessuna operatrice disponibile per i generi specificati.</p>';
        }

        ob_start();
        ?>
        <p class="uk-text-center uk-margin-medium-bottom">Componi il numero e chiedi di parlare con l'operatrice scelta</p>
        <div class="aos-operators-grid uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-match" uk-grid>
            <?php foreach ( $operatrici_per_vetrina as $item ) :
                $operatrice = $item['post'];
                $genere_nome = $item['genere_nome'];
                $genere_slug = $item['genere_slug'];
                $codice_da_tracciare = $operatrice->ID;
                $operatrice_content = apply_filters( 'the_content', $operatrice->post_content );
                ?>
                <div>
                    <div class="uk-card uk-padding-small uk-card-secondary uk-card-body uk-flex uk-flex-column uk-text-center operatrice" data-codice="<?php echo esc_attr( $codice_da_tracciare ); ?>">
                        <?php if ( has_post_thumbnail( $operatrice->ID ) ) : ?>
                            <img class="uk-border-circle" src="<?php echo get_the_post_thumbnail_url( $operatrice->ID, 'medium_large' ); ?>" alt="<?php echo esc_attr( $operatrice->post_title ); ?>">
                        <?php endif; ?>
                        <div class="labelnome"><?php echo esc_html( $operatrice->post_title ); ?></div>
                        <div class="uk-h3 uk-margin-remove-top uk-text-primary uk-margin-small-bottom">Genere: <?php echo esc_html( $genere_nome ); ?></div>
                        <div class="uk-text-small cartintro"><?php echo wp_kses_post( wp_trim_words( $operatrice_content, 20, '...' ) ); ?></div>
                        <div class="uk-margin-top">
                            <?php
                            $numerazioni_filtrate = [];
                            $is_basso_costo_card = stripos( $genere_nome, 'Basso Costo' ) !== false;

                            foreach ( $tutte_le_numerazioni as $numerazione ) {
                                $desc = $numerazione->description;
                                $mostra_pulsante = false;

                                if ( $is_basso_costo_card ) {
                                    $base_genere_nome = trim( str_ireplace( 'Basso Costo', '', $genere_nome ) );
                                    if (
                                        stripos( $desc, $genere_nome ) !== false ||
                                        ( stripos( $desc, 'Carta di Credito' ) !== false && stripos( $desc, $base_genere_nome ) !== false ) ||
                                        ( stripos( $desc, 'Ricarica Online' ) !== false && stripos( $desc, $base_genere_nome ) !== false )
                                    ) {
                                        $mostra_pulsante = true;
                                    }
                                } else {
                                    if ( stripos( $desc, $genere_nome ) !== false && stripos( $desc, 'Svizzera' ) === false && stripos( $desc, 'Basso Costo' ) === false ) {
                                        $mostra_pulsante = true;
                                    }
                                }

                                if ( $mostra_pulsante ) {
                                    $numerazioni_filtrate[] = $numerazione;
                                }
                            }

                            if ( ! empty( $numerazioni_filtrate ) ) {
                                usort( $numerazioni_filtrate, function ( $a, $b ) {
                                    return \AvsOperatorStatus\Utils\Helpers::aos_get_numerazione_priority( $a->description ) <=> \AvsOperatorStatus\Utils\Helpers::aos_get_numerazione_priority( $b->description );
                                } );

                                foreach ( $numerazioni_filtrate as $numerazione ) {
                                    echo \AvsOperatorStatus\Utils\Helpers::aos_render_payment_button(
                                        $numerazione,
                                        $genere_slug,
                                        $mappa_genere_ddi,
                                        $codice_da_tracciare,
                                        $ha_chiama_e_ricarica
                                    );
                                }
                            } else {
                                echo '<p class="uk-text-small uk-text-muted"><em>Nessuna tariffa specifica per questo genere.</em></p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
