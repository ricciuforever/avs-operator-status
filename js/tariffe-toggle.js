// js/tariffe-toggle.js - VERSIONE FINALE CON LOGICA TARIFFE UNIFICATE E COLONNA SCATTO CONDIZIONALE

jQuery(document).ready(function($) {
    'use strict';

    if (typeof aos_tariffe_map === 'undefined' || $.isEmptyObject(aos_tariffe_map)) {
        return;
    }

    // Seleziona tutti i pulsanti generati dai nostri shortcode
    $('.aos-operators-grid a.uk-button, .single-operatrice a.uk-button').each(function() {
        const mainButton = $(this);
        const href = mainButton.attr('href');
        if (!href) return;

        let numeroPulito = '';
        if (href.includes('r=pr_cc/CCrecharge4')) {
            const ddiMatch = href.match(/ddi=([0-9\.]+)/);
            if (ddiMatch && ddiMatch[1]) { numeroPulito = ddiMatch[1].replace(/\./g, ''); }
        } else if (href.startsWith('tel')) {
            numeroPulito = href.replace(/[^0-9]/g, '');
            if (numeroPulito.startsWith('39') && numeroPulito.length > 10) {
                numeroPulito = numeroPulito.substring(2);
            }
        }

        // Controlla se questo numero ha tariffe associate
        if (aos_tariffe_map.hasOwnProperty(numeroPulito)) {

            if (mainButton.data('tariffe-applied')) return;
            mainButton.data('tariffe-applied', true);

            const tariffe = aos_tariffe_map[numeroPulito];

            // 1. Determina la valuta e lo stile del pulsante
            let currencySymbol = href.includes('+41') ? 'CHF' : '€';
            let buttonStyle = 'uk-button-default';
            if (mainButton.hasClass('uk-button-default')) {
                buttonStyle = 'uk-button-default';
            } else if (mainButton.hasClass('uk-button-default')) {
                buttonStyle = 'uk-button-default';
            }
            
            // 2. Crea il nuovo pulsante "Tariffe"
            const tariffButton = $('<button type="button" class="uk-button ' + buttonStyle + '"> ' + currencySymbol + ' </button>');

            // --- NUOVA LOGICA COLONNA SCATTO ---
            // Controlla se almeno una tariffa ha uno scatto > 0. Se no, la colonna verrà nascosta.
            const mostraColonnaScatto = tariffe.some(t => parseFloat(t.scatto) > 0);

            // 3. Crea l'intestazione della tabella in modo condizionale
            let tableHtml = '<table class="uk-table uk-table-striped uk-table-small uk-text-center"><thead><tr><th>Gestore</th>';
            if (mostraColonnaScatto) {
                tableHtml += '<th>Scatto</th>';
            }
            tableHtml += '<th>Costo/min</th></tr></thead><tbody>';

            // Controlla se c'è solo una tariffa o se tutte le tariffe sono uguali
            const primaTariffa = tariffe[0];
            const tutteTariffeUguali = tariffe.every(t => t.scatto === primaTariffa.scatto && t.importo === primaTariffa.importo);

            if (tariffe.length === 1 || tutteTariffeUguali) {
                // Mostra una riga singola "Da qualsiasi gestore"
                let scatto = parseFloat(primaTariffa.scatto).toFixed(2);
                let importo = parseFloat(primaTariffa.importo).toFixed(2);
                
                tableHtml += '<tr><td>Da qualsiasi gestore</td>';
                if (mostraColonnaScatto) {
                    tableHtml += '<td>' + scatto + currencySymbol + '</td>';
                }
                tableHtml += '<td>' + importo + currencySymbol + '/min</td></tr>';

            } else {
                // Altrimenti, mostra tutte le tariffe distinte
                tariffe.forEach(function(tariffa) {
                    let scatto = parseFloat(tariffa.scatto).toFixed(2);
                    let importo = parseFloat(tariffa.importo).toFixed(2);

                    tableHtml += '<tr><td>' + tariffa.operatore.charAt(0).toUpperCase() + tariffa.operatore.slice(1) + '</td>';
                    if (mostraColonnaScatto) {
                        tableHtml += '<td>' + scatto + currencySymbol + '</td>';
                    }
                    tableHtml += '<td>' + importo + currencySymbol + '/min</td></tr>';
                });
            }
            
            tableHtml += '</tbody></table>';

            const dropdownContent = $('<div />', { 'uk-dropdown': 'mode: click; pos: bottom-right' })
                .addClass('uk-card uk-card-body uk-card-default uk-padding-small')
                .css('width', 'auto') // Larghezza automatica per adattarsi al contenuto
                .html(tableHtml);

            // 4. Crea il contenitore per il nuovo pulsante e il suo dropdown
            const tariffToggleContainer = $('<div />').append(tariffButton).append(dropdownContent);

            // 5. Rendi il pulsante principale più flessibile
            mainButton.addClass('uk-width-expand uk-text-center');

            // 6. Crea il "Button Group" e assembla la struttura finale
            const buttonGroup = $('<div class="uk-button-group uk-width-1-1 uk-margin-small-bottom"></div>');
            mainButton.wrap(buttonGroup).after(tariffToggleContainer);
        }
    });
});