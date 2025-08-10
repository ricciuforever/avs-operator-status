<?php

namespace AvsOperatorStatus\Features;

use AvsOperatorStatus\Utils\Helpers;
use WP_Query;

/**
 * Class Quiz
 *
 * Encapsulates all functionality for the "Find Your Operator" quiz.
 */
class Quiz {
    /**
     * Registers all hooks for the quiz feature.
     */
    public function register() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

        // AJAX handlers
        add_action( 'wp_ajax_nopriv_calcola_quiz_results', [ $this, 'calculate_results_ajax_handler' ] );
        add_action( 'wp_ajax_calcola_quiz_results', [ $this, 'calculate_results_ajax_handler' ] );
        add_action( 'wp_ajax_nopriv_avs_track_quiz_event', [ $this, 'track_event_ajax_handler' ] );
        add_action( 'wp_ajax_avs_track_quiz_event', [ $this, 'track_event_ajax_handler' ] );
    }

    /**
     * Enqueues and localizes the quiz JavaScript.
     */
    public function enqueue_scripts() {
        wp_register_script( 'avs-quiz-script', AOS_PLUGIN_URL . 'js/quiz-script.js', [ 'jquery' ], '3.0.0', true );
        wp_localize_script( 'avs-quiz-script', 'quiz_params', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'quiz_nonce' )
        ] );
    }

    /**
     * Adds the admin menu page for quiz statistics.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Statistiche Quiz',
            'Statistiche Quiz',
            'manage_options',
            'avs-quiz-stats',
            [ $this, 'render_stats_page_html' ],
            'dashicons-chart-pie',
            25
        );
    }

    /**
     * AJAX handler to calculate and return quiz results.
     */
    public function calculate_results_ajax_handler() {
        check_ajax_referer( 'quiz_nonce', 'security' );
        $answers_json = isset( $_POST['answers'] ) ? stripslashes( $_POST['answers'] ) : '[]';
        $answers = json_decode( $answers_json, true );

        $base_scores = [];
        $operatrici_query = new WP_Query( [ 'post_type' => 'operatrice', 'post_status' => 'publish', 'posts_per_page' => -1 ] );

        if ( $operatrici_query->have_posts() ) {
            while ( $operatrici_query->have_posts() ) {
                $operatrici_query->the_post();
                $post_id = get_the_ID();
                $base_scores[ $post_id ] = 0;

                $generi_terms = get_the_terms( $post_id, 'genere' );
                $generi_str = $generi_terms && ! is_wp_error( $generi_terms ) ? implode( ' ', wp_list_pluck( $generi_terms, 'name' ) ) : '';
                $intro = get_post_meta( $post_id, 'intro_operatrice', true );
                $contenuto = strtolower( get_the_title() . ' ' . $generi_str . ' ' . $intro );
                $nome = strtolower( get_the_title() );
                $data_pubblicazione = get_the_date( 'U' );

                foreach ( $answers as $qa ) {
                    $type = $qa['type'];
                    $answer = $qa['answer'];

                    switch ($type) {
                        case 'intenzione':
                            if ($answer === 'frizzante') { $keywords = ['dolce', 'simpatica', 'giocosa', 'allegra', 'complice', 'solare', 'frizzante', 'monella', 'stuzzicare', 'scherzare', 'sorriso', 'piano', 'innocenti', 'evasioni', 'timida', 'dolcezza', 'spiritosa']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'passionale') { $keywords = ['passionale', 'intensa', 'trasgressiva', 'senza filtri', 'senza tabù', 'godere', 'assoluto', 'eccitata', 'scateno', 'libidine', 'peperina', 'faccio morire', 'provarmi', 'esplodere', 'piacere', 'lussuria', 'peccato', 'infuocata', 'bruciante']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'esotica') { $desc_keywords = ['esotica', 'orientale', 'misteriosa', 'sensuale', 'stravagante', 'originale', 'scoprilo', 'immagini', 'segreti', 'mistero', 'magia', 'affascinante']; $name_keywords = ['akira', 'samira', 'naomi', 'morgana', 'kayla', 'chloe', 'jasmine', 'yuki', 'soo-jin', 'abeni', 'eleni', 'malee', 'sakura', 'fatima', 'nala', 'moana']; if ($this->string_contains($contenuto, $desc_keywords)) $base_scores[$post_id] += 8; if ($this->string_contains($nome, $name_keywords)) $base_scores[$post_id] += 5; }
                            break;
                        case 'approccio':
                            if ($answer === 'dolcezza') { $keywords = ['dolce', 'premurosa', 'ascolto', 'comprensiva', 'amante', 'complice', 'romantica', 'dolcezza', 'coccole', 'abbraccio']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'dominazione') { $keywords = ['dominante', 'padrona', 'severa', 'autoritaria', 'esigente', 'senza tabù', 'padrone', 'comando', 'ordini', 'regole', 'sottomessa', 'sottomesso']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'mix') { $base_scores[$post_id] += 3; }
                            break;
                        case 'esperienza':
                            if ($answer === 'esperta') { if ($data_pubblicazione < strtotime('-1 year')) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'novita') { if ($data_pubblicazione > strtotime('-3 months')) $base_scores[$post_id] += 10; }
                            break;
                        case 'dialogo':
                            if ($answer === 'diretto') { $keywords = ['diretta', 'senza filtri', 'sincera', 'schiatta', 'onesta']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'allusivo') { $keywords = ['allusiva', 'allusivo', 'stuzzicare', 'sensuale', 'vedo non vedo', 'sussurrare']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'ascolto') { $keywords = ['ascolto', 'comprensiva', 'amica', 'consigli', 'segreti']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 8; }
                            break;
                        case 'carattere':
                            if ($answer === 'estroverso') { $keywords = ['estroversa', 'solare', 'allegra', 'simpatica', 'espansiva', 'chiacchierona']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'introverso') { $keywords = ['introversa', 'riservata', 'timida', 'misteriosa', 'profonda']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                            elseif ($answer === 'maturo') { $keywords = ['matura', 'esperta', 'sicura', 'donna', 'consapevole']; if ($this->string_contains($contenuto, $keywords)) $base_scores[$post_id] += 8; }
                            break;
                    }
                }
            }
        }
        wp_reset_postdata();

        $performance_multipliers = $this->get_performance_multipliers();
        $final_scores = [];
        foreach ( $base_scores as $post_id => $base_score ) {
            if ( $base_score > 0 ) {
                $multiplier = $performance_multipliers[ $post_id ] ?? 1.0;
                $final_scores[ $post_id ] = $base_score * $multiplier;
            }
        }

        arsort( $final_scores );
        $top_3_ids = array_slice( array_keys( $final_scores ), 0, 3 );

        if ( count( $top_3_ids ) < 3 ) {
            $fallback_operators_query = new WP_Query( [
                'post_type' => 'operatrice',
                'post_status' => 'publish',
                'posts_per_page' => 3,
                'orderby' => 'rand',
            ] );
            $top_3_ids = wp_list_pluck( $fallback_operators_query->posts, 'ID' );
            wp_reset_postdata();
        }

        global $post;
        $html_output = '<div class="uk-grid uk-child-width-1-1 uk-child-width-1-3@m" uk-grid>';
        if ( ! empty( $top_3_ids ) ) {
            foreach ( $top_3_ids as $id ) {
                $post = get_post( $id );
                if ( $post ) {
                    setup_postdata( $post );
                    $html_output .= '<div>' . Helpers::aos_render_operator_card_html( $post ) . '</div>';
                }
            }
            wp_reset_postdata();
        } else {
            $html_output .= '<div class="uk-width-1-1"><p class="uk-text-center">Ci scusiamo, al momento non abbiamo operatrici disponibili.</p></div>';
        }
        $html_output .= '</div>';

        wp_send_json_success( [ 'html' => $html_output, 'ids' => $top_3_ids ] );
    }

    /**
     * AJAX handler to track quiz interaction events.
     */
    public function track_event_ajax_handler() {
        if ( current_user_can( 'manage_options' ) ) {
            wp_send_json_success( [ 'status' => 'administrator ignored' ] );
            return;
        }

        check_ajax_referer( 'quiz_nonce', 'security' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'avs_quiz_tracking';

        $session_id = sanitize_text_field( $_POST['session_id'] );
        $event_type = sanitize_text_field( $_POST['event_type'] );
        $question_number = isset( $_POST['question_number'] ) ? intval( $_POST['question_number'] ) : null;
        $answer_key = isset( $_POST['answer_key'] ) ? sanitize_text_field( $_POST['answer_key'] ) : null;
        $operator_id = isset( $_POST['operator_id'] ) ? intval( $_POST['operator_id'] ) : null;
        $click_context = isset( $_POST['click_context'] ) ? esc_url_raw( $_POST['click_context'] ) : '';

        $wpdb->insert(
            $table_name,
            [
                'session_id' => $session_id,
                'event_type' => $event_type,
                'question_number' => $question_number,
                'answer_key' => $answer_key,
                'operator_id' => $operator_id,
                'click_context' => $click_context,
            ],
            [ '%s', '%s', '%d', '%s', '%d', '%s' ]
        );

        wp_send_json_success( [ 'status' => 'event tracked' ] );
    }

    /**
     * Renders the HTML for the quiz statistics page in the admin area.
     */
    public function render_stats_page_html() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avs_quiz_tracking';

        ?>
        <div class="wrap">
            <h1><span class="dashicons-before dashicons-chart-pie"></span> Statistiche di Interazione del Quiz</h1>
            <p>Questa pagina mostra come gli utenti interagiscono con il quiz, quali risposte scelgono e quali operatrici vengono suggerite e cliccate più spesso.</p>
            <hr>
            <h2 style="margin-top: 30px;">Efficacia dei Suggerimenti (Click-Through Rate)</h2>
            <p>Questa tabella mostra quante volte un'operatrice è stata suggerita dal quiz e quante volte è stata cliccata, calcolando il tasso di conversione (CTR).</p>
            <?php
            $views_query = "SELECT operator_id, COUNT(*) as total_views FROM {$table_name} WHERE event_type = 'risultati_mostrati' AND operator_id IS NOT NULL GROUP BY operator_id";
            $views_results = $wpdb->get_results( $views_query, OBJECT_K );

            $clicks_query = "SELECT operator_id, COUNT(*) as total_clicks FROM {$table_name} WHERE event_type = 'risultato_cliccato' AND operator_id IS NOT NULL GROUP BY operator_id";
            $clicks_results = $wpdb->get_results( $clicks_query, OBJECT_K );

            $stats_data = [];
            if ( $views_results ) {
                foreach ( $views_results as $op_id => $data ) {
                    $clicks = $clicks_results[ $op_id ]->total_clicks ?? 0;
                    $views = $data->total_views;
                    $ctr = ( $views > 0 ) ? ( $clicks / $views ) * 100 : 0;
                    $stats_data[ $op_id ] = [
                        'title' => get_the_title( $op_id ),
                        'views' => $views,
                        'clicks' => $clicks,
                        'ctr' => $ctr,
                    ];
                }
            }

            uasort( $stats_data, function( $a, $b ) {
                return $b['ctr'] <=> $a['ctr'];
            } );

            if ( ! empty( $stats_data ) ) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th style="width:40%;">Nome Operatrice</th><th>Suggerita (volte)</th><th>Cliccata (volte)</th><th style="width:20%;">CTR</th></tr></thead>';
                echo '<tbody>';
                foreach ( $stats_data as $row ) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html( $row['title'] ) . '</strong></td>';
                    echo '<td>' . esc_html( $row['views'] ) . '</td>';
                    echo '<td>' . esc_html( $row['clicks'] ) . '</td>';
                    echo '<td><strong>' . esc_html( number_format( $row['ctr'], 2 ) ) . '%</strong></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>Nessun dato ancora raccolto.</p>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Gets performance multipliers for operators based on quiz interaction.
     * @return array A map of [operator_id => multiplier].
     */
    private function get_performance_multipliers(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avs_quiz_tracking';
        $multipliers = [];

        $views_query = "SELECT operator_id, COUNT(*) as total_views FROM {$table_name} WHERE event_type = 'risultati_mostrati' AND operator_id IS NOT NULL GROUP BY operator_id";
        $views_results = $wpdb->get_results( $views_query, OBJECT_K );

        $clicks_query = "SELECT operator_id, COUNT(*) as total_clicks FROM {$table_name} WHERE event_type = 'risultato_cliccato' AND operator_id IS NOT NULL GROUP BY operator_id";
        $clicks_results = $wpdb->get_results( $clicks_query, OBJECT_K );

        if ( $views_results ) {
            foreach ( $views_results as $op_id => $data ) {
                $clicks = $clicks_results[ $op_id ]->total_clicks ?? 0;
                $views = $data->total_views;
                $confidence_score = ( $clicks + 2 ) / ( $views + 4 );
                $multiplier = 0.5 + $confidence_score;
                $multipliers[ $op_id ] = $multiplier;
            }
        }
        return $multipliers;
    }

    /**
     * Checks if a string contains any of the given keywords.
     * @param string $string The string to search in.
     * @param array $keywords The keywords to search for.
     * @return bool True if a keyword is found, false otherwise.
     */
    private function string_contains( string $string, array $keywords ): bool {
        foreach ( $keywords as $keyword ) {
            if ( strpos( $string, $keyword ) !== false ) return true;
        }
        return false;
    }
}
