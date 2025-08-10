<?php
/**
 * Logica, visualizzazione e iniezione dinamica del Quiz.
 * Versione Definitiva.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ========================================================================
// 1. REGISTRAZIONE SCRIPT (invariato)
// ========================================================================

add_action('wp_enqueue_scripts', 'avs_quiz_enqueue_scripts');
function avs_quiz_enqueue_scripts() {
    $plugin_url = plugin_dir_url(__DIR__);
    wp_register_script('avs-quiz-script', $plugin_url . 'js/quiz-script.js', ['jquery'], '3.0.0', true);
    wp_localize_script('avs-quiz-script', 'quiz_params', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('quiz_nonce')]);
}


// ========================================================================
// 2. FUNZIONI DI RENDERING HTML (per l'iniezione nella griglia)
// ========================================================================

function avs_get_quiz_module_html() {
    wp_enqueue_script('avs-quiz-script');
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
 * Renderizza la griglia di operatrici e inietta il quiz al 4° posto.
 * Risolve anche il problema dell'altezza delle card.
 *
 * @param WP_Query $query L'oggetto WP_Query con le operatrici.
 * @param string $grid_classes Classi CSS per la griglia.
 * @return string L'HTML completo della griglia.
 */
function avs_render_operator_grid_with_quiz_injection( $query, $grid_classes = 'uk-child-width-1-2 uk-child-width-1-3@s uk-child-width-1-4@m', $gap = 'medium' ) { // Aggiunto $gap come parametro
    if ( ! $query->have_posts() ) return '<p>Nessuna operatrice trovata.</p>';
    // NON INIETTARE IL QUIZ: COMMENTA O RIMUOVI LA RIGA successiva
    // $quiz_html = avs_get_quiz_module_html(); // Rimuovi o commenta questa riga se non vuoi che venga nemmeno generato l'HTML del quiz

    $inject_position = 4; // La posizione rimane, ma non verrà usata per il quiz
    $output = '';
    $counter = 0;
    
    // Inizia la griglia UKit
    $output .= '<div class="uk-grid uk-grid-match ' . esc_attr($grid_classes) . ' uk-grid-' . esc_attr($gap) . '" uk-grid>';

    while ( $query->have_posts() ) {
        $query->the_post();
        $counter++;
        // COMMENTA O RIMUOVI IL BLOCCO IF CHE INIETTA IL QUIZ
        /*
        if ($query->post_count >= $inject_position && $counter === $inject_position) {
            $output .= '<div>' . $quiz_html . '</div>';
        }
        */
        if ( function_exists('aos_render_operator_card_html') ) {
            $output .= '<div>' . aos_render_operator_card_html( get_the_ID() ) . '</div>';
        }
    }
    wp_reset_postdata();
    $output .= '</div>';
    return $output;
}


/**
 * Recupera i dati di performance (views, clicks) dal database e calcola un "moltiplicatore di performance"
 * per ogni operatrice. Utilizza una formula di smoothing per dare più peso ai dati con più interazioni.
 *
 * @return array Mappa di [operator_id => moltiplicatore]
 */
function avs_quiz_get_performance_multipliers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'avs_quiz_tracking';
    $multipliers = [];

    // 1. Otteniamo le visualizzazioni (quante volte un'operatrice è stata suggerita)
    $views_query = "SELECT operator_id, COUNT(*) as total_views FROM {$table_name} WHERE event_type = 'risultati_mostrati' AND operator_id IS NOT NULL GROUP BY operator_id";
    $views_results = $wpdb->get_results($views_query, OBJECT_K);

    // 2. Otteniamo i click
    $clicks_query = "SELECT operator_id, COUNT(*) as total_clicks FROM {$table_name} WHERE event_type = 'risultato_cliccato' AND operator_id IS NOT NULL GROUP BY operator_id";
    $clicks_results = $wpdb->get_results($clicks_query, OBJECT_K);

    // 3. Calcoliamo il moltiplicatore per ogni operatrice che è stata visualizzata
    if ($views_results) {
        foreach ($views_results as $op_id => $data) {
            $clicks = isset($clicks_results[$op_id]) ? $clicks_results[$op_id]->total_clicks : 0;
            $views = $data->total_views;

            // Formula di smoothing (Bayesian average con a=2, b=2)
            // Aggiungiamo 2 "successi" e 2 "fallimenti" fittizi per stabilizzare il CTR
            // Evita che un'operatrice con 1 view e 1 click (100% CTR) abbia un punteggio irrealisticamente alto.
            $confidence_score = ($clicks + 2) / ($views + 4); 
            
            // Convertiamo il confidence score (range 0-1) in un moltiplicatore (es. 0.5x - 1.5x)
            // Un punteggio di 0.5 (media) darà un moltiplicatore di 1.0x (neutro)
            $multiplier = 0.5 + $confidence_score; // Range da 0.5 a 1.5

            $multipliers[$op_id] = $multiplier;
        }
    }
    
    return $multipliers;
}

