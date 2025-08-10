<?php

namespace AvsOperatorStatus\Shortcodes;

use WP_Query;

/**
 * Class LineebollentiAllOperatorsGridShortcode
 *
 * Handles the [lineebollenti_all_operators_grid] shortcode.
 */
class LineebollentiAllOperatorsGridShortcode {
    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'lineebollenti_all_operators_grid', [ $this, 'render' ] );
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

        $tutte_le_numerazioni = get_terms( [ 'taxonomy' => 'numerazione', 'hide_empty' => false ] );
        $numerazioni_standard = [];

        if ( ! is_wp_error( $tutte_le_numerazioni ) && ! empty( $tutte_le_numerazioni ) ) {
            foreach ( $tutte_le_numerazioni as $num ) {
                if (
                    stripos( $num->description, 'Ricarica Online' ) === false &&
                    stripos( $num->description, 'Carta di Credito' ) === false &&
                    stripos( $num->description, 'Svizzera' ) === false &&
                    stripos( $num->description, 'Basso Costo' ) === false
                ) {
                    $numerazioni_standard[] = $num;
                }
            }
        }

        $operators_query = new WP_Query( [
            'post_type' => 'operatrice',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [ [
                'key' => '_thumbnail_id',
                'compare' => 'EXISTS',
            ] ],
        ] );

        if ( ! $operators_query->have_posts() ) {
            echo '<p>Nessuna operatrice disponibile per la visualizzazione.</p>';
            return ob_get_clean();
        }
        ?>
        <div class="uk-section uk-section-small">
            <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">Tutte le Operatrici</h2>
            <div class="uk-grid-match uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l" uk-grid>
                <?php
                while ( $operators_query->have_posts() ) {
                    $operators_query->the_post();
                    $operator_id = get_the_ID();
                    $operator_title = get_the_title();
                    $operator_link = get_permalink( $operator_id );

                    $cleaned_content = strip_shortcodes( get_the_content() );
                    $operator_intro = wp_trim_words( $cleaned_content, 15, '...' );

                    $operator_image_url = '';
                    if ( has_post_thumbnail() ) {
                        $image_id = get_post_thumbnail_id( $operator_id );
                        $image_src = wp_get_attachment_image_src( $image_id, 'medium' );
                        if ( $image_src ) {
                            $operator_image_url = $image_src[0];
                        } else {
                            $operator_image_url = get_the_post_thumbnail_url( $operator_id, 'full' );
                        }
                    }

                    $generi = get_the_terms( $operator_id, 'genere' );
                    $genere_nome = ( $generi && ! is_wp_error( $generi ) ) ? $generi[0]->name : '';

                    $display_phone_number = '';
                    if ( ! empty( $genere_nome ) ) {
                        foreach ( $numerazioni_standard as $numerazione ) {
                            if ( stripos( $numerazione->description, $genere_nome ) !== false ) {
                                $display_phone_number = $numerazione->name;
                                break;
                            }
                        }
                        if ( empty( $display_phone_number ) && $genere_nome === 'Etero Basso Costo' ) {
                            foreach ( $numerazioni_standard as $numerazione ) {
                                if ( stripos( $numerazione->description, 'Etero' ) !== false ) {
                                    $display_phone_number = $numerazione->name;
                                    break;
                                }
                            }
                        }
                    }
                    ?>
                    <div>
                        <a href="<?php echo esc_url( $operator_link ); ?>" class="uk-link-reset">
                            <div class="uk-card uk-card-default uk-card-hover uk-card-body uk-text-center uk-border-rounded">
                                <?php if ( ! empty( $operator_image_url ) ) : ?>
                                    <div class="uk-card-media-top uk-margin-bottom">
                                        <img src="<?php echo esc_url( $operator_image_url ); ?>" alt="<?php echo esc_attr( $operator_title ); ?>" class="uk-border-circle" width="120" height="120" style="object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <h3 class="uk-card-title uk-margin-small-top"><?php echo esc_html( $operator_title ); ?></h3>
                                <?php if ( ! empty( $genere_nome ) ) : ?>
                                    <div class="uk-text-primary uk-text-small uk-margin-remove-top uk-margin-small-bottom">Genere: <?php echo esc_html( $genere_nome ); ?></div>
                                <?php endif; ?>
                                <?php if ( ! empty( $display_phone_number ) ) : ?>
                                    <div class="uk-text-large uk-text-bold uk-margin-small-bottom aos-phone-number">
                                        <a href="tel://<?php echo esc_attr( $display_phone_number ); ?>" class="uk-link-reset">
                                            <span uk-icon="icon: receiver"></span> <?php echo esc_html( $display_phone_number ); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ( ! empty( $operator_intro ) ) : ?>
                                    <p class="uk-text-small uk-text-muted"><?php echo wp_kses_post( $operator_intro ); ?></p>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( $operator_link ); ?>" class="uk-button uk-button-text uk-margin-small-top">Lascia un messaggio &rarr;</a>
                            </div>
                        </a>
                    </div>
                    <?php
                }
                wp_reset_postdata();
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
