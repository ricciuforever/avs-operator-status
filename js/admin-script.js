jQuery(document).ready(function($) {
        
        var container = $('#aos-tariffe-container');
        
        function addRow() {
            var template = $('#aos-tariffa-template').html();
            container.append(template);
        }

        if ( container.children('.aos-tariffa-row').length === 0 ) {
            addRow();
        }
        
        $('#aos-add-tariffa-row').on('click', function() {
            addRow();
        });

        // Gestione eventi delegata al contenitore
        container.on('click', '.aos-remove-tariffa-row', function() {
            if ( container.children('.aos-tariffa-row').length === 1 ) {
                 $(this).closest('.aos-tariffa-row').find('input, select').val('');
            } else {
                 $(this).closest('.aos-tariffa-row').remove();
            }
        });

        // NUOVO: Gestione del click per duplicare una riga
        container.on('click', '.aos-duplicate-tariffa-row', function() {
            var originalRow = $(this).closest('.aos-tariffa-row');
            
            // Crea una nuova riga dal template
            var newRow = $($('#aos-tariffa-template').html());
            
            // Leggi i valori dalla riga originale
            var operatore = originalRow.find('select[name^=\"aos_tariffe[operatore]\"]').val();
            var scatto = originalRow.find('input[name^=\"aos_tariffe[scatto]\"]').val();
            var importo = originalRow.find('input[name^=\"aos_tariffe[importo]\"]').val();
            
            // Imposta i valori nella nuova riga
            newRow.find('select[name^=\"aos_tariffe[operatore]\"]').val(operatore);
            newRow.find('input[name^=\"aos_tariffe[scatto]\"]').val(scatto);
            newRow.find('input[name^=\"aos_tariffe[importo]\"]').val(importo);
            
            // Inserisci la nuova riga subito dopo quella originale
            originalRow.after(newRow);
        });
    });