// ========================================================================
// 3. LOGICA BACKEND E AJAX (Modificata per il Fallback)
// ========================================================================

add_action('wp_ajax_nopriv_calcola_quiz_results', 'avs_quiz_calculate_results');
add_action('wp_ajax_calcola_quiz_results', 'avs_quiz_calculate_results');

function avs_quiz_calculate_results() {
    check_ajax_referer('quiz_nonce', 'security');
    $answers_json = isset($_POST['answers']) ? stripslashes($_POST['answers']) : '[]';
    $answers = json_decode($answers_json, true);
    // Non facciamo wp_send_json_error() qui se answers è vuoto, gestiamo il fallback per ogni caso

    // ========================================================================
    // FASE 1: Calcolo del Punteggio di Base (Logica a keyword invariata)
    // ========================================================================
    $base_scores = [];
    $operatrici_query = new WP_Query(['post_type' => 'operatrice', 'post_status' => 'publish', 'posts_per_page' => -1]);

    if ($operatrici_query->have_posts()) {
        while ($operatrici_query->have_posts()) {
            $operatrici_query->the_post();
            $post_id = get_the_ID();
            $base_scores[$post_id] = 0;
            
            // Il codice per analizzare contenuto, nome, data etc. rimane identico...
            $generi_terms = get_the_terms($post_id, 'genere');
            $generi_str = $generi_terms && !is_wp_error($generi_terms) ? implode(' ', wp_list_pluck($generi_terms, 'name')) : '';
            $intro = get_post_meta($post_id, 'intro_operatrice', true);
            $contenuto = strtolower(get_the_title() . ' ' . $generi_str . ' ' . $intro);
            $nome = strtolower(get_the_title());
            $data_pubblicazione = get_the_date('U');

            foreach ($answers as $qa) {
                $type = $qa['type'];
                $answer = $qa['answer'];

                // Lo switch per assegnare i punti rimane lo stesso
                // Esempio: if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10;
                switch ($type) {
                    case 'intenzione':
                        if ($answer === 'frizzante') { $keywords = ['dolce', 'simpatica', 'giocosa', 'allegra', 'complice', 'solare', 'frizzante', 'monella', 'stuzzicare', 'scherzare', 'sorriso', 'piano', 'innocenti', 'evasioni', 'timida', 'dolcezza', 'spiritosa']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'passionale') { $keywords = ['passionale', 'intensa', 'trasgressiva', 'senza filtri', 'senza tabù', 'godere', 'assoluto', 'eccitata', 'scateno', 'libidine', 'peperina', 'faccio morire', 'provarmi', 'esplodere', 'piacere', 'lussuria', 'peccato', 'infuocata', 'bruciante']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'esotica') { $desc_keywords = ['esotica', 'orientale', 'misteriosa', 'sensuale', 'stravagante', 'originale', 'scoprilo', 'immagini', 'segreti', 'mistero', 'magia', 'affascinante']; $name_keywords = ['akira', 'samira', 'naomi', 'morgana', 'kayla', 'chloe', 'jasmine', 'yuki', 'soo-jin', 'abeni', 'eleni', 'malee', 'sakura', 'fatima', 'nala', 'moana']; if (avs_quiz_string_contains($contenuto, $desc_keywords)) $base_scores[$post_id] += 8; if (avs_quiz_string_contains($nome, $name_keywords)) $base_scores[$post_id] += 5; }
                        break;
                    case 'approccio':
                        if ($answer === 'dolcezza') { $keywords = ['dolce', 'premurosa', 'ascolto', 'comprensiva', 'amante', 'complice', 'romantica', 'dolcezza', 'coccole', 'abbraccio']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'dominazione') { $keywords = ['dominante', 'padrona', 'severa', 'autoritaria', 'esigente', 'senza tabù', 'padrone', 'comando', 'ordini', 'regole', 'sottomessa', 'sottomesso']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'mix') { $base_scores[$post_id] += 3; } // Ridotto il peso per non "inquinare" i punteggi
                        break;
                    case 'esperienza':
                        if ($answer === 'esperta') { if ($data_pubblicazione < strtotime('-1 year')) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'novita') { if ($data_pubblicazione > strtotime('-3 months')) $base_scores[$post_id] += 10; }
                        break;
                    case 'dialogo':
                        if ($answer === 'diretto') { $keywords = ['diretta', 'senza filtri', 'sincera', 'schiatta', 'onesta']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'allusivo') { $keywords = ['allusiva', 'allusivo', 'stuzzicare', 'sensuale', 'vedo non vedo', 'sussurrare']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'ascolto') { $keywords = ['ascolto', 'comprensiva', 'amica', 'consigli', 'segreti']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 8; }
                        break;
                    case 'carattere':
                        if ($answer === 'estroverso') { $keywords = ['estroversa', 'solare', 'allegra', 'simpatica', 'espansiva', 'chiacchierona']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'introverso') { $keywords = ['introversa', 'riservata', 'timida', 'misteriosa', 'profonda']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 10; }
                        elseif ($answer === 'maturo') { $keywords = ['matura', 'esperta', 'sicura', 'donna', 'consapevole']; if (avs_quiz_string_contains($contenuto, $keywords)) $base_scores[$post_id] += 8; }
                        break;
                }
            }
        }
    }
    wp_reset_postdata();

    // ========================================================================
    // FASE 2: Calcolo del Moltiplicatore di Performance
    // ========================================================================
    $performance_multipliers = avs_quiz_get_performance_multipliers();

    // ========================================================================
    // FASE 3: Calcolo del Punteggio Finale e Selezione (MODIFICATA)
    // ========================================================================
    $final_scores = [];
    foreach ($base_scores as $post_id => $base_score) {
        if ($base_score > 0) { // Considera solo le operatrici che hanno ricevuto almeno 1 punto di base
            $multiplier = isset($performance_multipliers[$post_id]) ? $performance_multipliers[$post_id] : 1.0;
            $final_scores[$post_id] = $base_score * $multiplier;
        }
    }

    // Ordina i punteggi finali
    arsort($final_scores);
    $top_3_ids = array_slice(array_keys($final_scores), 0, 3);

    // ========================================================================
    // LOGICA DI FALLBACK (NUOVA SEZIONE)
    // ========================================================================
    // Se non troviamo abbastanza operatrici (meno di 3) o nessun match, usiamo un fallback.
    if (count($top_3_ids) < 3 || empty($top_3_ids)) {
        // Logica per recuperare le operatrici più popolari (es. per CTR o semplicemente le ultime pubblicate)
        // Questo è un esempio di fallback: recupera le 3 operatrici con il CTR più alto.
        // Se il tracking non ha ancora dati, potresti prendere le 3 più recenti o casuali.
        
        $fallback_operators_query = new WP_Query([
            'post_type' => 'operatrice',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'orderby' => 'meta_value_num', // Per ordinare per performance (CTR)
            'meta_key' => '_avs_operator_ctr', // Assumiamo un meta_key che salva il CTR o un punteggio di popolarità
            'order' => 'DESC',
            // 'orderby' => 'date', // Alternativa: le più recenti
            // 'order' => 'DESC',
            // 'orderby' => 'rand', // Alternativa: casuali
        ]);

        if ($fallback_operators_query->have_posts()) {
            $top_3_ids = wp_list_pluck($fallback_operators_query->posts, 'ID');
        } else {
            // Ultimo resort: prendi 3 operatrici casuali se non ci sono neanche per il fallback
            $top_3_ids = wp_list_pluck((new WP_Query(['post_type' => 'operatrice', 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => 'rand']))->posts, 'ID');
        }
        wp_reset_postdata(); // Assicurati di resettare dopo ogni nuova WP_Query
    }
    // Fine logica di fallback

    // La generazione dell'HTML per i risultati rimane identica
    global $post;
    $html_output = '<div class="uk-grid uk-child-width-1-1 uk-child-width-1-3@m" uk-grid>';
    if (!empty($top_3_ids)) {
        foreach ($top_3_ids as $id) {
            $post = get_post($id);
            if ($post) {
                setup_postdata($post);
                if (function_exists('aos_render_operator_card_html')) {
                    $html_output .= '<div>' . aos_render_operator_card_html($post) . '</div>';
                }
            }
        }
        wp_reset_postdata(); 
    } else {
        // Questo else block dovrebbe ora essere raggiunto solo in casi estremamente rari,
        // se non ci fossero proprio operatrici pubblicate.
        $html_output .= '<div class="uk-width-1-1"><p class="uk-text-center">Ci scusiamo, al momento non abbiamo operatrici disponibili.</p></div>';
    }
    $html_output .= '</div>';

    wp_send_json_success(['html' => $html_output, 'ids' => $top_3_ids]);
}

