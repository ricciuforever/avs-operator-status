<?php

namespace AvsOperatorStatus\Shortcodes;

/**
 * Class TariffeNumerazioniShortcode
 *
 * Handles the [tariffe_numerazioni] shortcode.
 */
class TariffeNumerazioniShortcode {

    /**
     * Registers the shortcode.
     */
    public function register() {
        add_shortcode( 'tariffe_numerazioni', [ $this, 'render' ] );
    }

    /**
     * Renders the shortcode output.
     *
     * @param array $atts The shortcode attributes.
     * @return string The HTML output.
     */
    public function render( $atts ) {
        $numerazioni = get_terms( [
            'taxonomy'   => 'numerazione',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $numerazioni ) || empty( $numerazioni ) ) {
            return '';
        }

        ob_start();

        foreach ( $numerazioni as $numerazione ) {
            $currency_symbol = '€';
            if ( strpos( trim( $numerazione->name ), '+41' ) === 0 ) {
                $currency_symbol = 'CHF';
            }

            $tariffe = get_term_meta( $numerazione->term_id, '_aos_tariffe_meta', true );
            ?>
            <h3 class="uk-heading-divider uk-margin-medium-top">
                <?php
                echo esc_html( $numerazione->name );
                if ( ! empty( $numerazione->description ) ) {
                    echo ' <span class="uk-text-muted">(' . esc_html( $numerazione->description ) . ')</span>';
                }
                ?>
            </h3>
            <?php
            $mostra_riga_unica = (
                stripos( $numerazione->description, 'Carta di Credito' ) !== false ||
                stripos( $numerazione->description, 'Ricarica Online' ) !== false ||
                stripos( $numerazione->description, 'Svizzera' ) !== false ||
                strpos( trim( $numerazione->name ), '+41' ) === 0
            );

            if ( ! empty( $tariffe ) && is_array( $tariffe ) ) {
                ?>
                <div class="uk-overflow-auto">
                    <table class="uk-table uk-table-hover uk-table-striped uk-table-middle uk-text-center">
                        <thead>
                            <tr>
                                <th class="uk-text-center">Gestore</th>
                                <th class="uk-text-center">Scatto alla risposta</th>
                                <th class="uk-text-center">Costo al minuto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ( $mostra_riga_unica ) :
                                $prima_tariffa = $tariffe[0];
                                ?>
                                <tr>
                                    <td><strong>Da qualsiasi gestore</strong></td>
                                    <td><?php echo number_format_i18n( $prima_tariffa['scatto'], 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></td>
                                    <td><?php echo number_format_i18n( $prima_tariffa['importo'], 2 ); ?> <?php echo esc_html( $currency_symbol ); ?> / min</td>
                                </tr>
                            <?php else :
                                foreach ( $tariffe as $tariffa ) :
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( ucfirst( $tariffa['operatore'] ) ); ?></td>
                                        <td><?php echo number_format_i18n( $tariffa['scatto'], 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></td>
                                        <td><?php echo number_format_i18n( $tariffa['importo'], 2 ); ?> <?php echo esc_html( $currency_symbol ); ?> / min</td>
                                    </tr>
                                <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
            } else {
                ?>
                <p><em>Le tariffe per questa numerazione non sono ancora disponibili.</em></p>
                <?php
            }
        }

        return ob_get_clean();
    }
}
