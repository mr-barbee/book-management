<?php

namespace Drupal\book_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageBase;

/**
 * Configure book_management settings for this site.
 */
class BookManagementSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'book_management.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'book_management_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['disclaimer'] = [
      '#prefix' => "<div>",
      '#suffix' => "</div>",
      '#markup' => t('You can import books directly into the book management system. The CSV file must include the <strong>Title, ID, Grade, and Stock</strong>.<br />
                      The list of acceptable fields are below. All other fields in the .csv file will be disregarded. Also if the stock count is  the books will also be disregarded.')
    ];

    $form['field_descr'] = [
      '#prefix' => "<div>",
      '#suffix' => "</div>",
      '#markup' => t('<strong>Field Descriptions:</strong><br /> TITLE | 255 character limit
                                         <br /> ISBN | numeric digits limit
                                         <br /> SUBJECT | 255 character limit
                                         <br /> VOLUME | 255 character limit
                                         <br /> GRADE | Numeric digit from 1-8 including K
                                         <br /> ID | Numeric Only 3-digits unique book identifier number (XXX)
                                         <br /> TEXT TYPE | Currently C or E
                                         <br /> MATERIAL CATEGORY | Either S for "student" or T for "teacher"
                                         <br /> STOCK | The number of books'
                                      )
    ];

    $form['book_importer'] = [
      '#title' => 'CSV Importer',
      '#type' => 'managed_file',
      '#upload_location' => 'public://book_importer/',
      '#description' => t('Upload the CSV file that you wish to import.'),
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [10485760],
      ],
    ];
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Run Import');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = FALSE;
    $count = [
      'success' => 0,
      'failed' => 0,
      'skipped' => 0
    ];
    // Retrieve the configuration.
    try {
      $file = File::load($form_state->getValue('book_importer')[0]);
      // Load and open the CSV file.
      $fp = fopen($file->getFileUri(), 'r');
      $header_row = array_map('strtolower', fgetcsv($fp, 1024, ","));
      // If these fields are not saved in the header of the csv file the throw an
      // exception and warn user.
      $required_fields = ['title', 'id', 'grade', 'stock'];
      foreach ($required_fields as $key => $required_field) {
        if (array_search($required_field, $header_row) === FALSE) {
          throw new \Exception(t("The required field (@field) isn't set.\n", ['@field' => $required_field]));
        }
      }
      // Gather all of the book content for the import.
      $book_content = [];
      while (($import_row = fgetcsv($fp, 1024, ",")) !== FALSE) {
        // Load all of the fields from the import.
        $isbn = $import_row[array_search('isbn', $header_row)];
        $id = $import_row[array_search('id', $header_row)];
        $title = $import_row[array_search('title', $header_row)];
        $book_count = $import_row[array_search('stock', $header_row)];
        $category = $import_row[array_search('material category', $header_row)];
        $subject = $import_row[array_search('subject', $header_row)];
        $type = $import_row[array_search('text type', $header_row)];
        $grade = $import_row[array_search('grade', $header_row)];
        $volume = $import_row[array_search('volume', $header_row)];

        // If the ISBN isnt numeric only.
        if (preg_match('~[0-9]~', $isbn) === 0  && !empty($isbn)) {
          $count['failed']++;
          continue;
        }
        // If the book number is not numeric then
        // skip this row.
        if (!is_numeric($book_count) && !empty($book_count)) {
          $count['failed']++;
          continue;
        }
        // If the title, grade, or condition is empty then we want
        // to skip this record.
        if (empty($title) || empty($grade)) {
          $count['failed']++;
          continue;
        }
        $book_content[] = [
          'id' => $id,
          'isbn' => $isbn,
          'title' => $title,
          'book_count' => $book_count,
          'subject' => $subject,
          'category' => $category,
          'grade' => $grade,
          'type' => $type,
          'volume' => $volume
        ];
        // count how many rows
        // saved successfully.
        $count['success']++;
      }
      // Throw error bc there is now books to import.
      if (empty($book_content)) {
        throw new \Exception($this->t("There is no valid row in the CSV file to import.\n"));
      }
      // Load the batch process for the book content.
      $batch = [
        'operations' => [
          ['save_book_and_book_items_run', [$book_content]],
        ],
        'finished' => 'save_book_and_book_items_finished',
        'title' => $this->t('Processing Import Batch'),
        'init_message' => $this->t('Batch Import is starting.'),
        'progress_message' => $this->t('Time remaining: @estimate.'),
        'error_message' => $this->t('Batch has encountered an error.')
      ];
      batch_set($batch);
      // We want to delete the file when we are finsihed.
      $file_usage = \Drupal::service('file.usage');
      $file_usage->delete($file, 'book_management');
      // Save the form.
      \Drupal::messenger()->addStatus(t("Book Import Breakdown. Able to import: @success - Unable (not imported): @failed" , ['@success' => $count['success'], '@failed' => $count['failed']]));
    }
    catch (\Exception $e) {
      if ($file) {
        // clean up the file if error was found.
        $file_usage = \Drupal::service('file.usage');
        $file_usage->delete($file, 'book_management');
      }
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to import the csv file! Error: @error\n", ['@error' => $e->getMessage()]));
    }
  }
}
