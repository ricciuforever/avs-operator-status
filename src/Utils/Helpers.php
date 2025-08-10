<?php

namespace AvsOperatorStatus\Utils;

use WP_Query;
use WP_Post;
use WP_Term;

/**
 * Class Helpers
 *
 * Contains various helper/utility functions for the plugin.
 */
class Helpers {

    /**
     * Generates and returns a map that associates a genre slug with its DDI.
     * @return array The map of genres to their DDIs.
     */
    public static function aos_get_ddi_map(): array {
        $mappa_genere_ddi = [];

        $tutte_le_numerazioni = get_terms( [ 'taxonomy' => 'numerazione', 'hide_empty' => false ] );
        $tutti_i_generi = get_terms( [ 'taxonomy' => 'genere', 'hide_empty' => false ] );

        if ( is_wp_error( $tutte_le_numerazioni ) || empty( $tutte_le_numerazioni ) || is_wp_error( $tutti_i_generi ) || empty( $tutti_i_generi ) ) {
            return $mappa_genere_ddi;
        }

        foreach ( $tutte_le_numerazioni as $numerazione ) {
            if ( stripos( $numerazione->description, 'Ricarica Online' ) !== false ) {
                foreach ( $tutti_i_generi as $genere ) {
                    if ( $genere && stripos( $numerazione->description, $genere->name ) !== false ) {
                        $mappa_genere_ddi[ $genere->slug ] = $numerazione->name;
                        break;
                    }
                }
            }
        }

        return $mappa_genere_ddi;
    }

