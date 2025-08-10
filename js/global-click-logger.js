// js/global-click-logger.js - VERSIONE FINALE CON CONTESTO

jQuery(document).ready(function($) {
    'use strict';
    if (typeof aos_global_params === 'undefined' || typeof aos_global_params.numeri === 'undefined') { return; }

    $(document).on('click', 'a', function(e) {
        const link = $(this);
        const href = link.attr('href');
        if (!href) return;

        let numeroPulito = '';
        if (href.includes('r=pr_cc/CCrecharge4')) {
            const ddiMatch = href.match(/ddi=([0-9\.]+)/);
            if (ddiMatch && ddiMatch[1]) { numeroPulito = ddiMatch[1].replace(/\./g, ''); }
        } else if (href.startsWith('tel:')) {
            numeroPulito = href.substring(href.indexOf(':') + 1).replace(/[^0-9]/g, '');
        }

        if (numeroPulito === '' || !aos_global_params.numeri.hasOwnProperty(numeroPulito)) {
            return;
        }

        const numerazioneId = aos_global_params.numeri[numeroPulito];
        let postId = 0;
        let contextUrl = '';

        // --- NUOVA LOGICA PER CAPIRE IL CONTESTO ---

        // 1. Il click è avvenuto dentro una card di uno shortcode?
        const card = link.closest('.operatrice');
        if (card.length > 0 && card.data('codice')) {
            postId = card.data('codice');
        }
        // 2. Altrimenti, siamo in una pagina di una singola operatrice?
        else if (aos_global_params.is_operatrice_page && aos_global_params.post_id) {
            postId = aos_global_params.post_id;
        }
        // 3. Altrimenti, è un click generico. Salviamo l'URL della pagina.
        else {
            contextUrl = window.location.href;
        }

        // Invia i dati al backend
        $.post(aos_global_params.ajax_url, {
            action: 'aos_log_global_click',
            nonce: aos_global_params.nonce,
            numerazione_id: numerazioneId,
            post_id: postId,
            context_url: contextUrl
        });
    });
});
