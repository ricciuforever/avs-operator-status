// js/main-script.js - SCRIPT UNIFICATO FINALE

jQuery(document).ready(function($) {
    'use strict';
    if (typeof aos_params === 'undefined') return;

    // --- FUNZIONE HELPER PER IL WRAPPER ---
    // Garantisce che ogni pulsante sia avvolto una sola volta da un div contenitore.
    function ensureWrapper(link) {
        let button = link.closest('.uk-button');
        if (!button.length) return null;
        if (button.parent().is('.promo-wrapper, .uk-button-group')) {
            return button.parent();
        } else {
            button.wrap('<div class="promo-wrapper"></div>');
            return button.parent();
        }
    }

    // --- LOGICA ETICHETTE E TOGGLE ---
    // Questa funzione viene eseguita per ogni pulsante e applica le etichette in ordine.
    function applyLabelsAndToggles() {
        $('.aos-operators-grid a.uk-button, .single-operatrice a.uk-button, .entry-content a.uk-button').each(function() {
            const link = $(this);
            const href = link.attr('href');
            if (!href || link.data('labels-applied')) return;

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
            if (numeroPulito === '') return;

            link.data('labels-applied', true); // Marca il pulsante come processato

            // 1. Applica etichetta PROMO
            if (aos_params.promo_list && aos_params.promo_list.hasOwnProperty(numeroPulito)) {
                let wrapper = ensureWrapper(link);
                if (wrapper) {
                    const promoInfo = aos_params.promo_list[numeroPulito];
                    const promoLabel = $('<a />', {'class': 'uk-label uk-light uk-background-primary promo-label', 'text': 'Promo!'});
                    const dropdownContent = $('<div />', {'uk-dropdown': 'mode: click; pos: top-right'}).addClass('uk-card uk-card-body uk-background-primary uk-padding-small').css('width', '280px').append($('<p />').html(promoInfo.messaggio.replace(/\n/g, '<br>')));
                    $('<div />', {'class': 'promo-toggle-container'}).append(promoLabel).append(dropdownContent).prependTo(wrapper);
                }
            }

            // 2. Applica etichetta BASSO COSTO
            if (aos_params.basso_costo_list && aos_params.basso_costo_list.includes(numeroPulito)) {
                let wrapper = ensureWrapper(link);
                if (wrapper && wrapper.find('.basso-costo-label').length === 0) {
                    const bassoCostoLabel = $('<span />', {'class': 'uk-label uk-background-success basso-costo-label', 'text': 'Basso Costo'});
                    wrapper.prepend(bassoCostoLabel);
                }
            }

            /* --- INIZIO BLOCCO DA COMMENTARE ---
               Questa sezione è ora gestita dal popup generato dal PHP.
               La commentiamo per evitare ridondanza e conflitti.
            */
            /*
            // 3. Applica PULSANTE TARIFFE
            if (aos_params.mappa_tariffe && aos_params.mappa_tariffe.hasOwnProperty(numeroPulito)) {
                // Questa logica modifica la struttura, quindi la eseguiamo per ultima
                const numerazioneData = aos_params.mappa_tariffe[numeroPulito];
                const tariffe = numerazioneData.tariffe;
                if (!tariffe || tariffe.length === 0 || link.parent().hasClass('uk-button-group')) return;

                const name = numerazioneData.name || '';
                let currencySymbol = name.includes('+41') ? 'CHF' : '€';
                let buttonStyle = 'uk-button-default';
                if (link.hasClass('uk-button-primary')) { buttonStyle = 'uk-button-primary'; }
                else if (link.hasClass('uk-button-secondary')) { buttonStyle = 'uk-button-secondary'; }

                const tariffButton = $('<button type="button" class="uk-button ' + buttonStyle + '"> ' + currencySymbol + ' </button>');
                const mostraRigaUnica = ((numerazioneData.description || '').toLowerCase().includes('carta di credito') || (numerazioneData.description || '').toLowerCase().includes('ricarica online') || (numerazioneData.description || '').toLowerCase().includes('svizzera') || (numerazioneData.name || '').trim().startsWith('+41'));
                let sonoTutteUguali = false;
                if (tariffe.length > 1) { sonoTutteUguali = tariffe.every(t => t.scatto === tariffe[0].scatto && t.importo === tariffe[0].importo); }

                let tableHtml = '<table class="uk-table uk-table-striped uk-table-small uk-text-center"><thead><tr><th>Operatore</th><th>Scatto</th><th>Costo/min</th></tr></thead><tbody>';
                if (mostraRigaUnica || tariffe.length === 1 || sonoTutteUguali) {
                    let s = parseFloat(tariffe[0].scatto).toFixed(2); let i = parseFloat(tariffe[0].importo).toFixed(2);
                    tableHtml += '<tr><td><strong>Da qualsiasi operatore</strong></td><td>' + s + currencySymbol + '</td><td>' + i + currencySymbol + '/min</td></tr>';
                } else {
                    tariffe.forEach(function(t) { let s = parseFloat(t.scatto).toFixed(2); let i = parseFloat(t.importo).toFixed(2); tableHtml += '<tr><td>' + t.operatore.charAt(0).toUpperCase() + t.operatore.slice(1) + '</td><td>' + s + currencySymbol + '</td><td>' + i + currencySymbol + '/min</td></tr>'; });
                }
                tableHtml += '</tbody></table>';
                const dropdownContent = $('<div />', { 'uk-dropdown': 'mode: click; pos: bottom-right' }).addClass('uk-card uk-card-body uk-card-default uk-padding-small').css('width', '300px').html(tableHtml);
                const tariffToggleContainer = $('<div />').append(tariffButton).append(dropdownContent);
                link.addClass('uk-width-expand').wrap('<div class="uk-button-group uk-width-1-1"></div>').after(tariffToggleContainer);
            }
            */
            /* --- FINE BLOCCO DA COMMENTARE --- */

        });
    }
    // NOTA: il nome di questa funzione nel tuo codice è 'applyAllLabels', ma dovrebbe essere 'applyLabelsAndToggles' per coerenza
    applyLabelsAndToggles();

    // --- LOGICA TRACKING DUALE ---
    function trackViews() {
        const visibleOperators = [];
        $('.aos-operators-grid .operatrice').each(function() {
            const postId = $(this).data('codice');
            if (postId) { visibleOperators.push(postId); }
        });
        if (visibleOperators.length > 0) {
            $.post(aos_params.ajax_url, { action: 'aos_track_views', nonce: aos_params.nonce, codes: visibleOperators });
        }
    }
    trackViews();

    $('.aos-operators-grid').on('click', '.aos-track-click', function() {
        const postId = $(this).data('codice');
        if (postId) {
            $.post(aos_params.ajax_url, { action: 'aos_track_click', nonce: aos_params.nonce, code: postId });
        }
    });

    $(document).on('click', 'a', function() {
        const href = $(this).attr('href');
        if (!href) return;
        let numeroPulito = '';
        if (href.includes('r=pr_cc/CCrecharge4')) {
            const ddiMatch = href.match(/ddi=([0-9\.]+)/);
            if (ddiMatch && ddiMatch[1]) { numeroPulito = ddiMatch[1].replace(/\./g, ''); }
        } else if (href.startsWith('tel')) {
            numeroPulito = href.replace(/[^0-9]/g, '');
            if (numeroPulito.startsWith('39') && numeroPulito.length > 10) { numeroPulito = numeroPulito.substring(2); }
        }
        if (numeroPulito === '' || !aos_params.mappa_numeri || !aos_params.mappa_numeri.hasOwnProperty(numeroPulito)) return;
        const numerazioneId = aos_params.mappa_numeri[numeroPulito];
        let postId = 0; let contextUrl = '';
        const card = $(this).closest('.operatrice');
        if (card.length > 0 && card.data('codice')) { postId = card.data('codice'); }
        else if (aos_params.is_operatrice_page && aos_params.post_id) { postId = aos_params.post_id; }
        else { contextUrl = window.location.href; }
        $.post(aos_params.ajax_url, { action: 'aos_log_global_click', nonce: aos_params.nonce, numerazione_id: numerazioneId, post_id: postId, context_url: contextUrl });
    });
});
