// js/basso-costo-labeler.js - VERSIONE FINALE CORRETTA

jQuery(document).ready(function($) {

    if (typeof aos_basso_costo_list === 'undefined' || aos_basso_costo_list.length === 0) {
        return; // Non ci sono numeri a basso costo, usciamo.
    }

    // Cerchiamo in tutta la pagina i link che contengono "tel:"
    $('a[href*="tel:"]').each(function() {
        var link = $(this);
        var href = link.attr('href');
        
        var numeroTelefono = href.replace(/[^0-9]/g, '');

        if (aos_basso_costo_list.includes(numeroTelefono)) {
            
            var wrapper = link.closest('.uk-button');
            if (!wrapper.length) {
                return; // Se non è un bottone UIkit, non facciamo nulla
            }

            if (wrapper.data('basso-costo-applied')) {
                return; // Evita duplicati
            }
            wrapper.data('basso-costo-applied', true);

            wrapper.addClass('promo-wrapper');

            // MODIFICA CRUCIALE: Usiamo classi specifiche per il basso costo
            var bassoCostoLabel = $('<span />', {
                'class': 'uk-label uk-background-success basso-costo-label', // Stile verde e classe per il posizionamento
                'text': 'Basso Costo!'
            });

            // Inseriamo l'etichetta all'inizio e dentro il pulsante
            wrapper.prepend(bassoCostoLabel);
        }
    });
});