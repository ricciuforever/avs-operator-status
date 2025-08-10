<?php
/**
 * Mostra un lettore audio per un'operatrice se il file MP3 esiste.
 * Il nome del file deve corrispondere al nome dell'operatrice, tutto in minuscolo.
 * Esempio: per l'operatrice "Veronica", il file deve essere "veronica.mp3".
 *
 * @param string $operator_name Il nome dell'operatrice (es. da get_the_title()).
 */
function lineebollenti_display_operator_audio($operator_name) {
    // 1. Prepara i percorsi
    // Converte il nome in minuscolo per corrispondere al nome del file (es. "Veronica" -> "veronica")
    $file_name = strtolower($operator_name) . '.mp3';

    // Percorso URL per l'attributo 'src' del tag audio
    $audio_url = content_url('/uploads/audio/' . $file_name);

    // Percorso fisico sul server per controllare se il file esiste
    // WP_CONTENT_DIR è una costante di WordPress che punta alla cartella wp-content
    $upload_dir_info = wp_upload_dir();
    $audio_path = $upload_dir_info['basedir'] . '/audio/' . $file_name;

    // 2. Controlla se il file esiste fisicamente sul server
    if (file_exists($audio_path)) {
        // 3. Se esiste, stampa il tag <audio> di HTML5
        echo '<div class="operator-audio-player">';
        echo '  <p class="audio-intro">Ascolta la mia voce...</p>';
        echo '  <audio controls src="' . esc_url($audio_url) . '">';
        echo '      Il tuo browser non supporta l\'elemento audio.';
        echo '  </audio>';
        echo '</div>';
    }
    // Se il file non esiste, la funzione non farà nulla.
}