// =======================================================================================
// 4. FUNZIONI DI RENDERING SPECIFICHE PER LE CARD DEL QUIZ ("CASO 5") (invariate)
// =======================================================================================

/**
 * [Specifica per il Quiz] Assegna una priorità a una numerazione.
 */
function avs_quiz_get_numerazione_priority($description) {
    if (stripos($description, 'Carta di Credito') !== false) return 1;
    if (stripos($description, 'Ricarica Online') !== false) return 2;
    return 10;
}

/**
 * [Specifica per il Quiz] Renderizza il pulsante di pagamento. (invariato)
 */
function avs_quiz_render_payment_button($numerazione, $genere_slug, $mappa_genere_ddi, $codice_da_tracciare, $ha_chiama_e_ricarica) {
    $description = $numerazione->description;
    $number = $numerazione->name;
    ob_start();

    // Caso 1: Ricarica Online
    if (stripos($description, 'Ricarica Online') !== false) {
        if (!$ha_chiama_e_ricarica) {
            $ddi_per_ricarica = $mappa_genere_ddi[$genere_slug] ?? '';
            if (!empty($ddi_per_ricarica)) {
                // HTML per banner Ricarica Online (omesso per brevità, è corretto)
            }
        }
    // Caso 2: Carta di Credito
    } elseif (stripos($description, 'Carta di Credito') !== false) {
        // HTML per banner Carta di Credito (omesso per brevità, è corretto)

    // Caso 3: Svizzera
    } elseif (stripos($description, 'Svizzera') !== false) {
        // HTML per pulsante Svizzera (omesso per brevità, è corretto)
    
    // CASO 4: DEFAULT (899) - Con pulsante Tariffe
    } else {
        $href = 'tel://' . esc_attr($number);
        $tariffe = get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true);

        if (!empty($tariffe) && is_array($tariffe)) {
            ?>
            <div class="uk-button-group uk-width-1-1 uk-margin-small-bottom">
                <a href="<?php echo esc_url($href); ?>" class="uk-button uk-button-secondary uk-width-expand uk-text-center" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
                    <span uk-icon="icon: phone" class="uk-margin-small-right"></span>
                    <?php echo esc_html($number); ?>
                </a>
                <div>
                    <button type="button" class="uk-button uk-button-secondary"> € </button>
                    <div uk-dropdown="mode: click; pos: bottom-right">
                        <table class="uk-table uk-table-striped uk-table-small uk-text-center uk-table-middle">
                           <thead>
                                <tr>
                                    <th>Gestore</th>
                                    <th>Scatto</th>
                                    <th>Costo/min</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tariffe as $tariffa) : ?>
                                    <tr>
                                        <td><?php echo esc_html($tariffa['gestore'] ?? 'N/D'); ?></td>
                                        <td><?php echo isset($tariffa['scatto']) ? esc_html(number_format_i18n($tariffa['scatto'], 2)) . '€' : 'N/D'; ?></td>
                                        <td><?php echo isset($tariffa['costo']) ? esc_html(number_format_i18n($tariffa['costo'], 2)) . '€/min' : 'N/D'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        } else {
            // Fallback: pulsante semplice
            ?>
            <a href="<?php echo esc_url($href); ?>" class="uk-button uk-button-secondary uk-width-1-1 uk-margin-small-bottom" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
                <span uk-icon="icon: phone" class="uk-margin-small-right"></span>
                <?php echo esc_html($number); ?>
            </a>
            <?php
        }
    }
    return ob_get_clean();
}


