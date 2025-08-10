<?php
// includes/admin-statistics-page.php - VERSIONE CON SCROLL ALL'INIZIO DEL FORM

if ( ! defined( 'ABSPATH' ) ) exit;

// Includi i file dei grafici dedicati
require_once plugin_dir_path(__FILE__) . 'chart/aos-numerazioni-chart.php';
require_once plugin_dir_path(__FILE__) . 'chart/aos-operatrici-chart.php';
require_once plugin_dir_path(__FILE__) . 'chart/aos-generi-chart.php';

add_action('admin_menu', 'aos_add_statistics_page');
function aos_add_statistics_page() {
    add_submenu_page('edit.php?post_type=operatrice', 'Statistiche Click', 'Statistiche Click', 'manage_options', 'aos-click-statistics', 'aos_render_statistics_page_html');
}

/**
 * Renderizza l'HTML e la logica della pagina delle statistiche.
 */
function aos_render_statistics_page_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aos_click_tracking';

    // --- Gestione Filtri di Data ---
    $today = date('Y-m-d');
    
    // Trova la data del primo click registrato
    $first_click_date_db = $wpdb->get_var("SELECT MIN(click_timestamp) FROM {$table_name}");
    $first_available_date = $first_click_date_db ? date('Y-m-d', strtotime($first_click_date_db)) : $today;

    $selected_date_start = isset($_GET['filter_date_start']) ? sanitize_text_field($_GET['filter_date_start']) : $first_available_date;
    $selected_date_end = isset($_GET['filter_date_end']) ? sanitize_text_field($_GET['filter_date_end']) : $today;
    $selected_numerazione = isset($_GET['filter_numerazione']) ? intval($_GET['filter_numerazione']) : 0;
    
    // Filtri per analisi incrociata (Operatrice, Genere, Numerazione, Contesto)
    $selected_operatrice_analysis = isset($_GET['filter_operatrice_analysis']) ? intval($_GET['filter_operatrice_analysis']) : 0;
    $selected_genere_analysis = isset($_GET['filter_genere_analysis']) ? intval($_GET['filter_genere_analysis']) : 0;
    $selected_numerazione_analysis = isset($_GET['filter_numerazione_analysis']) ? intval($_GET['filter_numerazione_analysis']) : 0;
    $selected_context_analysis = isset($_GET['filter_context_analysis']) ? urldecode(sanitize_text_field($_GET['filter_context_analysis'])) : '';


    $base_url = admin_url('edit.php?post_type=operatrice&page=aos-click-statistics');
    $date_presets = [
        'Oggi' => ['start' => date('Y-m-d'), 'end' => date('Y-m-d')],
        'Ieri' => ['start' => date('Y-m-d', strtotime('-1 day')), 'end' => date('Y-m-d', strtotime('-1 day'))],
        'Ultimi 7 Giorni' => ['start' => date('Y-m-d', strtotime('-6 days')), 'end' => date('Y-m-d')],
        'Questo Mese' => ['start' => date('Y-m-01'), 'end' => date('Y-m-t')],
        'Mese Scorso' => ['start' => date('Y-m-01', strtotime('first day of last month')), 'end' => date('Y-m-t', strtotime('last day of last month'))],
        'Dall\'inizio' => ['start' => $first_available_date, 'end' => $today],
    ];
    $query_date_start = $selected_date_start . ' 00:00:00';
    $query_date_end = $selected_date_end . ' 23:59:59';
    
    // --- Query per la Dashboard ---
    $top_operatrici = $wpdb->get_results($wpdb->prepare(
    "SELECT post_id, COUNT(id) as click_count 
     FROM {$table_name} 
     WHERE post_id > 0 
       AND click_timestamp BETWEEN %s AND %s 
     GROUP BY post_id 
     ORDER BY click_count DESC, MAX(click_timestamp) DESC 
     LIMIT 5",
    $query_date_start,
    $query_date_end
));

    $excluded_genres_pattern = '%Basso Costo%'; 
    $top_generi = $wpdb->get_results($wpdb->prepare(
    "SELECT t.term_id, COUNT(c.id) as click_count
     FROM {$table_name} AS c
     INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
     INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
     INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
     WHERE tt.taxonomy = 'genere'
       AND t.name NOT LIKE %s
       AND c.click_timestamp BETWEEN %s AND %s
     GROUP BY t.term_id
     ORDER BY click_count DESC, t.name ASC
     LIMIT 5",
    $excluded_genres_pattern,
    $query_date_start,
    $query_date_end
));
    
    // --- Dropdown dinamico (invariato) ---
    $clicked_term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT numerazione_term_id FROM {$table_name} WHERE numerazione_term_id > 0 AND click_timestamp BETWEEN %s AND %s", $query_date_start, $query_date_end ) );
    $filterable_terms = !empty($clicked_term_ids) ? get_terms(['taxonomy' => 'numerazione', 'include' => $clicked_term_ids, 'hide_empty' => false, 'orderby' => 'name']) : [];
    
    // --- Query per la Tabella dettagliata generale ---
    $query = "SELECT * FROM {$table_name} WHERE click_timestamp BETWEEN %s AND %s";
    $params = [$query_date_start, $query_date_end];
    if ($selected_numerazione > 0) { $query .= " AND numerazione_term_id = %d"; $params[] = $selected_numerazione; }
    $query .= " ORDER BY click_timestamp DESC";
    $results = $wpdb->get_results($wpdb->prepare($query, $params));

    // --- Ottieni i dati per tutti i grafici ---
    $numerazioni_chart_data = aos_get_numerazioni_chart_data($query_date_start, $query_date_end);
    $operatrici_chart_data = aos_get_operatrici_chart_data($query_date_start, $query_date_end);
    $generi_chart_data = aos_get_generi_chart_data($query_date_start, $query_date_end);

    // --- NUOVO: Dati per l'analisi incrociata Operatrice-Genere-Numerazione ---
    $operatrici_for_analysis_dropdown = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, COUNT(id) as click_count FROM {$table_name} 
         WHERE post_id > 0 AND click_timestamp BETWEEN %s AND %s 
         GROUP BY post_id 
         ORDER BY click_count DESC, post_id ASC", // Ordinamento primario per click, secondario per ID operatrice
        $query_date_start,
        $query_date_end
    ));

    // Ottieni tutti i generi che hanno click nel periodo per il dropdown
    // MODIFICA QUI: Aggiunta la condizione per escludere "Basso Costo"
    $generi_for_analysis_dropdown = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT tt.term_id, t.name
         FROM {$table_name} AS c
         INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
         INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
         INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'genere'
           AND t.name NOT LIKE %s -- Aggiunta questa riga
           AND c.click_timestamp BETWEEN %s AND %s
         ORDER BY t.name ASC",
        $excluded_genres_pattern, // Passa il pattern come parametro
        $query_date_start,
        $query_date_end
    ));

    // Ottieni tutte le numerazioni che hanno click nel periodo per il dropdown
    $numerazioni_for_analysis_dropdown = $wpdb->get_results($wpdb->prepare(
    "SELECT c.numerazione_term_id, t.name, COUNT(c.id) as click_count
     FROM {$table_name} AS c
     LEFT JOIN {$wpdb->terms} AS t ON c.numerazione_term_id = t.term_id
     WHERE c.numerazione_term_id > 0
       AND c.click_timestamp BETWEEN %s AND %s
     GROUP BY c.numerazione_term_id, t.name
     ORDER BY click_count DESC, t.name ASC",
    $query_date_start,
    $query_date_end
));

    // Ottieni tutti i contesti (URL) che hanno click nel periodo per il dropdown
    $contexts_for_analysis_dropdown = $wpdb->get_results($wpdb->prepare(
    "SELECT click_context, COUNT(id) as click_count
     FROM {$table_name}
     WHERE click_context != ''
       AND click_timestamp BETWEEN %s AND %s
     GROUP BY click_context
     ORDER BY click_count DESC, click_context ASC",
    $query_date_start,
    $query_date_end
));


    $operatrice_genere_numerazione_data = [];
    // Esegui la query incrociata solo se almeno un filtro è selezionato
    if ($selected_operatrice_analysis > 0 || $selected_genere_analysis > 0 || $selected_numerazione_analysis > 0 || !empty($selected_context_analysis)) {
        $analysis_query = "SELECT 
                                c.post_id,
                                tt_genere.term_id as genere_term_id, 
                                t_genere.name as genere_name,
                                c.numerazione_term_id,
                                t_numerazione.name as numerazione_name,
                                c.click_context,
                                COUNT(c.id) as click_count
                             FROM {$table_name} AS c
                             INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
                             INNER JOIN {$wpdb->term_taxonomy} AS tt_genere ON tr.term_taxonomy_id = tt_genere.term_taxonomy_id AND tt_genere.taxonomy = 'genere'
                             INNER JOIN {$wpdb->terms} AS t_genere ON tt_genere.term_id = t_genere.term_id
                             LEFT JOIN {$wpdb->terms} AS t_numerazione ON c.numerazione_term_id = t_numerazione.term_id
                             WHERE c.click_timestamp BETWEEN %s AND %s";
        
        $analysis_params = [$query_date_start, $query_date_end];

        if ($selected_operatrice_analysis > 0) {
            $analysis_query .= " AND c.post_id = %d";
            $analysis_params[] = $selected_operatrice_analysis;
        }
        if ($selected_genere_analysis > 0) {
            $analysis_query .= " AND tt_genere.term_id = %d";
            $analysis_params[] = $selected_genere_analysis;
        }
        if ($selected_numerazione_analysis > 0) {
            $analysis_query .= " AND c.numerazione_term_id = %d";
            $analysis_params[] = $selected_numerazione_analysis;
        }
        if (!empty($selected_context_analysis)) {
            $analysis_query .= " AND c.click_context = %s";
            $analysis_params[] = $selected_context_analysis;
        }

        $analysis_query .= " GROUP BY c.post_id, genere_term_id, numerazione_term_id, c.click_context";
        $analysis_query .= " ORDER BY click_count DESC, t_genere.name ASC, t_numerazione.name ASC, c.click_context ASC";

        $operatrice_genere_numerazione_data = $wpdb->get_results($wpdb->prepare($analysis_query, $analysis_params));
    }
    
    ?>
    <div class="wrap">
        <h1>Statistiche dei Click</h1>
        
        <div class="subsubsub" style="margin-bottom: 20px;">
            <ul class="subsubsub">
                <?php foreach ($date_presets as $label => $dates): 
                    $url_params = [
                        'filter_date_start' => $dates['start'], 
                        'filter_date_end' => $dates['end'], 
                        'filter_numerazione' => $selected_numerazione,
                        'filter_operatrice_analysis' => $selected_operatrice_analysis,
                        'filter_genere_analysis' => $selected_genere_analysis,
                        'filter_numerazione_analysis' => $selected_numerazione_analysis,
                        'filter_context_analysis' => urlencode($selected_context_analysis)
                    ];
                    $url = add_query_arg($url_params, $base_url);
                    $is_current = ($selected_date_start == $dates['start'] && $selected_date_end == $dates['end']);
                ?>
                    <li><a href="<?php echo esc_url($url); ?>" class="<?php echo $is_current ? 'current' : ''; ?>"><?php echo esc_html($label); ?></a> |</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form method="get" id="main-filter-form" style="clear:both; margin-top: 10px; padding: 15px; background: #f9f9f9; border: 1px solid #ccd0d4;">
            <input type="hidden" name="post_type" value="operatrice"><input type="hidden" name="page" value="aos-click-statistics">
            <input type="hidden" name="scroll_to" value="main-filter-form"> 

            <label for="filter_date_start">Dal:</label>
            <input type="date" id="filter_date_start" name="filter_date_start" value="<?php echo esc_attr($selected_date_start); ?>">
            <label for="filter_date_end">Al:</label>
            <input type="date" id="filter_date_end" name="filter_date_end" value="<?php echo esc_attr($selected_date_end); ?>">
            <label for="filter_numerazione">Numerazione:</label>
            <select name="filter_numerazione" id="filter_numerazione">
                <option value="0">Tutte le numerazioni</option>
                <?php foreach ($filterable_terms as $term) : ?>
                    <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected_numerazione, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" class="button" value="Filtra Periodo">
        </form>

        <h2 style="margin-top: 2em;">Dashboard del Periodo: <?php echo date_i18n('d F Y', strtotime($selected_date_start)); ?> - <?php echo date_i18n('d F Y', strtotime($selected_date_end)); ?></h2>
        <div id="dashboard-widgets-wrap">
            <div id="dashboard-widgets" class="metabox-holder">
                <div id="postbox-container-1" class="postbox-container">
                    <div class="meta-box-sortables">
                        <div class="postbox">
                            <h2 class="hndle"><span>Top 5 Operatrici</span></h2>
                            <div class="inside">
                                <?php if (empty($top_operatrici)) : ?><p>Nessun click registrato.</p><?php else : ?><ol style="margin:0 1.5em;"><?php foreach ($top_operatrici as $o) : ?><li><strong><?php echo esc_html(get_the_title($o->post_id)); ?>:</strong> <?php echo $o->click_count; ?> click</li><?php endforeach; ?></ol><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="postbox-container-2" class="postbox-container">
                    <div class="meta-box-sortables">
                        <div class="postbox">
                            <h2 class="hndle"><span>Top 5 Generi</span></h2>
                            <div class="inside">
                                <?php if (empty($top_generi)) : ?><p>Nessun click registrato.</p><?php else : ?><ol style="margin:0 1.5em;"><?php foreach ($top_generi as $g) : $gt = get_term($g->term_id); ?><li><strong><?php echo esc_html($gt->name); ?>:</strong> <?php echo $g->click_count; ?> click</li><?php endforeach; ?></ol><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr>
        <h2 class="nav-tab-wrapper">
            <a href="javascript:void(0);" class="nav-tab nav-tab-active" data-chart-id="numerazioni">Andamento Numerazioni</a>
            <a href="javascript:void(0);" class="nav-tab" data-chart-id="operatrici">Andamento Operatrici</a>
            <a href="javascript:void(0);" class="nav-tab" data-chart-id="generi">Andamento Generi</a>
        </h2>

        <div id="chart-numerazioni-content" class="aos-chart-content">
            <h3>Andamento Top 5 Numerazioni</h3>
            <?php aos_render_numerazioni_chart_canvas($numerazioni_chart_data); ?>
        </div>

        <div id="chart-operatrici-content" class="aos-chart-content" style="display:none;">
            <h3>Andamento Top 5 Operatrici</h3>
            <?php aos_render_operatrici_chart_canvas($operatrici_chart_data); ?>
        </div>

        <div id="chart-generi-content" class="aos-chart-content" style="display:none;">
            <h3>Andamento Top 5 Generi</h3>
            <?php aos_render_generi_chart_canvas($generi_chart_data); ?>
        </div>
        
        <hr>
        <h2>Analisi Performance Incrociata</h2>
        <form method="get" id="analysis-filter-form" style="padding: 15px; background: #f9f9f9; border: 1px solid #ccd0d4; margin-bottom: 20px;">
            <input type="hidden" name="post_type" value="operatrice">
            <input type="hidden" name="page" value="aos-click-statistics">
            <input type="hidden" name="filter_date_start" value="<?php echo esc_attr($selected_date_start); ?>">
            <input type="hidden" name="filter_date_end" value="<?php echo esc_attr($selected_date_end); ?>">
            <input type="hidden" name="scroll_to" value="analysis-filter-form">

            <label for="filter_operatrice_analysis">Operatrice:</label>
            <select name="filter_operatrice_analysis" id="filter_operatrice_analysis">
                <option value="0">Tutte le operatrici</option>
                <?php
                foreach ($operatrici_for_analysis_dropdown as $op_obj) {
                    $op_title = get_the_title($op_obj->post_id);
                    if ($op_title) {
                        $selected = selected($selected_operatrice_analysis, $op_obj->post_id, false);
                        echo '<option value="' . esc_attr($op_obj->post_id) . '" ' . $selected . '>' . esc_html($op_title) . '</option>';
                    }
                }
                ?>
            </select>

            <label for="filter_genere_analysis" style="margin-left: 15px;">Genere:</label>
            <select name="filter_genere_analysis" id="filter_genere_analysis">
                <option value="0">Tutti i generi</option>
                <?php
                foreach ($generi_for_analysis_dropdown as $gen_obj) {
                    $selected = selected($selected_genere_analysis, $gen_obj->term_id, false);
                    echo '<option value="' . esc_attr($gen_obj->term_id) . '" ' . $selected . '>' . esc_html($gen_obj->name) . '</option>';
                }
                ?>
            </select>

            <label for="filter_numerazione_analysis" style="margin-left: 15px;">Numerazione:</label>
            <select name="filter_numerazione_analysis" id="filter_numerazione_analysis">
                <option value="0">Tutte le numerazioni</option>
                <?php
                foreach ($numerazioni_for_analysis_dropdown as $num_obj) {
                    $numerazione_name = !empty($num_obj->name) ? $num_obj->name : 'Numerazione Cancellata (ID: ' . $num_obj->numerazione_term_id . ')';
                    $selected = selected($selected_numerazione_analysis, $num_obj->numerazione_term_id, false);
                    echo '<option value="' . esc_attr($num_obj->numerazione_term_id) . '" ' . $selected . '>' . esc_html($numerazione_name) . '</option>';
                }
                ?>
            </select>

            <label for="filter_context_analysis" style="margin-left: 15px;">Pagina (Contesto):</label>
            <select name="filter_context_analysis" id="filter_context_analysis" style="max-width: 250px;">
                <option value="">Tutte le pagine</option>
                <?php
                foreach ($contexts_for_analysis_dropdown as $context_obj) {
                    $context_url = esc_attr($context_obj->click_context);
                    $display_name = urldecode(basename($context_url));
                    if (empty($display_name)) {
                        $parsed_url = wp_parse_url($context_url);
                        $display_name = $parsed_url['host'] ?? $context_url;
                    }
                    $selected = selected($selected_context_analysis, $context_url, false);
                    echo '<option value="' . $context_url . '" ' . $selected . '>' . esc_html($display_name) . '</option>';
                }
                ?>
            </select>

            <input type="submit" class="button" value="Applica Filtri">
        </form>

        <?php 
        // Mostra la tabella solo se almeno un filtro di analisi è attivo
        if ($selected_operatrice_analysis > 0 || $selected_genere_analysis > 0 || $selected_numerazione_analysis > 0 || !empty($selected_context_analysis)) : 
        ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                <h3>Risultati Analisi</h3>
                <?php if (empty($operatrice_genere_numerazione_data)) : ?>
                    <p>Nessun click trovato con i filtri selezionati nel periodo.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Operatrice</th>
                                <th>Genere</th>
                                <th>Numerazione</th>
                                <th>Pagina (Contesto)</th>
                                <th>Click Registrati</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operatrice_genere_numerazione_data as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html(get_the_title($row->post_id)); ?></td>
                                    <td><?php echo esc_html($row->genere_name); ?></td>
                                    <td>
                                        <?php 
                                        $numerazione_term = get_term($row->numerazione_term_id, 'numerazione');
                                        if ($numerazione_term && !is_wp_error($numerazione_term)) {
                                            echo esc_html($numerazione_term->name);
                                        } else {
                                            echo '<span style="color: #dc3232; font-style: italic;">Numerazione Cancellata (ID: ' . esc_html($row->numerazione_term_id) . ')</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($row->click_context)) {
                                            $decoded_url = urldecode($row->click_context);
                                            $display_context = basename($decoded_url);
                                            if (empty($display_context)) { 
                                                $parsed_url = wp_parse_url($decoded_url);
                                                $display_context = $parsed_url['host'] ?? $decoded_url;
                                            }
                                            echo '<a href="' . esc_url($decoded_url) . '" target="_blank">' . esc_html($display_context) . '</a>';
                                        } else {
                                            echo '<em>Sconosciuto</em>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($row->click_count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <p style="margin-bottom: 20px;">Seleziona una o più opzioni nei filtri sopra per visualizzare le performance incrociate (Operatrice, Genere, Numerazione, Pagina) nel periodo corrente.</p>
        <?php endif; ?>

        <hr>
        <h2>Dettaglio Click del Periodo</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Contesto del Click</th><th>Numerazione Cliccata</th><th>Orario del Click</th></tr></thead>
            <tbody>
                <?php if (empty($results)) : ?><tr><td colspan="3">Nessun click trovato.</td></tr>
                <?php else : foreach ($results as $row) : $nt = get_term($row->numerazione_term_id); ?>
                    <tr>
                        <td><?php if ($row->post_id > 0) { echo '<strong>' . esc_html(get_the_title($row->post_id)) . '</strong>'; if (!empty($row->click_context)) { echo '<br><small>da: <a href="' . esc_url($row->click_context) . '" target="_blank">' . esc_html(urldecode(basename($row->click_context))) . '</a></small>'; } } elseif (!empty($row->click_context)) { echo '<a href="' . esc_url($row->click_context) . '" target="_blank">' . esc_html(urldecode($row->click_context)) . '</a>'; } else { echo '<em>Sconosciuto</em>'; } ?></td>
                        <td>
    <?php
    if ($nt) {
        echo esc_html($nt->name);
    } else {
        echo '<span style="color: #dc3232; font-style: italic;">Numerazione Cancellata (ID: ' . esc_html($row->numerazione_term_id) . ')</span>';
    }
    ?>
</td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($row->click_timestamp)); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var aosCharts = {}; // Store chart instances globally

        document.addEventListener('DOMContentLoaded', function() {
            var tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
            var chartContents = document.querySelectorAll('.aos-chart-content');

            // Data for Numerazioni Chart
            var numerazioniData = {
                labels: <?php echo json_encode($numerazioni_chart_data['labels']); ?>,
                datasets: <?php echo json_encode($numerazioni_chart_data['datasets']); ?>
            };

            // Data for Operatrici Chart
            var operatriciData = {
                labels: <?php echo json_encode($operatrici_chart_data['labels']); ?>,
                datasets: <?php echo json_encode($operatrici_chart_data['datasets']); ?>
            };

            // Data for Generi Chart
            var generiData = {
                labels: <?php echo json_encode($generi_chart_data['labels']); ?>,
                datasets: <?php echo json_encode($generi_chart_data['datasets']); ?>
            };

            // Common Chart Options
            var commonChartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    legend: {
                        position: 'top',
                    },
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Data'
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 15
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Numero di Click'
                        },
                        ticks: {
                            precision: 0
                        },
                        grace: '5%'
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        right: 15,
                        bottom: 10,
                        left: 15
                    }
                }
            };

            function aosInitChart(chartId, data, titleText) {
                var ctx = document.getElementById(chartId);
                if (ctx) {
                    if (aosCharts[chartId]) {
                        aosCharts[chartId].destroy(); // Destroy existing chart instance
                    }

                    if (data.datasets.length > 0) {
                        var options = JSON.parse(JSON.stringify(commonChartOptions));
                        options.plugins.title.text = titleText;
                        
                        aosCharts[chartId] = new Chart(ctx, {
                            type: 'line',
                            data: data,
                            options: options
                        });
                    }
                }
            }

            function aosSwitchChartTab(selectedTabId) {
                tabs.forEach(function(tab) {
                    tab.classList.remove('nav-tab-active');
                });
                chartContents.forEach(function(content) {
                    content.style.display = 'none';
                });

                var activeTab = document.querySelector('[data-chart-id="' + selectedTabId + '"]');
                if (activeTab) {
                    activeTab.classList.add('nav-tab-active');
                }
                
                var targetContentId = 'chart-' + selectedTabId + '-content';
                var targetContent = document.getElementById(targetContentId);
                if (targetContent) {
                    targetContent.style.display = 'block';

                    // Initialize or update the chart when its tab is active
                    if (selectedTabId === 'numerazioni') {
                        aosInitChart('aosNumerazioniClicksChart', numerazioniData, 'Andamento Giornaliero Click Top 5 Numerazioni');
                    } else if (selectedTabId === 'operatrici') {
                        aosInitChart('aosOperatriciClicksChart', operatriciData, 'Andamento Giornaliero Click Top 5 Operatrici');
                    } else if (selectedTabId === 'generi') {
                        aosInitChart('aosGeneriClicksChart', generiData, 'Andamento Giornaliero Click Top 5 Generi');
                    }
                }
            }

            // Add event listener to tabs using the data-chart-id attribute
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    aosSwitchChartTab(this.dataset.chartId);
                });
            });

            // Initialize the default chart on page load
            aosSwitchChartTab('numerazioni');

            // Scroll to the specified element after page load/refresh
            var scrollToId = new URLSearchParams(window.location.search).get('scroll_to');
            if (scrollToId) {
                var targetElement = document.getElementById(scrollToId);
                if (targetElement) {
                    // Use a slight delay to ensure all elements are rendered
                    setTimeout(function() {
                        targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100); 
                }
            }
        });
    </script>
    <style>
        /* Stili per il tab switcher */
        .nav-tab-wrapper {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccd0d4;
            padding-bottom: 0;
            line-height: 1.5;
        }
        .nav-tab {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px 0 0;
            border: 1px solid transparent;
            border-bottom: none;
            background: #f0f0f0;
            color: #555;
            text-decoration: none;
            box-sizing: border-box;
            border-radius: 4px 4px 0 0;
            transition: background 0.3s, color 0.3s;
            cursor: pointer;
        }
        .nav-tab:hover {
            background: #e0e0e0;
            color: #222;
        }
        .nav-tab-active {
            background: #fff;
            border-color: #ccd0d4;
            border-bottom-color: #fff;
            color: #000;
        }
        .aos-chart-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            margin-bottom: 20px;
        }
        /* Stile per i select nel filtro incrociato se diventano troppi */
        #filter_operatrice_analysis,
        #filter_genere_analysis,
        #filter_numerazione_analysis,
        #filter_context_analysis {
            vertical-align: middle;
            margin-bottom: 10px; /* Spazio sotto i select */
            max-width: 250px; /* Limita la larghezza per evitare overflow */
            width: 100%; /* Rende responsivo */
            display: inline-block; /* Per allineamento orizzontale */
        }
        @media (min-width: 768px) { /* Ritorna a display inline per schermi più grandi */
            #filter_operatrice_analysis,
            #filter_genere_analysis,
            #filter_numerazione_analysis,
            #filter_context_analysis {
                width: auto;
                margin-right: 10px; /* Spazio tra i select */
            }
            /* .form-row è solo un esempio per raggruppare visivamente, non l'ho aggiunto nel codice attuale */
            /* .form-row { 
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
            } */
        }
    </style>
    <?php
}