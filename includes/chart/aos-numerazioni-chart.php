<?php
// includes/chart/aos-numerazioni-chart.php
// Questo file genera il grafico delle top 5 numerazioni.
// Autore: Emanuele Tolomei

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Funzione per generare e visualizzare il markup del canvas per il grafico delle top 5 numerazioni.
 * Restituisce i dati del grafico per l'inizializzazione JavaScript.
 *
 * @param string $query_date_start La data di inizio del periodo di query.
 * @param string $query_date_end La data di fine del periodo di query.
 * @return array Un array contenente 'labels' e 'datasets' per Chart.js.
 */
function aos_get_numerazioni_chart_data( $query_date_start, $query_date_end ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aos_click_tracking';

    // Query per identificare le top 5 numerazioni nel periodo
    $top_5_numerazioni_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT numerazione_term_id
         FROM {$table_name}
         WHERE numerazione_term_id > 0
           AND click_timestamp BETWEEN %s AND %s
         GROUP BY numerazione_term_id
         ORDER BY COUNT(id) DESC
         LIMIT 5",
        $query_date_start,
        $query_date_end
    ));

    $chart_data = [];
    $labels = [];

    if (!empty($top_5_numerazioni_ids)) {
        // Genera tutte le date nel range selezionato per le etichette dell'asse X
        $current_date = strtotime($query_date_start);
        $end_date = strtotime($query_date_end);
        while ($current_date <= $end_date) {
            $labels[] = date('Y-m-d', $current_date);
            $current_date = strtotime('+1 day', $current_date);
        }

        foreach ($top_5_numerazioni_ids as $numerazione_id) {
            $numerazione_term = get_term($numerazione_id, 'numerazione');
            if ($numerazione_term && !is_wp_error($numerazione_term)) {
                $numerazione_name = $numerazione_term->name;

                // Query per ottenere i click giornalieri per questa numerazione
                $daily_clicks = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(click_timestamp) as click_date, COUNT(id) as daily_count
                     FROM {$table_name}
                     WHERE numerazione_term_id = %d
                       AND click_timestamp BETWEEN %s AND %s
                     GROUP BY click_date
                     ORDER BY click_date ASC",
                    $numerazione_id,
                    $query_date_start,
                    $query_date_end
                ), OBJECT_K);

                $data_points = [];
                foreach ($labels as $date) {
                    $data_points[] = isset($daily_clicks[$date]) ? (int)$daily_clicks[$date]->daily_count : 0;
                }

                $chart_data[] = [
                    'label' => esc_html($numerazione_name),
                    'data' => $data_points,
                    'borderColor' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)), // Colore esadecimale random
                    'backgroundColor' => 'rgba(0, 0, 0, 0)', // Linee senza riempimento
                    'fill' => false,
                    'tension' => 0.4
                ];
            }
        }
    }
    
    // Ritorna i dati direttamente
    return ['labels' => $labels, 'datasets' => $chart_data];
}

// Stampa il markup del canvas.
// Questa funzione sarà chiamata da admin-statistics-page.php
function aos_render_numerazioni_chart_canvas($chart_data) {
    ?>
    <div class="aos-chart-container">
        <?php if (empty($chart_data['datasets'])) : ?>
            <p>Nessun dato disponibile per il grafico delle top 5 numerazioni nel periodo selezionato.</p>
        <?php else : ?>
            <canvas id="aosNumerazioniClicksChart" style="min-height: 350px; max-height: 600px; width: 100%;"></canvas>
        <?php endif; ?>
    </div>
    <?php
}