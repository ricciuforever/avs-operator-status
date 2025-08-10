// js/tracking-script.js - VERSIONE FINALE CON CONTESTO COMPLETO

jQuery(document).ready(function($) {
    'use strict';
    if (typeof aos_tracking_params === 'undefined') { return; }

    // --- SISTEMA 1: TRACCIAMENTO PER CONTATORI (invariato) ---
    function trackViews() {
        const visibleOperators = [];
        $('.aos-operators-grid .cartomante').each(function() {
            const postId = $(this).data('codice');
            if (postId) { visibleOperators.push(postId); }
        });
        if (visibleOperators.length > 0) {
            $.post(aos_tracking_params.ajax_url, {
                action: 'aos_track_views',
                nonce: aos_tracking_params.nonce,
                codes: visibleOperators
            });
        }
    }
    trackViews();

    $('.aos-operators-grid').on('click', '.aos-track-click', function() {
        const postId = $(this).data('codice');
        if (postId) {
            $.post(aos_tracking_params.ajax_url, {
                action: 'aos_track_click',
                nonce: aos_tracking_params.nonce,
                code: postId
            });
        }
    });

    // --- SISTEMA 2: TRACCIAMENTO GLOBALE (logica di contesto aggiornata) ---
    if (typeof aos_tracking_params.mappa_numeri !== 'undefined') {
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
                if (numeroPulito.startsWith('39') && numeroPulito.length > 10) {
                    numeroPulito = numeroPulito.substring(2);
                }
            }

            if (numeroPulito === '' || !aos_tracking_params.mappa_numeri.hasOwnProperty(numeroPulito)) {
                return;
            }

            const numerazioneId = aos_tracking_params.mappa_numeri[numeroPulito];
            
            // --- MODIFICA CHIAVE: Catturiamo SEMPRE l'URL e POI cerchiamo l'ID ---
            
            let postId = 0;
            const contextUrl = window.location.href; // Catturiamo l'URL della pagina in ogni caso.

            // Cerchiamo di identificare se il click è avvenuto su una card o pagina specifica
            const card = link.closest('.cartomante');
            if (card.length > 0 && card.data('codice')) {
                postId = card.data('codice');
            } else if (aos_tracking_params.is_operatrice_page && aos_tracking_params.post_id) {
                postId = aos_tracking_params.post_id;
            }
            
            // Invia TUTTE le informazioni al backend
            $.post(aos_tracking_params.ajax_url, {
                action: 'aos_log_global_click',
                nonce: aos_tracking_params.nonce,
                numerazione_id: numerazioneId,
                post_id: postId, // Sarà > 0 se trovato, altrimenti 0
                context_url: contextUrl // Verrà inviato sempre
            });
        });
    }
});