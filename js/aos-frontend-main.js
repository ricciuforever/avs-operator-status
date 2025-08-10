jQuery(document).ready(function($) {
    'use strict';

    // Aggiungere questa variabile in cima al file, o comunque in uno scope accessibile
    const AOS_UNBLOCK_MODAL_ID = '#aos-unblock-modal';

    // Se i dati dal PHP non esistono, non facciamo nulla.
    if (typeof aos_frontend_params === 'undefined') {
        return;
    }

    // ====================================================================
    // 1. FUNZIONI HELPER (definite qui per essere accessibili globalmente o da più funzioni)
    // ====================================================================

    const AOS_FAVORITES_COOKIE_NAME = 'aos_favorites';

    /**
     * Legge e restituisce la lista di ID dei preferiti dal cookie.
     */
    function getFavoritesFromCookie() {
        const cookieValue = document.cookie.split('; ').find(row => row.startsWith(AOS_FAVORITES_COOKIE_NAME + '='));
        if (cookieValue) {
            const idsString = cookieValue.split('=')[1];
            if (idsString === '') return [];
            return idsString.split(',').map(id => parseInt(id, 10)).filter(id => !isNaN(id));
        }
        return [];
    }

    /**
     * Salva una lista di ID preferiti nel cookie.
     */
    function saveFavoritesToCookie(favoritesArray) {
        const idsString = [...new Set(favoritesArray)].join(',');
        const expires = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString();
        document.cookie = `${AOS_FAVORITES_COOKIE_NAME}=${idsString}; expires=${expires}; path=/; SameSite=Lax`;
    }

    /**
     * Cancella il cookie dei preferiti.
     */
    function deleteFavoritesCookie() {
        document.cookie = AOS_FAVORITES_COOKIE_NAME + '=; Max-Age=-99999999; path=/;';
    }

    /**
     * Estrae un numero pulito (solo cifre) dall'attributo href di un link.
     */
    function getCleanNumber(href) {
        if (!href) return '';
        let numeroPulito = '';
        if (href.includes('r=pr_cc/CCrecharge4')) {
            const ddiMatch = href.match(/ddi=([0-9\.]+)/);
            if (ddiMatch && ddiMatch[1]) {
                numeroPulito = ddiMatch[1].replace(/\./g, '');
            }
        } else if (href.startsWith('tel:')) {
            numeroPulito = href.substring(href.indexOf(':') + 1).replace(/[^0-9]/g, '');
            if (numeroPulito.startsWith('39') && numeroPulito.length > 10) {
                numeroPulito = numeroPulito.substring(2);
            }
        }
        return numeroPulito;
    }

    // Gestione tracking (spostata qui per renderla accessibile)
    function initializeTracking() {
        // Views
        const visibleOperators = [];
        // --- CORREZIONE: Aggiunto selettore per le pagine archivio .tax-genere ---
        $('.aos-operators-grid .operatrice, .tax-genere .operatrice').each(function() {
            const postId = $(this).data('codice');
            if (postId) visibleOperators.push(postId);
        });
        if (visibleOperators.length > 0) {
            $.post(aos_frontend_params.ajax_url, { action: 'aos_track_views', nonce: aos_frontend_params.nonce, codes: visibleOperators });
        }

        // Clicks contatore legacy
        // --- CORREZIONE: Modificato l'event handler per essere più robusto e includere .tax-genere ---
        // Usiamo $(document).on() per una maggiore compatibilità con contenuti caricati dinamicamente (AJAX)
        $(document).on('click', '.aos-operators-grid .aos-track-click, .tax-genere .aos-track-click', function() {
            // Troviamo la card genitore più vicina per assicurarci di prendere il codice corretto
            const card = $(this).closest('.operatrice');
            const postId = card.data('codice');
            if (postId) {
                $.post(aos_frontend_params.ajax_url, { action: 'aos_track_click', nonce: aos_frontend_params.nonce, code: postId });
            }
        });

        // Click tracking globale (questa funzione era già corretta e globale)
        $(document).on('click', 'a', function() {
            const href = $(this).attr('href');
            const numeroPulito = getCleanNumber(href);
            if (numeroPulito === '' || !aos_frontend_params.mappa_numeri || !aos_frontend_params.mappa_numeri.hasOwnProperty(numeroPulito)) return;
            const numerazioneId = aos_frontend_params.mappa_numeri[numeroPulito];
            let postId = 0;
            const contextUrl = window.location.href;
            const card = $(this).closest('.operatrice');
            if (card.length > 0 && card.data('codice')) { postId = card.data('codice'); }
            else if (aos_frontend_params.is_operatrice_page && aos_frontend_params.post_id) { postId = aos_frontend_params.post_id; }
            $.post(aos_frontend_params.ajax_url, { action: 'aos_log_global_click', nonce: aos_frontend_params.nonce, numerazione_id: numerazioneId, post_id: postId, context_url: contextUrl });
        });
    }

    /**
     * Carica le card dei preferiti nella pagina dedicata.
     */
    function loadFavoritesOnPage() {
        const favoritesContainer = $('#aos-favorites-container');
        const clearButton = $('#aos-clear-favorites');
        if (favoritesContainer.length === 0) return;

        const favoriteIds = getFavoritesFromCookie();
        if (favoriteIds.length === 0) {
            favoritesContainer.html('<div class="uk-width-1-1"><p>Non hai ancora aggiunto nessuna operatrice ai tuoi preferiti.</p></div>');
            clearButton.hide();
            return;
        }

        clearButton.show();
        favoritesContainer.html('<div class="uk-width-1-1"><div uk-spinner="ratio: 2"></div></div>');

        $.ajax({
            url: aos_frontend_params.ajax_url, method: 'POST',
            data: { action: 'aos_get_favorite_cards_html', nonce: aos_frontend_params.nonce, operator_ids: favoriteIds }
        }).done(function(response) {
            if (response.success && response.data) {
                favoritesContainer.html(response.data);
                // Dopo aver caricato le nuove card, inizializza le loro funzionalità
                window.initializeAllFeatures();
            } else {
                favoritesContainer.html('<div class="uk-width-1-1"><p>Si è verificato un errore nel caricare i tuoi preferiti.</p></div>');
            }
        });
    }

    // ====================================================================
    // 2. LOGICA PRINCIPALE E INIZIALIZZAZIONE
    // ====================================================================

    /**
     * Itera su tutti i link e le card, applicando tutte le funzionalità.
     * Questa funzione unifica la logica di tutte le versioni precedenti.
     * RESA GLOBALE per essere chiamata anche da altri script (es. quiz).
     */
    window.initializeAllFeatures = function() {
        const currentFavorites = getFavoritesFromCookie();
        const hasFavorites = currentFavorites.length > 0;

        $('.operatrice').each(function() {
            const card = $(this);
            if (card.data('aos-processed')) return; // Evita di processare la stessa card più volte

            const operatorId = parseInt(card.data('codice'), 10);
            if (!operatorId) return;

            // --- Aggiungi Contenitore Preferiti ---
            const heartIcon = $('<a href="#" class="aos-favorite-toggle" uk-icon="icon: heart; ratio: 1.2"></a>');
            heartIcon.attr('aria-label', 'Aggiungi ai preferiti');
            if (currentFavorites.includes(operatorId)) {
                heartIcon.addClass('is-favorite');
            }
            const favoriteContainer = $('<div class="aos-favorite-container"></div>').append(heartIcon);
            if (hasFavorites) {
                favoriteContainer.append('<a href="/le-mie-preferite/" class="aos-view-favorites-link">Visualizza preferite</a>');
            }
            card.find('.uk-position-relative').first().append(favoriteContainer);

            // Trova tutti i link all'interno della card e applica le altre logiche
            card.find('a[href*="tel:"], a[href*="ddi="]').each(function() {
                const link = $(this);
                const buttonWrapper = link.closest('.uk-button');

                const href = link.attr('href');
                const numeroPulito = getCleanNumber(href);
                if (numeroPulito === '') return;

                let wrapper = link.parent('.promo-wrapper');
                if (wrapper.length === 0) {
                    link.wrap('<div class="promo-wrapper"></div>');
                    wrapper = link.parent();
                }

                // --- Applica Etichetta Promo ---
                if (aos_frontend_params.promo_list && aos_frontend_params.promo_list.hasOwnProperty(numeroPulito)) {
                    const promoInfo = aos_frontend_params.promo_list[numeroPulito];
                    const promoLabel = $('<a />', {'class': 'uk-label uk-light uk-background-primary promo-label', 'text': 'Promo!'});
                    const dropdownContent = $('<div />', {'uk-dropdown': 'mode: click; pos: top-right'}).addClass('uk-card uk-card-body uk-background-primary uk-padding-small').css('width', '280px').append($('<p />').html(promoInfo.messaggio.replace(/\n/g, '<br>')));
                    $('<div />', {'class': 'promo-toggle-container'}).append(promoLabel).append(dropdownContent).prependTo(wrapper);
                }

                // --- Applica Toggle Tariffe ---
                if (buttonWrapper.length && aos_frontend_params.mappa_tariffe && aos_frontend_params.mappa_tariffe.hasOwnProperty(numeroPulito)) {
                   if (buttonWrapper.parent().hasClass('uk-button-group')) return;

                   const tariffe = aos_frontend_params.mappa_tariffe[numeroPulito];
                   const currencySymbol = href.includes('+41') ? 'CHF' : '€';
                   const buttonStyle = buttonWrapper.hasClass('uk-button-primary') ? 'uk-button-primary' : (buttonWrapper.hasClass('uk-button-secondary') ? 'uk-button-secondary' : 'uk-button-default');
                   const tariffButton = $('<button type="button" class="uk-button ' + buttonStyle + '"> ' + currencySymbol + ' </button>');

                   const mostraColonnaScatto = tariffe.some(t => parseFloat(t.scatto) > 0);
                   let tableHtml = '<table class="uk-table uk-table-striped uk-table-small uk-text-center"><thead><tr><th>Gestore</th>' + (mostraColonnaScatto ? '<th>Scatto</th>' : '') + '<th>Costo/min</th></tr></thead><tbody>';
                   const tutteTariffeUguali = tariffe.every((t, i, arr) => i === 0 || (t.scatto === arr[0].scatto && t.importo === arr[0].importo));

                   if (tariffe.length === 1 || tutteTariffeUguali) {
                       let scatto = parseFloat(tariffe[0].scatto).toFixed(2);
                       let importo = parseFloat(tariffe[0].importo).toFixed(2);
                       tableHtml += '<tr><td>Da qualsiasi gestore</td>' + (mostraColonnaScatto ? '<td>' + scatto + currencySymbol + '</td>' : '') + '<td>' + importo + currencySymbol + '/min</td></tr>';
                   } else {
                       tariffe.forEach(function(tariffa) {
                           let scatto = parseFloat(tariffa.scatto).toFixed(2);
                           let importo = parseFloat(tariffa.importo).toFixed(2);
                           tableHtml += '<tr><td>' + tariffa.operatore.charAt(0).toUpperCase() + tariffa.operatore.slice(1) + '</td>' + (mostraColonnaScatto ? '<td>' + scatto + currencySymbol + '</td>' : '') + '<td>' + importo + currencySymbol + '/min</td></tr>';
                       });
                   }
                   tableHtml += '</tbody></table>';

                   const dropdownContent = $('<div />', { 'uk-dropdown': 'mode: click; pos: bottom-right' }).addClass('uk-card uk-card-body uk-card-default uk-padding-small').css('width', 'auto').html(tableHtml);
                   const tariffToggleContainer = $('<div />').append(tariffButton).append(dropdownContent);

                   buttonWrapper.addClass('uk-width-expand uk-text-center').wrap('<div class="uk-button-group uk-width-1-1"></div>').after(tariffToggleContainer);
                }
            });

            // --- NUOVO: Aggiungi Pulsante Info Sblocco Numeri ---
            // Contenitore per il pulsante, posizionato in basso a destra
            const infoButtonContainer = $('<div class="aos-unblock-info-container"></div>');
            // MODIFICATO: Aggiunto il testo "Sblocco 899" all'interno del link <a>
            const infoButton = $(`<a href="${AOS_UNBLOCK_MODAL_ID}" uk-toggle class="uk-icon-button uk-label uk-label-light uk-background-primary" title="Come sbloccare il telefono per chiamare">
                                    <span uk-icon="icon: info; ratio: 0.8" class="uk-margin-small-right"></span> Sblocco 899
                                  </a>`); // Ho aggiunto uk-margin-small-right all'icona per spaziatura

            infoButtonContainer.append(infoButton);
            // Cerca il footer della card, se esiste, altrimenti appende direttamente alla card
            if (card.find('.uk-card-footer').length) {
                card.find('.uk-card-footer').append(infoButtonContainer);
            } else {
                card.append(infoButtonContainer);
            }

            card.data('aos-processed', true);
        });
    }; // Fine di window.initializeAllFeatures

    // ====================================================================
    // 3. GESTIONE EVENTI (EVENT HANDLERS)
    // ====================================================================

    // Gestisce il click sul cuore
    $(document).on('click', '.aos-favorite-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const heartIcon = $(this);
        const card = heartIcon.closest('.operatrice');
        const operatorId = parseInt(card.data('codice'), 10);
        if (!operatorId) return;

        let currentFavorites = getFavoritesFromCookie();
        const isCurrentlyFavorite = currentFavorites.includes(operatorId);
        let modalMessage = '';
        let actionType = ''; // Variabile per il nostro "ping"

        if (isCurrentlyFavorite) {
            // Rimuovi dai preferiti
            currentFavorites = currentFavorites.filter(id => id !== operatorId);
            heartIcon.removeClass('is-favorite');
            modalMessage = 'Operatrice rimossa dai tuoi preferiti.';
            actionType = 'remove'; // Azione: -1
        } else {
            // Aggiungi ai preferiti
            currentFavorites.push(operatorId);
            heartIcon.addClass('is-favorite');
            modalMessage = 'Operatrice aggiunta ai tuoi preferiti!';
            actionType = 'add'; // Azione: +1
        }

        // 1. Aggiorna il cookie (logica per l'utente)
        saveFavoritesToCookie(currentFavorites);

        // 2. Invia il ping al server per aggiornare il contatore (logica per l'admin)
        $.post(aos_frontend_params.ajax_url, {
            action: 'aos_update_favorite_count',
            nonce: aos_frontend_params.nonce,
            operator_id: operatorId,
            action_type: actionType
        });

        // 3. Mostra la modale di conferma
        const modalContent = `<div class="uk-modal-body uk-text-center"><p>${modalMessage}</p><a href="/le-mie-preferite/" class="uk-button uk-button-primary uk-margin-top">Vai alle tue preferite</a><button class="uk-modal-close-default" type="button" uk-close></button></div>`;
        UIkit.modal.dialog(modalContent);

        if (currentFavorites.length > 0) {
            if ($('.aos-view-favorites-link').length === 0) {
                $('.aos-favorite-container').append('<a href="/le-mie-preferite/" class="aos-view-favorites-link">Visualizza preferite</a>');
            }
        } else {
            $('.aos-view-favorites-link').remove();
        }
        if (heartIcon.closest('#aos-favorites-container').length > 0) {
            if (isCurrentlyFavorite) { card.parent('div').fadeOut(300, function() { $(this).remove(); }); }
            if (currentFavorites.length === 0) { loadFavoritesOnPage(); }
        }
    });

    // Gestisce il click su "Rimuovi tutti i preferiti"
    $(document).on('click', '#aos-clear-favorites', function() {
        if (confirm('Sei sicuro di voler rimuovere tutte le operatrici dai preferiti?')) {
            deleteFavoritesCookie();
            $('.aos-favorite-toggle').removeClass('is-favorite');
            loadFavoritesOnPage();
        }
    });

    // ====================================================================
    // 4. ESECUZIONE ALL'AVVIO
    // ====================================================================
    window.initializeAllFeatures(); // Esegue all'avvio della pagina
    loadFavoritesOnPage(); // Carica preferiti se la pagina è quella
    initializeTracking(); // Inizializza il tracking

}); // Fine di jQuery(document).ready

document.addEventListener('DOMContentLoaded', function() {
    const businessWhatsappNumber = '391234567890'; // SOSTITUISCI CON IL TUO NUMERO

    document.querySelectorAll('.simple-whatsapp-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const operatriceName = this.getAttribute('data-operatrice');
            const message = `Ciao, vorrei parlare con ${operatriceName}.`;
            const encodedMessage = encodeURIComponent(message);
            const whatsappUrl = `https://wa.me/${businessWhatsappNumber}?text=${encodedMessage}`;
            window.open(whatsappUrl, '_blank');
        });
    });
});
