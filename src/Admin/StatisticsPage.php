<?php

namespace AvsOperatorStatus\Admin;

/**
 * Class StatisticsPage
 *
 * Handles the creation and rendering of the click statistics admin page.
 */
class StatisticsPage {
    private ?array $stats_data = null;

    /**
     * Registers all hooks for the statistics page.
     */
    public function register() {
        add_action( 'admin_menu', [ $this, 'add_statistics_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Enqueues scripts and styles for the statistics page.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_assets( string $hook ) {
        if ( 'operatrice_page_aos-click-statistics' !== $hook ) {
            return;
        }

        $this->load_stats_data();

        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.0', true );
        wp_enqueue_script(
            'aos-admin-statistics',
            AOS_PLUGIN_URL . 'js/admin-statistics.js',
            [ 'chart-js', 'wp-i18n' ],
            AOS_VERSION,
            true
        );

        $chart_data = [
            'operatrici' => $this->stats_data['operatrici_chart_data'],
            'generi' => $this->stats_data['generi_chart_data'],
            'numerazioni' => $this->stats_data['numerazioni_chart_data'],
        ];
        wp_localize_script('aos-admin-statistics', 'aosStatisticsData', $chart_data);
    }

    /**
     * Adds the submenu page under the "Operatrici" CPT menu.
     */
    public function add_statistics_page() {
        add_submenu_page(
            'edit.php?post_type=operatrice',
            'Statistiche Click',
            'Statistiche Click',
            'manage_options',
            'aos-click-statistics',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Loads all the necessary data for the statistics page.
     */
    private function load_stats_data() {
        if ($this->stats_data !== null) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';

        $today = date('Y-m-d');
        $first_click_date_db = $wpdb->get_var("SELECT MIN(click_timestamp) FROM {$table_name}");
        $first_available_date = $first_click_date_db ? date('Y-m-d', strtotime($first_click_date_db)) : $today;

        $selected_date_start = $_GET['filter_date_start'] ?? $first_available_date;
        $selected_date_end = $_GET['filter_date_end'] ?? $today;

        $query_date_start = $selected_date_start . ' 00:00:00';
        $query_date_end = $selected_date_end . ' 23:59:59';

        $clicked_term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT numerazione_term_id FROM {$table_name} WHERE numerazione_term_id > 0 AND click_timestamp BETWEEN %s AND %s", $query_date_start, $query_date_end ) );

        $this->stats_data = [
            'selected_date_start' => $selected_date_start,
            'selected_date_end' => $selected_date_end,
            'first_available_date' => $first_available_date,
            'top_operatrici' => $this->get_top_operatrici($query_date_start, $query_date_end),
            'top_generi' => $this->get_top_generi($query_date_start, $query_date_end),
            'numerazioni_chart_data' => $this->get_numerazioni_chart_data($query_date_start, $query_date_end),
            'operatrici_chart_data' => $this->get_operatrici_chart_data($query_date_start, $query_date_end),
            'generi_chart_data' => $this->get_generi_chart_data($query_date_start, $query_date_end),
            'filterable_terms' => !empty($clicked_term_ids) ? get_terms(['taxonomy' => 'numerazione', 'include' => $clicked_term_ids, 'hide_empty' => false, 'orderby' => 'name']) : [],
        ];
    }

    /**
     * Renders the HTML for the statistics page.
     */
    public function render_page() {
        $this->load_stats_data();
        extract($this->stats_data);
        require_once AOS_PLUGIN_PATH . 'src/Admin/views/statistics-page-view.php';
    }

    private function get_top_operatrici(string $start_date, string $end_date): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        return $wpdb->get_results($wpdb->prepare("SELECT post_id, COUNT(id) as click_count FROM {$table_name} WHERE post_id > 0 AND click_timestamp BETWEEN %s AND %s GROUP BY post_id ORDER BY click_count DESC, MAX(click_timestamp) DESC LIMIT 5", $start_date, $end_date));
    }

    private function get_top_generi(string $start_date, string $end_date): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        return $wpdb->get_results($wpdb->prepare("SELECT t.term_id, COUNT(c.id) as click_count FROM {$table_name} AS c INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'genere' AND t.name NOT LIKE %s AND c.click_timestamp BETWEEN %s AND %s GROUP BY t.term_id ORDER BY click_count DESC, t.name ASC LIMIT 5", '%Basso Costo%', $start_date, $end_date));
    }

    private function get_numerazioni_chart_data(string $start_date, string $end_date): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        $top_ids = $wpdb->get_col($wpdb->prepare("SELECT numerazione_term_id FROM {$table_name} WHERE numerazione_term_id > 0 AND click_timestamp BETWEEN %s AND %s GROUP BY numerazione_term_id ORDER BY COUNT(id) DESC LIMIT 5", $start_date, $end_date));
        return $this->generate_chart_data_for_ids($top_ids, 'numerazione', $start_date, $end_date);
    }

    private function get_operatrici_chart_data(string $start_date, string $end_date): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        $top_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$table_name} WHERE post_id > 0 AND click_timestamp BETWEEN %s AND %s GROUP BY post_id ORDER BY COUNT(id) DESC LIMIT 5", $start_date, $end_date));
        return $this->generate_chart_data_for_ids($top_ids, 'operatrice', $start_date, $end_date);
    }

    private function get_generi_chart_data(string $start_date, string $end_date): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        $top_ids = $wpdb->get_col($wpdb->prepare("SELECT t.term_id FROM {$table_name} AS c INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'genere' AND t.name NOT LIKE %s AND c.click_timestamp BETWEEN %s AND %s GROUP BY t.term_id ORDER BY COUNT(c.id) DESC LIMIT 5", '%Basso Costo%', $start_date, $end_date));
        return $this->generate_chart_data_for_ids($top_ids, 'genere', $start_date, $end_date);
    }

    private function generate_chart_data_for_ids(array $top_ids, string $type, string $start_date, string $end_date): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aos_click_tracking';
        $chart_data = [];
        $labels = [];

        if (empty($top_ids)) {
            return ['labels' => [], 'datasets' => []];
        }

        $current_date = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        while ($current_date <= $end_timestamp) {
            $labels[] = date('Y-m-d', $current_date);
            $current_date = strtotime('+1 day', $current_date);
        }

        foreach ($top_ids as $id) {
            $name = ($type === 'operatrice') ? get_the_title($id) : get_term($id)->name;
            if (!$name) continue;

            $daily_clicks_query = '';
            if($type === 'genere') {
                 $daily_clicks_query = $wpdb->prepare("SELECT DATE(c.click_timestamp) as click_date, COUNT(c.id) as daily_count FROM {$table_name} AS c INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = 'genere' AND tt.term_id = %d AND c.click_timestamp BETWEEN %s AND %s GROUP BY click_date ORDER BY click_date ASC", $id, $start_date, $end_date);
            } else {
                $column = ($type === 'operatrice') ? 'post_id' : 'numerazione_term_id';
                $daily_clicks_query = $wpdb->prepare("SELECT DATE(click_timestamp) as click_date, COUNT(id) as daily_count FROM {$table_name} WHERE {$column} = %d AND click_timestamp BETWEEN %s AND %s GROUP BY click_date ORDER BY click_date ASC", $id, $start_date, $end_date);
            }

            $daily_clicks = $wpdb->get_results($daily_clicks_query, OBJECT_K);

            $data_points = [];
            foreach ($labels as $date) {
                $data_points[] = $daily_clicks[$date]->daily_count ?? 0;
            }

            $chart_data[] = [
                'label' => esc_html($name),
                'data' => $data_points,
                'borderColor' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)),
                'fill' => false,
                'tension' => 0.4
            ];
        }
        return ['labels' => $labels, 'datasets' => $chart_data];
    }
}
