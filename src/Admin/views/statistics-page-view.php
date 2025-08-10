<div class="wrap">
    <h1>Statistiche dei Click</h1>

    <style>
        .statistics-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .stats-box { flex: 1 1 45%; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stats-box h2 { margin-top: 0; }
        .stats-box table { width: 100%; border-collapse: collapse; }
        .stats-box th, .stats-box td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .filter-form { background: #fff; padding: 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        .filter-form .date-filters, .filter-form .preset-filters, .filter-form .detailed-filters { display: flex; gap: 10px; align-items: center; }
        .preset-filters .button { font-size: 13px; }
        .detailed-stats-table { width: 100%; margin-top: 20px; }
        .detailed-stats-table th { text-align: left; }
        .pagination { margin-top: 15px; }
    </style>

    <div class="filter-form">
        <form method="get" id="statistics-filter-form">
            <input type="hidden" name="post_type" value="operatrice">
            <input type="hidden" name="page" value="aos-click-statistics">

            <div class="date-filters">
                <label for="filter_date_start"><span class="dashicons dashicons-calendar-alt"></span> Dal:</label>
                <input type="date" id="filter_date_start" name="filter_date_start" value="<?php echo esc_attr($selected_date_start); ?>" min="<?php echo esc_attr($first_available_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                <label for="filter_date_end"><span class="dashicons dashicons-calendar-alt"></span> Al:</label>
                <input type="date" id="filter_date_end" name="filter_date_end" value="<?php echo esc_attr($selected_date_end); ?>" min="<?php echo esc_attr($first_available_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
            </div>

            <div class="preset-filters">
                <span class="description">Periodo:</span>
                <button type="button" class="button" data-range="today">Oggi</button>
                <button type="button" class="button" data-range="yesterday">Ieri</button>
                <button type="button" class="button" data-range="last7days">Ultimi 7 Giorni</button>
                <button type="button" class="button" data-range="all">Dall'inizio</button>
            </div>

            <div class="detailed-filters" style="width: 100%; margin-top: 10px;">
                <label for="filter_operatrice"><span class="dashicons dashicons-admin-users"></span> Operatrice:</label>
                <select name="filter_operatrice" id="filter_operatrice">
                    <option value="">Tutte</option>
                    <?php foreach ($all_operatrici as $operatrice) : ?>
                        <option value="<?php echo esc_attr($operatrice->ID); ?>" <?php selected($selected_operatrice, $operatrice->ID); ?>><?php echo esc_html($operatrice->post_title); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filter_genere"><span class="dashicons dashicons-tag"></span> Genere:</label>
                <select name="filter_genere" id="filter_genere">
                    <option value="">Tutti</option>
                     <?php foreach ($all_generi as $genere) : ?>
                        <option value="<?php echo esc_attr($genere->term_id); ?>" <?php selected($selected_genere, $genere->term_id); ?>><?php echo esc_html($genere->name); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filter_numerazione"><span class="dashicons dashicons-phone"></span> Numerazione:</label>
                <select name="filter_numerazione" id="filter_numerazione">
                    <option value="">Tutte</option>
                     <?php foreach ($all_numerazioni as $numerazione) : ?>
                        <option value="<?php echo esc_attr($numerazione->term_id); ?>" <?php selected($selected_numerazione, $numerazione->term_id); ?>><?php echo esc_html($numerazione->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-primary"><span class="dashicons dashicons-filter"></span> Filtra</button>
            </div>
        </form>
    </div>

    <div class="statistics-container">
        <div class="stats-box">
            <h2><span class="dashicons dashicons-star-filled"></span> Top 5 Operatrici</h2>
            <table>
                <thead>
                    <tr>
                        <th>Operatrice</th>
                        <th>Click Totali</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_operatrici)) : ?>
                        <tr><td colspan="2">Nessun dato disponibile.</td></tr>
                    <?php else : ?>
                        <?php foreach ($top_operatrici as $item) : ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($item->post_id)); ?></td>
                                <td><?php echo esc_html($item->click_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stats-box">
            <h2><span class="dashicons dashicons-tag"></span> Top 5 Generi</h2>
            <table>
                <thead>
                    <tr>
                        <th>Genere</th>
                        <th>Click Totali</th>
                    </tr>
                </thead>
                <tbody>
                     <?php if (empty($top_generi)) : ?>
                        <tr><td colspan="2">Nessun dato disponibile.</td></tr>
                    <?php else : ?>
                        <?php foreach ($top_generi as $item) : ?>
                            <?php $term = get_term($item->term_id); ?>
                            <tr>
                                <td><?php echo esc_html($term->name); ?></td>
                                <td><?php echo esc_html($item->click_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stats-box" style="flex-basis: 100%;">
            <h2><span class="dashicons dashicons-chart-area"></span> Andamento Click per Operatrici</h2>
            <div class="chart-container">
                <canvas id="operatriciChart"></canvas>
            </div>
        </div>

        <div class="stats-box" style="flex-basis: 100%;">
            <h2><span class="dashicons dashicons-chart-bar"></span> Andamento Click per Generi</h2>
            <div class="chart-container">
                <canvas id="generiChart"></canvas>
            </div>
        </div>

        <div class="stats-box" style="flex-basis: 100%;">
            <h2><span class="dashicons dashicons-phone"></span> Andamento Click per Numerazioni</h2>
            <div class="chart-container">
                <canvas id="numerazioniChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart rendering is now handled by js/admin-statistics.js -->

<div class="stats-box" style="flex-basis: 100%; margin-top: 20px;">
    <h2><span class="dashicons dashicons-list-view"></span> Dettaglio Click (<?php echo esc_html($total_clicks); ?> totali)</h2>
    <table class="wp-list-table widefat striped detailed-stats-table">
        <thead>
            <tr>
                <th class="sorted <?php echo esc_attr($_GET['order'] ?? 'desc'); ?>"><?php echo $sortable_links['click_timestamp']; ?></th>
                <th><?php echo $sortable_links['operatrice_name']; ?></th>
                <th><?php echo $sortable_links['genere_name']; ?></th>
                <th><?php echo $sortable_links['numerazione_name']; ?></th>
                <th><?php echo $sortable_links['click_context']; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($detailed_clicks)) : ?>
                <tr><td colspan="5">Nessun click registrato in questo periodo.</td></tr>
            <?php else : ?>
                <?php foreach ($detailed_clicks as $click) : ?>
                    <tr>
                        <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($click->click_timestamp))); ?></td>
                        <td><?php echo esc_html($click->operatrice_name ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($click->genere_name ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($click->numerazione_name ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($click->click_context); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php echo $pagination; ?>
    </div>
</div>
