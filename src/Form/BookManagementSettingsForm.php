<?php

namespace Drupal\book_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageBase;
use Drupal\node\Entity\Node;

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
   * Class constructor.
   */
  public function __construct() {
    $this->service = \Drupal::service('book_management.services');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['disclaimer'] = array(
      '#prefix' => "<div>",
      '#suffix' => "</div>",
      '#markup' => t('You can import books directly into the book management system. The CSV file must include the <strong>Title, ISBN, Grade, Condition, Book Number</strong>.<br /> The optional fields are Subject, Volume. All other fields in the .csv file will be disregarded. Also already loaded books will also be disregarded.')
    );

    $form['field_descr'] = array(
      '#prefix' => "<div>",
      '#suffix' => "</div>",
      '#markup' => t('<strong>Field Descriptions:</strong><br /> TITLE | 255 character limit
                                         <br /> ISBN | 13 numeric digits limit
                                         <br /> SUBJECT | 255 character limit
                                         <br /> VOLUME | 255 character limit
                                         <br /> GRADE | Numeric digit only from 1-8
                                         <br /> CONDITION | bad, good, or excellent
                                         <br /> BOOK NUMBER | Numeric Only 3-digits (XXX)')
    );

    $form['book_importer'] = array(
      '#title' => 'CSV Importer',
      '#type' => 'managed_file',
      '#upload_location' => 'public://book_importer/',
      '#description' => t('Upload the CSV file that you wish to import.'),
      '#required' => TRUE,
      '#upload_validators' => array(
        'file_validate_extensions' => array('csv'),
        'file_validate_size' => array(25600000),
      ),
    );
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Run Import');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
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
      $required_fields = array('title', 'isbn', 'grade', 'condition', 'book number');
      foreach ($required_fields as $key => $required_field) {
        if (array_search($required_field, $header_row) === FALSE) {
          throw new \Exception(t("The required field (@field) isn't set.\n", array('@field' => $required_field)));
        }
      }
      while (($import_row = fgetcsv($fp, 1024, ",")) !== FALSE) {
        // Load all of the fields from the import.
        $isbn = $import_row[array_search('isbn', $header_row)];
        $title = $import_row[array_search('title', $header_row)];
        $book_number = $import_row[array_search('book number', $header_row)];
        $subject = $import_row[array_search('subject', $header_row)];
        $grade = $import_row[array_search('grade', $header_row)];
        $volume = $import_row[array_search('volume', $header_row)];
        $condition = $import_row[array_search('condition', $header_row)];

        // If the ISBN isnt numeric only or not 13 characters long then skip this row.
        if (preg_match('~[0-9]~', $isbn) === 0 || strlen($isbn) !== 13) {
          $count['failed']++;
          continue;
        }
        // If the book number is not numeric then
        // skip this row.
        if (!is_numeric($book_number)) {
          $count['failed']++;
          continue;
        }
        // If the title, grade, or condition is empty then we want
        // to skip this record.
        if (empty($title) || empty($grade) || empty($condition)) {
          $count['failed']++;
          continue;
        }
        // Format the Book ID value.
        $book_id = $this->service->getBookIdFormat($isbn, $book_number);
        // Load the book based on the Book ID.
        $nodes = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadByProperties(['field_book_item_id' => $book_id]);
        // Make sure that node does not exists first.
        if (reset($nodes) == FALSE) {
          // Check to see if the ISBN is all ready saved in the system.
          $book_entity = $this->service->getBookByIsbn($isbn);
          if ($book_entity == FALSE){
            // I want to make a new book node
            $book_entity = Node::create(['type' => 'book']);
            $book_entity->enforceIsNew();
            $book_entity->set('title', $title);
            $book_entity->set('field_book_isbn', $isbn);
            $book_entity->set('field_book_subject', $subject);
            $book_entity->set('field_book_grade', $grade);
            $book_entity->set('field_book_volume', $volume);
            $book_entity->set('field_book_depreciated', FALSE);
            $book_entity->status = 1;
            $book_entity->save();
          }
          // Generate a new book item.
          $book_item = Node::create(['type' => 'book_item']);
          $book_item->enforceIsNew();

          $book_item->set('field_book_item_id', $book_id);
          $book_item->set('title', $title . ' Book #' . $book_id);

          // Update the condition and save the book node.
          $book_item->set('field_book_item_condition', $condition);
          // And the book node to the book item.
          $book_item->field_book->target_id = $book_entity->id();
          $book_item->status = 1;
          $book_item->save();

          // Save the book item to the book node.
          $book_entity->field_book_items[] = ['target_id' => $book_item->id()];
          // Resave the bok node.
          $book_entity->save();
          // count how many rows
          // saved successfully.
          $count['success']++;
        }
        else {
          // Skip this record bc
          // the book id is already
          // in the system.
          $count['skipped']++;
        }
      }
      // We want to delete the file when we are finsihed.
      file_delete($form_state->getValue('book_importer')[0]);
      // Save the form.
      drupal_set_message(t("The books were successfully imported to the system. Success: @success - Failed: @failed - Skipped: @skipped" , array('@success' => $count['success'], '@failed' => $count['failed'], '@skipped' => $count['skipped'])));
    }
    catch (\Exception $e) {
      // clean up the file if error was found.
      file_delete($form_state->getValue('book_importer')[0]);
      \Drupal::logger('book_management')->error($e->getMessage());
      drupal_set_message(t("There was an error while trying to import the csv file! Error: @error\n", array('@error' => $e->getMessage())), 'error');
    }
  }
}
