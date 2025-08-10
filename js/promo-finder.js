// js/promo-finder.js - VERSIONE CON DROPDOWN (TOGGLE)

jQuery(document).ready(function($) {

    // La variabile 'aos_promo_list' ci viene passata da PHP.
    if (typeof aos_promo_list === 'undefined' || $.isEmptyObject(aos_promo_list)) {
        return; // Non ci sono promo attive, usciamo.
    }

    // Cerchiamo in tutta la pagina i link che contengono "tel:" nel loro href.
    $('a[href*="tel:"]').each(function() {
        var link = $(this);
        var href = link.attr('href');
        
        // Estraiamo il numero di telefono pulito (solo cifre) dal link.
        var numeroTelefono = href.replace(/[^0-9]/g, '');

        // Controlliamo se questo numero è nella nostra lista di promo.
        if (aos_promo_list.hasOwnProperty(numeroTelefono)) {
            var promoInfo = aos_promo_list[numeroTelefono];
            
            // Troviamo il contenitore-pulsante più vicino.
            var wrapper = link.closest('.uk-button');
            if (!wrapper.length) {
                link.wrap('<div class="promo-wrapper"></div>');
                wrapper = link.parent();
            } else {
                wrapper.addClass('promo-wrapper');
            }

            // Se questo contenitore è già stato processato, saltiamo.
            if (wrapper.data('promo-applied')) {
                return;
            }
            wrapper.data('promo-applied', true);

            // --- NUOVA LOGICA: Creazione del Toggle/Dropdown ---

            // 1. Creiamo l'etichetta "Promo!" che farà da trigger.
            //    NON ha più un link href, è solo un elemento cliccabile.
            var promoLabel = $('<a />', {
                'class': 'uk-label uk-light uk-background-primary promo-label',
                'text': 'Promo!'
            });

            // 2. Creiamo il pannello dropdown nascosto con il messaggio.
            //    'mode: click' fa sì che si apra e chiuda al click.
            var dropdownContent = $('<div />', { 'uk-dropdown': 'mode: click; pos: top-right' })
                .addClass('uk-card uk-card-body uk-background-primary uk-padding-small')
                .css('width', '280px') // Diamo una larghezza fissa al dropdown
                .append(
                    $('<p />').html(promoInfo.messaggio.replace(/\n/g, '<br>'))
                );

            // 3. Creiamo un piccolo contenitore per l'etichetta e il suo dropdown.
            //    Questo serve a UIkit per collegare i due elementi.
            var promoContainer = $('<div />', {
                'class': 'promo-toggle-container'
            });
            
            promoContainer.append(promoLabel).append(dropdownContent);

            // 4. Inseriamo il nostro contenitore promo all'inizio del wrapper del pulsante.
            wrapper.prepend(promoContainer);
        }
    });
});