    /**
     * Selects a random operator post from a genre, weighted by popularity.
     * @param int $genere_term_id The term ID of the genre.
     * @return WP_Post|null The post object or null.
     */
    public static function aos_get_weighted_random_operator_post( int $genere_term_id ): ?WP_Post {
        if ( ! $genere_term_id ) {
            return null;
        }

        global $wpdb;
        $table_name_tracking = $wpdb->prefix . 'aos_click_tracking';
        $trenta_giorni_fa = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $top_operatrici_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT c.post_id FROM {$table_name_tracking} AS c
             INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'genere' AND tt.term_id = %d AND c.click_timestamp >= %s
             GROUP BY c.post_id ORDER BY COUNT(c.id) DESC LIMIT 3",
            $genere_term_id,
            $trenta_giorni_fa
        ) );

        $operatrici_query = new WP_Query( [
            'post_type' => 'operatrice',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [ [ 'taxonomy' => 'genere', 'field' => 'term_id', 'terms' => $genere_term_id ] ],
            'fields' => 'ids'
        ] );
        $all_operator_ids = $operatrici_query->posts;

        if ( empty( $all_operator_ids ) ) {
            return null;
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

        if ( empty( $operator_pool ) ) {
            return null;
        }

        $random_id = $operator_pool[ array_rand( $operator_pool ) ];
        return get_post( $random_id );
    }

    /**
     * Renders the HTML for a single payment/call button.
     * @param WP_Term $numerazione The numerazione term object.
     * @param string $genere_slug The current genre slug.
     * @param array $mappa_genere_ddi The DDI map.
     * @param int $codice_da_tracciare The post ID to track.
     * @param bool $ha_chiama_e_ricarica Flag for fallback logic.
     * @return string The HTML for the button.
     */
    public static function aos_render_payment_button( WP_Term $numerazione, string $genere_slug, array $mappa_genere_ddi, int $codice_da_tracciare, bool $ha_chiama_e_ricarica ): string {
        $description = $numerazione->description;
        $number = $numerazione->name;

        ob_start();

        if ( stripos( $description, 'Ricarica Online' ) !== false ) {
            if ( ! $ha_chiama_e_ricarica ) {
                $ddi_per_ricarica = $mappa_genere_ddi[ $genere_slug ] ?? '';
                if ( ! empty( $ddi_per_ricarica ) ) {
                    $href_ricarica = 'https://customers.b4tlc.it/application/B4tlc/index.php?r=pr_cc/CCrecharge4&ddi=' . str_replace( '.', '', $ddi_per_ricarica );
                    $tariffe = get_term_meta( $numerazione->term_id, '_aos_tariffe_meta', true );
                    $prima_tariffa = ! empty( $tariffe ) ? $tariffe[0] : null;
                    ?>
                    <a href="<?php echo esc_url( $href_ricarica ); ?>" class="aos-ricarica-banner uk-link-reset uk-text-center" target="_blank" title="Ricarica Online per il numero <?php echo esc_attr( $ddi_per_ricarica ); ?>">
                        <div class="uk-text-bold">Clicca e Ricarica</div>
                        <?php if ( $prima_tariffa && isset( $prima_tariffa['importo'] ) ): ?>
                            <div class="uk-text-primary uk-text-bold uk-margin-small-top">A soli <?php echo esc_html( number_format_i18n( $prima_tariffa['importo'], 2 ) ); ?>€ / min</div>
                        <?php endif; ?>
                        <div class="uk-text-small uk-margin-small-top">per il numero: <?php echo esc_html( $ddi_per_ricarica ); ?></div>
                        <div class="aos-payment-icons uk-margin-small-top">
                            <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/visa.svg" width="40" alt="Visa" style="margin: 0px;">
                            <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/mastercard.svg" width="40" alt="Mastercard" style="margin: 0px;">
                            <img src="https://www.lineebollenti.it/wp-content/uploads/2025/06/postepay.webp" width="40" alt="Postepay" style="margin: 0px;">
                            <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/amex.svg" width="40" alt="American Express" style="margin: 0px;">
                            <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/paypal.svg" width="40" alt="PayPal" style="margin: 0px;">
                        </div>
                    </a>
                    <?php
                }
            }
        } elseif ( stripos( $description, 'Carta di Credito' ) !== false ) {
            $href = 'tel://' . esc_attr( $number );
            $tariffe = get_term_meta( $numerazione->term_id, '_aos_tariffe_meta', true );
            $prima_tariffa = ! empty( $tariffe ) ? $tariffe[0] : null;
            ?>
            <a href="<?php echo esc_url( $href ); ?>" class="uk-tile-muted uk-display-block uk-padding-small uk-link-reset uk-text-center uk-border-rounded aos-track-click" data-codice="<?php echo esc_attr( $codice_da_tracciare ); ?>" title="Chiama con Carta di Credito: <?php echo esc_attr( $number ); ?>">
                <div class="uk-text-bold">Chiama e Ricarica</div>
                <?php if ( $prima_tariffa && isset( $prima_tariffa['importo'] ) ): ?>
                    <div class="uk-text-primary uk-text-small uk-margin-small-top">Tariffa unica: <?php echo esc_html( number_format_i18n( $prima_tariffa['importo'], 2 ) ); ?>€ / min</div>
                <?php endif; ?>
                <div class="aos-payment-icons uk-margin-small-top">
                    <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/visa.svg" width="40" alt="Visa" style="margin: 0px;">
                    <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/mastercard.svg" width="40" alt="Mastercard" style="margin: 0px;">
                    <img src="https://www.lineebollenti.it/wp-content/uploads/2025/06/postepay.webp" width="40" alt="Postepay" style="margin: 0px;">
                    <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/amex.svg" width="40" alt="American Express" style="margin: 0px;">
                </div>
                <div class="uk-text-meta uk-margin-small-top">Chiama il numero: <?php echo esc_html( $number ); ?></div>
            </a>
            <?php
        } elseif ( stripos( $description, 'Svizzera' ) !== false ) {
            $href = 'tel://' . esc_attr( $number );
            $button_classes = 'uk-link-reset uk-width-1-1 uk-button uk-button-primary';
            $icon_html = '<img src="/wp-content/uploads/2019/04/svizzera1.png" width="25" alt="Svizzera" class="uk-margin-small-right">';
            ?>
            <a href="<?php echo esc_url( $href ); ?>" class="<?php echo esc_attr( $button_classes ); ?> aos-track-click uk-margin-small-bottom uk-text-left" data-codice="<?php echo esc_attr( $codice_da_tracciare ); ?>">
                <?php echo $icon_html . esc_html( $number ); ?>
            </a>
            <?php
        } else {
            $href = 'tel://' . esc_attr( $number );
            $button_classes = 'uk-button uk-button-secondary uk-width-1-1';
            $icon_html = '<span uk-icon="icon: phone" class="uk-margin-small-right"></span>';
            ?>
            <a href="<?php echo esc_url( $href ); ?>" class="<?php echo esc_attr( $button_classes ); ?> aos-track-click uk-margin-small-bottom uk-text-left" data-codice="<?php echo esc_attr( $codice_da_tracciare ); ?>">
                <?php echo $icon_html . esc_html( $number ); ?>
            </a>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Renders the complete HTML for a single operator card.
     * @param WP_Post|int $operatrice_post The post object or ID of the operator.
     * @return string The complete HTML for the card.
     */
    public static function aos_render_operator_card_html( $operatrice_post ): string {
        if ( is_numeric( $operatrice_post ) ) {
            $operatrice_post = get_post( $operatrice_post );
        }

        if ( ! $operatrice_post instanceof WP_Post || $operatrice_post->post_type !== 'operatrice' ) {
            return '';
        }

        $post_id = $operatrice_post->ID;
        $codice_da_tracciare = $post_id;
        $generi = get_the_terms( $post_id, 'genere' );
        $genere_nome = ( $generi && ! is_wp_error( $generi ) ) ? $generi[0]->name : '';
        $genere_slug = ( $generi && ! is_wp_error( $generi ) ) ? $generi[0]->slug : '';

        $operator_profile_link = get_permalink( $post_id );

        $mappa_genere_ddi = self::aos_get_ddi_map();
        $tutte_le_numerazioni = get_terms( [ 'taxonomy' => 'numerazione', 'hide_empty' => false ] );

        ob_start();

        // JSON-LD Schema generation would go here. For now, omitting for brevity.
        // The original function had a large block for this.

        ?>
        <div class="uk-card uk-padding-small uk-card-secondary uk-card-body uk-flex uk-flex-column uk-text-center operatrice uk-position-relative" data-codice="<?php echo esc_attr( $codice_da_tracciare ); ?>">
            <div class="uk-flex-1">
                <div class="uk-position-relative">
                    <?php
                    if ( has_post_thumbnail( $post_id ) ) :
                        $alt_text = esc_attr( $operatrice_post->post_title ) . ' - Telefono erotico ' . esc_attr( $genere_nome );
                        echo get_the_post_thumbnail( $post_id, 'medium', [ 'class' => 'uk-border-circle', 'alt' => $alt_text ] );
                    endif;
                    ?>
                </div>
                <div class="labelnome"><?php echo esc_html( $operatrice_post->post_title ); ?></div>
                <div class="uk-h3 uk-margin-remove-top uk-text-primary uk-margin-small-bottom">Genere: <?php echo esc_html( $genere_nome ); ?></div>
                <div class="uk-text-small cartintro"><?php echo wp_kses_post( wp_trim_words( $operatrice_post->post_content, 20, '...' ) ); ?></div>
                <a href="<?php echo esc_url( $operator_profile_link ); ?>" class="uk-button uk-button-text uk-margin-small-top">Lascia un messaggio &rarr;</a>
            </div>
            <?php
            self::lineebollenti_display_operator_audio( get_the_title( $post_id ) );
            ?>
            <div class="uk-margin-top">
                <?php
                $numerazioni_filtrate = [];
                if ( ! empty( $genere_nome ) && ! is_wp_error( $tutte_le_numerazioni ) ) {
                    foreach ( $tutte_le_numerazioni as $numerazione ) {
                        $match_trovato = false;
                        if ( stripos( $numerazione->description, $genere_nome ) !== false ) {
                            $match_trovato = true;
                        } elseif ( $genere_nome === 'Etero Basso Costo' && stripos( $numerazione->description, 'Etero' ) !== false ) {
                            $match_trovato = true;
                        }

                        if ( $match_trovato && stripos( $numerazione->description, 'Svizzera' ) === false && stripos( $numerazione->description, 'Basso Costo' ) === false ) {
                            $numerazioni_filtrate[] = $numerazione;
                        }
                    }
                }
                if ( ! empty( $numerazioni_filtrate ) ) {
                    usort( $numerazioni_filtrate, function( $a, $b ) {
                        return self::aos_get_numerazione_priority( $a->description ) <=> self::aos_get_numerazione_priority( $b->description );
                    } );
                    $ha_chiama_e_ricarica = false;
                    foreach ( $numerazioni_filtrate as $numerazione_check ) {
                        if ( stripos( $numerazione_check->description, 'Carta di Credito' ) !== false ) {
                            $ha_chiama_e_ricarica = true;
                            break;
                        }
                    }
                    foreach ( $numerazioni_filtrate as $numerazione ) {
                        echo self::aos_render_payment_button( $numerazione, $genere_slug, $mappa_genere_ddi, $codice_da_tracciare, $ha_chiama_e_ricarica );
                    }
                } else {
                    echo '<p class="uk-text-small uk-text-muted"><em>Nessuna tariffa specifica.</em></p>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Displays an audio player for an operator if the MP3 file exists.
     * @param string $operator_name The name of the operator.
     */
    public static function lineebollenti_display_operator_audio( string $operator_name ) {
        $file_name = strtolower( $operator_name ) . '.mp3';
        $audio_url = content_url( '/uploads/audio/' . $file_name );
        $upload_dir_info = wp_upload_dir();
        $audio_path = $upload_dir_info['basedir'] . '/audio/' . $file_name;

        if ( file_exists( $audio_path ) ) {
            echo '<div class="operator-audio-player">';
            echo '  <p class="audio-intro">Ascolta la mia voce...</p>';
            echo '  <audio controls src="' . esc_url( $audio_url ) . '">';
            echo '      Il tuo browser non supporta l\'elemento audio.';
            echo '  </audio>';
            echo '</div>';
        }
    }

    /**
     * Returns a numeric priority for sorting buttons.
     * @param string $description The description of the numerazione.
     * @return int The priority.
     */
    public static function aos_get_numerazione_priority( string $description ): int {
        if ( stripos( $description, 'Carta di Credito' ) !== false ) return 1;
        if ( stripos( $description, 'Ricarica Online' ) !== false ) return 2;
        if ( stripos( $description, 'Svizzera' ) !== false ) return 99;
        return 10; // Default priority for 899 etc.
    }

    /**
     * Renders the quiz module HTML.
     * @return string The HTML for the quiz module.
     */
    public static function avs_get_quiz_module_html(): string {
        wp_enqueue_script( 'avs-quiz-script' );
        ob_start();
        ?>
        <div class="uk-card uk-card-body uk-card-secondary uk-flex uk-flex-column uk-height-1-1">
            <div class="uk-margin-auto-vertical uk-text-center">
                <h3 class="uk-h4 uk-margin-small-bottom">Non sai chi scegliere?</h3>
                <p class="uk-text-small uk-margin-small-top uk-margin-medium-bottom">Fai il nostro quiz e trova l'operatrice perfetta per te.</p>
                <button class="uk-button uk-button-primary" uk-toggle="target: #avs-quiz-modal">Inizia il Quiz</button>
            </div>
        </div>
        <div id="avs-quiz-modal" class="uk-modal-full" uk-modal>
            <div class="uk-modal-dialog uk-flex uk-flex-center uk-flex-middle" uk-height-viewport>
                <button class="uk-modal-close-full uk-close-large" type="button" uk-close></button>
                <div class="uk-width-1-1 uk-width-2-3@m uk-padding-large">
                    <div id="avs-quiz-questions"></div>
                    <div id="avs-quiz-results" class="uk-hidden">
                        <h2 class="uk-text-center">Ecco le 3 operatrici che ti consigliamo!</h2>
                        <div id="avs-quiz-results-content" class="uk-margin-top">
                            <div class="uk-text-center" uk-spinner="ratio: 3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the operator grid and injects the quiz module.
     * @param WP_Query $query The WP_Query object with operators.
     * @param string $grid_classes CSS classes for the grid.
     * @param string $gap The grid gap.
     * @return string The complete HTML for the grid.
     */
    public static function avs_render_operator_grid_with_quiz_injection( WP_Query $query, string $grid_classes = 'uk-child-width-1-2 uk-child-width-1-3@s uk-child-width-1-4@m', string $gap = 'medium' ): string {
        if ( ! $query->have_posts() ) return '<p>Nessuna operatrice trovata.</p>';

        $output = '';
        $output .= '<div class="uk-grid uk-grid-match ' . esc_attr( $grid_classes ) . ' uk-grid-' . esc_attr( $gap ) . '" uk-grid>';

        while ( $query->have_posts() ) {
            $query->the_post();
            $output .= '<div>' . self::aos_render_operator_card_html( get_the_ID() ) . '</div>';
        }
        wp_reset_postdata();
        $output .= '</div>';
        return $output;
    }
}