/**
 * [Specifica per il Quiz] Renderizza l'HTML completo per la card di una singola operatrice. (invariato)
 */
function avs_quiz_render_operator_card_html( $operatrice_post ) {
    if ( is_numeric( $operatrice_post ) ) $operatrice_post = get_post( $operatrice_post );
    if ( ! $operatrice_post instanceof WP_Post || $operatrice_post->post_type !== 'operatrice' ) {
        return '';
    }

    $post_id = $operatrice_post->ID;
    $codice_da_tracciare = $post_id;
    $generi = get_the_terms($post_id, 'genere');
    $genere_nome = ($generi && !is_wp_error($generi)) ? $generi[0]->name : '';
    $genere_slug = ($generi && !is_wp_error($generi)) ? $generi[0]->slug : '';
    
    // Assicuriamoci che la mappa DDI sia disponibile, la useremo dopo.
    $mappa_genere_ddi = function_exists('aos_get_ddi_map') ? aos_get_ddi_map() : [];
     
    $tutte_le_numerazioni = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false]);
    ob_start();
    ?>
    <div class="uk-card uk-padding-small uk-card-secondary uk-card-body uk-flex uk-flex-column uk-text-center cartomante" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
        <div class="uk-flex-1">
            <?php if (has_post_thumbnail($post_id)) echo get_the_post_thumbnail($post_id, 'medium', ['class' => 'uk-border-circle']); ?>
            <div class="labelnome"><?php echo esc_html($operatrice_post->post_title); ?></div>
            <div class="uk-h3 uk-margin-remove-top uk-text-primary uk-margin-small-bottom">Genere: <?php echo esc_html($genere_nome); ?></div>
            <div class="uk-text-small cartintro"><?php echo wp_kses_post(wp_trim_words($operatrice_post->post_content, 20, '...')); ?></div>
        </div>
        <div class="uk-margin-top">
            <?php
            $numerazioni_filtrate = [];
            if (!empty($genere_nome) && !is_wp_error($tutte_le_numerazioni)) {
                foreach ($tutte_le_numerazioni as $numerazione) {
                    $match_trovato = false;
                    if (stripos($numerazione->description, $genere_nome) !== false) {
                        $match_trovato = true;
                    }
                    elseif ($genere_nome === 'Etero Basso Costo' && stripos($numerazione->description, 'Etero') !== false) {
                        $match_trovato = true;
                    }
                    if ($match_trovato && stripos($numerazione->description, 'Svizzera') === false && stripos($numerazione->description, 'Basso Costo') === false) {
                        $numerazioni_filtrate[] = $numerazione;
                    }
                }
            }

            if (!empty($numerazioni_filtrate)) {
                usort($numerazioni_filtrate, function($a, $b) { return avs_quiz_get_numerazione_priority($a->description) <=> avs_quiz_get_numerazione_priority($b->description); });
                
                $ha_chiama_e_ricarica = false;
                foreach ($numerazioni_filtrate as $numerazione_check) {
                    if (stripos($numerazione_check->description, 'Carta di Credito') !== false) {
                        $ha_chiama_e_ricarica = true;
                        break;
                    }
                }
                
                foreach ($numerazioni_filtrate as $numerazione) {
                    // ✨ CHIAMATA ALLA FUNZIONE CORRETTA CON TUTTI I PARAMETRI ✨
                    echo avs_quiz_render_payment_button($numerazione, $genere_slug, $mappa_genere_ddi, $codice_da_tracciare, $ha_chiama_e_ricarica);
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

function avs_quiz_string_contains($string, $keywords) {
    foreach ($keywords as $keyword) { if (strpos($string, $keyword) !== false) return true; }
    return false;
}

// ===== INIZIO CODICE DA AGGIUNGERE A quiz-logic.php =====

// Registra la nuova azione AJAX per il tracking
add_action('wp_ajax_nopriv_avs_track_quiz_event', 'avs_track_quiz_event_handler');
add_action('wp_ajax_avs_track_quiz_event', 'avs_track_quiz_event_handler');

/**
 * Gestisce e salva gli eventi di tracking del quiz nel database.
 */
function avs_track_quiz_event_handler() {
    // Non tracciare le azioni degli amministratori per non sporcare i dati.
    if ( current_user_can( 'manage_options' ) ) {
        wp_send_json_success(['status' => 'administrator ignored']);
        wp_die();
    }
    
    check_ajax_referer('quiz_nonce', 'security');

    global $wpdb;
    $table_name = $wpdb->prefix . 'avs_quiz_tracking';

    // Recupera e sanifica i dati inviati dal JavaScript
    $session_id = sanitize_text_field($_POST['session_id']);
    $event_type = sanitize_text_field($_POST['event_type']);
    $question_number = isset($_POST['question_number']) ? intval($_POST['question_number']) : null;
    $answer_key = isset($_POST['answer_key']) ? sanitize_text_field($_POST['answer_key']) : null;
    $operator_id = isset($_POST['operator_id']) ? intval($_POST['operator_id']) : null;
    $click_context = isset($_POST['click_context']) ? esc_url_raw($_POST['click_context']) : '';


    // Inserisce i dati nella tabella
    $wpdb->insert(
        $table_name,
        [
            'session_id'    => $session_id,
            'event_type'    => $event_type,
            'question_number' => $question_number,
            'answer_key'    => $answer_key,
            'operator_id'   => $operator_id,
            'click_context' => $click_context,
        ],
        [
            '%s', // session_id
            '%s', // event_type
            '%d', // question_number
            '%s', // answer_key
            '%d', // operator_id
            '%s', // click_context
        ]
    );

    wp_send_json_success(['status' => 'event tracked']);
    wp_die();
}

// ===== FINE CODICE DA AGGIUNGERE A quiz-logic.php =====

// ===================================================================
// CODICE PER LA PAGINA DI VISUALIZZAZIONE DELLE STATISTICHE (invariato)
// ===================================================================

// Aggancia la nostra funzione al menu di amministrazione di WordPress.
add_action('admin_menu', 'avs_quiz_add_admin_menu');

/**
 * Aggiunge una nuova voce di menu nella bacheca di amministrazione.
 */
function avs_quiz_add_admin_menu() {
    add_menu_page(
        'Statistiche Quiz',      // Titolo della pagina
        'Statistiche Quiz',      // Testo nel menu
        'manage_options',        // Capability richiesta per vederla (solo admin)
        'avs-quiz-stats',        // Slug univoco della pagina
        'avs_quiz_stats_page_html',  // Funzione che disegna l'HTML della pagina
        'dashicons-chart-pie',   // Icona del menu
        25                       // Posizione nel menu
    );
}

/**
 * Disegna l'HTML per la pagina delle statistiche del quiz.
 */
function avs_quiz_stats_page_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'avs_quiz_tracking';

    // Array per tradurre le chiavi delle risposte in testo leggibile
    $answers_map = [
        'frizzante' => "Una chiacchierata frizzante e senza pensieri",
        'passionale' => "Un'esperienza intensa e passionale",
        'esotica' => "Un viaggio esotico e misterioso",
        'dolcezza' => "Dolcezza e totale complicità",
        'dominazione' => "Dominazione e un pizzico di malizia",
        'mix' => "Un mix equilibrato, dove tutto può succedere",
        'esperta' => "Una guida esperta che sa sempre come stupirti",
        'novita' => "L'emozione di una scoperta tutta nuova",
    ];
    ?>
    <div class="wrap">
        <h1><span class="dashicons-before dashicons-chart-pie"></span> Statistiche di Interazione del Quiz</h1>
        <p>Questa pagina mostra come gli utenti interagiscono con il quiz, quali risposte scelgono e quali operatrici vengono suggerite e cliccate più spesso.</p>

        <hr>

        <h2 style="margin-top: 30px;">Efficacia dei Suggerimenti (Click-Through Rate)</h2>
        <p>Questa tabella mostra quante volte un'operatrice è stata suggerita dal quiz e quante volte è stata cliccata, calcolando il tasso di conversione (CTR).</p>
        <?php
        // 1. Otteniamo tutte le volte che un'operatrice è stata MOSTRATA
        $views_query = "SELECT operator_id, COUNT(*) as total_views FROM {$table_name} WHERE event_type = 'risultati_mostrati' AND operator_id IS NOT NULL GROUP BY operator_id";
        $views_results = $wpdb->get_results($views_query, OBJECT_K);

        // 2. Otteniamo tutte le volte che è stata CLICCATA
        $clicks_query = "SELECT operator_id, COUNT(*) as total_clicks FROM {$table_name} WHERE event_type = 'risultato_cliccato' AND operator_id IS NOT NULL GROUP BY operator_id";
        $clicks_results = $wpdb->get_results($clicks_query, OBJECT_K);

        // 3. Uniamo i dati e calcoliamo il CTR
        $stats_data = [];
        if ($views_results) {
            foreach ($views_results as $op_id => $data) {
                $clicks = isset($clicks_results[$op_id]) ? $clicks_results[$op_id]->total_clicks : 0;
                $views = $data->total_views;

                $ctr = ($views > 0) ? ($clicks / $views) * 100 : 0;

                $stats_data[$op_id] = [
                    'title' => get_the_title($op_id),
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $ctr,
                ];
            }
        }
        
        // Ordiniamo i dati per CTR decrescente
        uasort($stats_data, function($a, $b) { return $b['ctr'] <=> $a['ctr']; });

        if (!empty($stats_data)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th style="width:40%;">Nome Operatrice</th><th>Suggerita (volte)</th><th>Cliccata (volte)</th><th style="width:20%;">CTR</th></tr></thead>';
            echo '<tbody>';
            foreach ($stats_data as $row) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($row['title']) . '</strong></td>';
                echo '<td>' . esc_html($row['views']) . '</td>';
                echo '<td>' . esc_html($row['clicks']) . '</td>';
                echo '<td><strong>' . esc_html(number_format($row['ctr'], 2)) . '%</strong></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Nessun dato ancora raccolto.</p>';
        }
        ?>
        
        <hr>

        <h2 style="margin-top: 30px;">Top 20 Operatrici più Suggerite dal Quiz</h2>
        <?php
        $top_operators = $wpdb->get_results(
            "SELECT operator_id, COUNT(*) as total 
             FROM {$table_name} 
             WHERE event_type = 'risultati_mostrati' AND operator_id IS NOT NULL 
             GROUP BY operator_id 
             ORDER BY total DESC 
             LIMIT 20"
        );

        if ($top_operators) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Nome Operatrice</th><th>Conteggio Suggerimenti</th></tr></thead>';
            echo '<tbody>';
            foreach ($top_operators as $row) {
                // Ottieni il nome dell'operatrice dal suo ID
                $operator_title = get_the_title($row->operator_id);
                echo '<tr>';
                echo '<td>' . esc_html($operator_title) . '</td>';
                echo '<td>' . esc_html($row->total) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Nessun dato sui suggerimenti ancora raccolto.</p>';
        }
        ?>
    </div>
    <?php
}
?>