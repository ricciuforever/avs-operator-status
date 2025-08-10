
jQuery(document).ready(function($) {
    'use strict';

    // 1. CONFIGURAZIONE E VARIABILI
    const allQuestions = [
        { type: 'intenzione', question: "Di cosa hai più voglia questa sera?", answers: { frizzante: "Una chiacchierata frizzante e senza pensieri", passionale: "Un'esperienza intensa e passionale", esotica: "Un viaggio esotico e misterioso" } },
        { type: 'approccio', question: "Quale approccio ti intriga di più?", answers: { dolcezza: "Dolcezza e totale complicità", dominazione: "Dominazione e un pizzico di malizia", mix: "Un mix equilibrato" } },
        { type: 'esperienza', question: "Ti fidi di più di...", answers: { esperta: "Una guida esperta", novita: "L'emozione di una scoperta" } },
        { type: 'dialogo', question: "Preferisci un dialogo...", answers: { diretto: "Diretto e senza peli sulla lingua", allusivo: "Suggestivo e pieno di allusioni", ascolto: "Profondo e basato sull'ascolto" } },
        { type: 'carattere', question: "Che tipo di carattere ti attrae?", answers: { estroverso: "Espansivo e solare", introverso: "Riservato e misterioso", maturo: "Sicuro e maturo" } }
    ];
    
    let isQuizActive = false;
    let selectedQuestions = [];
    let currentQuestionIndex = 0;
    let userAnswers = [];
    const quizModal = $('#avs-quiz-modal');
    const questionsContainer = $('#avs-quiz-questions');
    const resultsContainer = $('#avs-quiz-results');
    const resultsContent = $('#avs-quiz-results-content');

    // NUOVO: Genera un ID di sessione univoco per il quiz
     const quizSessionId = 'quiz_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
 
     // NUOVA FUNZIONE: Invia un evento di tracking al backend
     function avsQuizTrackEvent(eventType, data = {}) {
         $.ajax({
             url: quiz_params.ajax_url,
             type: 'POST',
             data: {
                 action: 'avs_track_quiz_event',
                 security: quiz_params.nonce,
                 session_id: quizSessionId,
                 event_type: eventType,
                 click_context: window.location.href, // NEW: Always send current URL
                 ...data // Aggiunge dati specifici dell'evento (es. question_number, answer_key, operator_id)
             },
             success: function(response) {
                 // console.log('Quiz tracking response:', response); // Per debug
             },
             error: function(error) {
                 // console.error('Quiz tracking error:', error); // Per debug
             }
         });
     }
    
    // 2. FUNZIONI DEL QUIZ
    function displayQuestion() {
        if (currentQuestionIndex >= selectedQuestions.length) {
            sendResults();
            return;
        }
        const q = selectedQuestions[currentQuestionIndex];
        let questionHTML = `
            <h2 class="uk-text-center uk-margin-medium-bottom">${q.question}</h2>
            <p class="uk-text-center uk-text-small uk-margin-top">Domanda ${currentQuestionIndex + 1} di ${selectedQuestions.length}</p>
            <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s" uk-grid>`;
        for (const key in q.answers) {
            questionHTML += `<div><button class="avs-answer-btn uk-button uk-button-default uk-button-large uk-width-1-1" data-type="${q.type}" data-answer-key="${key}">${q.answers[key]}</button></div>`;
        }
        questionHTML += '</div>';
        questionsContainer.html(questionHTML);
    }

    function sendResults() {
        questionsContainer.empty();
        resultsContainer.removeClass('uk-hidden');
        $.ajax({
            url: quiz_params.ajax_url, type: 'POST',
            data: { action: 'calcola_quiz_results', security: quiz_params.nonce, answers: JSON.stringify(userAnswers) },
            success: function(response) {
                if(response.success) {
                    resultsContent.html(response.data.html);
                    // Attiva i componenti UIkit (inclusi i dropdown delle tariffe)
                    UIkit.update(resultsContent[0]); 
                     window.initializeAllFeatures();
                     // NUOVO: Logga l'evento "risultati_mostrati" per ogni operatrice suggerita
                     if (response.data.ids && response.data.ids.length > 0) {
                         response.data.ids.forEach(function(operatorId) {
                             avsQuizTrackEvent('risultati_mostrati', { operator_id: operatorId });
                         });
                     }
                } else {
                    resultsContent.html('<p class="uk-text-center">Errore. Riprova.</p>');
                }
            }
        });
    }

    function resetQuiz() {
        selectedQuestions = allQuestions.sort(() => 0.5 - Math.random()).slice(0, 3);
        currentQuestionIndex = 0;
        userAnswers = [];
        resultsContainer.addClass('uk-hidden');
        resultsContent.html('<div class="uk-text-center" uk-spinner="ratio: 3"></div>');
        questionsContainer.removeClass('uk-hidden');
        avsQuizTrackEvent('quiz_iniziato');
    }

    // 3. GESTIONE EVENTI
    questionsContainer.on('click', '.avs-answer-btn', function() {
        userAnswers.push({ type: $(this).data('type'), answer: $(this).data('answer-key') });

        // CORRETTO: Logga l'evento "risposta_data" PRIMA di incrementare l'indice
        avsQuizTrackEvent('risposta_data', { 
            question_number: currentQuestionIndex + 1, // Ora il numero è corretto
            answer_key: $(this).data('answer-key') 
        });

        currentQuestionIndex++;
        displayQuestion();
    });

    // Logica stabile per il modale per prevenire i riavvii
    UIkit.util.on(quizModal, 'beforeshow', function () {
        if (isQuizActive) return;
        isQuizActive = true;
        resetQuiz();
        displayQuestion();
    });

    UIkit.util.on(quizModal, 'hidden', function () {
        isQuizActive = false;
        // NUOVO: Logga la chiusura del quiz
        avsQuizTrackEvent('quiz_chiuso');
     });

    // Inizializza le funzionalità delle card SOLO quando la modal del quiz è completamente mostrata
    UIkit.util.on(quizModal, 'shown', function () {
        // Ora initializeAllFeatures è definita a livello globale
        window.initializeAllFeatures(); 
    });

    // NUOVO: Gestisce il click sulle operatrici suggerite dal quiz
     // Usiamo la delegazione per catturare i click su elementi aggiunti dinamicamente
     resultsContainer.on('click', '.cartomante a[href*="tel:"]', function() {
         const card = $(this).closest('.cartomante');
         const operatorId = card.data('codice');
         if (operatorId) {
             avsQuizTrackEvent('risultato_cliccato', { operator_id: operatorId });
         }
     });
 
     // NUOVO: Gestisce il click su "Vai alle tue preferite" all'interno del quiz
     resultsContainer.on('click', '.aos-view-favorites-link', function() {
         const card = $(this).closest('.cartomante');
         const operatorId = card.data('codice');
         if (operatorId) {
              avsQuizTrackEvent('preferiti_cliccato', { operator_id: operatorId });
         }
     });

});
