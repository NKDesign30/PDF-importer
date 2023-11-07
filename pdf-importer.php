<?php

/**
 * Plugin Name: PDF Importer
 * Description: Importiert PDF-Inhalte und erstellt WordPress-Posts.
 * Version:     1.0
 * Author:      Niko
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once __DIR__ . '/vendor/autoload.php';

// Fügen Sie das Menü im WordPress-Adminbereich hinzu
function pdf_importer_admin_menu()
{
  add_menu_page('PDF Importer', 'PDF Importer', 'manage_options', 'pdf-importer', 'pdf_importer_upload_page');
}
add_action('admin_menu', 'pdf_importer_admin_menu');

// Die Seite für den Upload im Adminbereich
function pdf_importer_upload_page()
{
  // Überprüfen, ob der Benutzer die erforderlichen Berechtigungen hat
  if (!current_user_can('manage_options')) {
    wp_die(__('Sie haben nicht die erforderliche Berechtigung, um diese Seite zu sehen.'));
  }

?>
  <div class="wrap">
    <h1>PDF Importer</h1>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('pdf_importer_upload', 'pdf_importer_nonce'); ?>
      <input type='file' id='pdf_importer_file_upload' name='pdf_importer_file_upload'></input>
      <?php submit_button('PDF hochladen') ?>
    </form>
  </div>
<?php
  // Handhabung des Datei-Uploads
  if (isset($_FILES['pdf_importer_file_upload']) && check_admin_referer('pdf_importer_upload', 'pdf_importer_nonce')) {
    pdf_importer_handle_upload($_FILES['pdf_importer_file_upload']);
  }
}

// Verarbeitung des hochgeladenen PDFs
function pdf_importer_handle_upload($file)
{
  // Dateityp-Überprüfung
  $file_type = wp_check_filetype($file['name']);
  if ('pdf' !== $file_type['ext'] || 'application/pdf' !== $file['type']) {
    echo '<div class="error"><p>Bitte laden Sie nur PDF-Dateien hoch.</p></div>';
    return;
  }

  // Datei-Upload-Handling
  $upload_overrides = array('test_form' => false);
  $uploaded_file = wp_handle_upload($file, $upload_overrides);

  if (isset($uploaded_file['error'])) {
    echo '<div class="error"><p>Fehler beim Hochladen: ' . esc_html($uploaded_file['error']) . '</p></div>';
  } else {
    // PDF-Datei parsen und als Post speichern
    pdf_importer_parse_pdf($uploaded_file['file']);
  }
}

// Parsen der PDF-Datei und Erstellen eines Posts
function pdf_importer_parse_pdf($file_path)
{
  try {
    // Initialisieren von FPDI
    $pdf = new \setasign\Fpdi\Fpdi();

    // Seitenanzahl bekommen
    $pageCount = $pdf->setSourceFile($file_path);
    $text = '';

    // Durch jede Seite gehen und den Text extrahieren
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
      $templateId = $pdf->importPage($pageNo);
      $size = $pdf->getTemplateSize($templateId);

      $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
      $pdf->useTemplate($templateId);

      $text .= $pdf->Text(); // Diese Methode hängt von der Bibliothek ab und muss entsprechend angepasst werden
    }

    // Erstellen eines neuen WordPress-Posts
    $post_id = wp_insert_post(array(
      'post_title'    => 'PDF Import: ' . basename($file_path),
      'post_content'  => $text,
      'post_status'   => 'draft',
      'post_author'   => get_current_user_id(),
      'post_type'     => 'post',
    ));

    if ($post_id) {
      // Erfolgsmeldung
      echo '<div class="updated"><p>PDF erfolgreich importiert und als Entwurf gespeichert.</p></div>';
    } else {
      // Fehlermeldung
      echo '<div class="error"><p>Fehler beim Speichern des Posts.</p></div>';
    }
  } catch (Exception $e) {
    echo '<div class="error"><p>Fehler beim Parsen der PDF: ' . esc_html($e->getMessage()) . '</p></div>';
  } finally {
    // Löschen der temporären Datei
    unlink($file_path);
  }
}


// Aktivierungshook
function pdf_importer_activate()
{
  // Überprüfen der PHP-Version
  if (version_compare(PHP_VERSION, '7.1', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('Dieses Plugin benötigt PHP Version 7.1 oder höher.');
  }

  // Überprüfen der Verfügbarkeit des mbstring-Erweiterung
  if (!extension_loaded('mbstring')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('Dieses Plugin benötigt die mbstring PHP-Erweiterung.');
  }

  // Setzen einer Option, um anzuzeigen, dass das Plugin aktiviert wurde
  update_option('pdf_importer_activated', true);
}

// Deaktivierungshook
function pdf_importer_deactivate()
{
  // Löschen von Plugin-spezifischen Optionen
  delete_option('pdf_importer_activated');
}

register_activation_hook(__FILE__, 'pdf_importer_activate');
register_deactivation_hook(__FILE__, 'pdf_importer_deactivate');
