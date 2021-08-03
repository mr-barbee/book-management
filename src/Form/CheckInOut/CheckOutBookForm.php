<?php

/**
 * @file
 * Contains \Drupal\book_management\Form\CheckInOut\CheckOutBookForm.
 */

namespace Drupal\book_management\Form\CheckInOut;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Url;

class CheckOutBookForm extends MakeRecordFormBase {

  /**
   * @var \Drupal\entity
   */
  protected $book_item_entity;

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'check_out_book_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    try {
      $form = parent::buildForm($form, $form_state);
      // Load the book based on the Book ID
      $this->book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($this->store->get('node_id'));
      // Used to retrieve the CMS info.
      $service = \Drupal::service('book_management.services');
      // Gather the book entity from the book item.
      $book_entity = \Drupal::entityTypeManager()->getStorage('node')->load($this->book_item_entity->get('field_book')->getString());

      $form['book_id'] = array(
       '#markup' => '<div><label><strong>Book ID:</strong></label><p>' . $this->book_item_entity->get('field_book_item_id')->getString() . '</p></div>',
      );

      $form['title'] = array(
       '#markup' => '<div><label><strong>Title:</strong></label><p>' . $book_entity->get('title')->getString() . '</p></div>',
      );

      $form['volume'] = array(
       '#markup' => '<div><label><strong>Volume:</strong></label><p>' . $book_entity->get('field_book_volume')->getString() . '</p></div>',
      );

      $form['condition'] = array (
       '#type' => 'select',
       '#title' => $this->t('Condition'),
       '#options' => $service->getConditions(),
       '#required' => TRUE,
        '#default_value' => $this->book_item_entity->get('field_book_item_condition')->getString(),
      );

      $form['student'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Student'),
        '#autocomplete_route_name' => 'book_management.autocomplete.students',
        '#required' => TRUE
      ];

      $form['warning'] = array(
       '#markup' => '<div><strong>' . $this->t('Are you sure you want to checkout this book!') . '</strong></div>',
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to load the Book!\n"));
    }

    $form['actions']['previous'] = array(
      '#type' => 'link',
      '#title' => $this->t('Previous'),
      '#attributes' => array(
        'class' => array('button'),
      ),
      '#weight' => 0,
      '#url' => Url::fromRoute('book_management.check_book_status'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('student'))) {
      $form_state->setErrorByName('student', $this->t('This Field is Required.'));
    }
    else if (empty(EntityAutocomplete::extractEntityIdFromAutocompleteInput($form_state->getValue('student')))) {
      $form_state->setErrorByName('student', $this->t('This Field student name is invalid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Check to make sure the book is not depreciated.
    $book_entity = \Drupal::entityTypeManager()->getStorage('node')->load($this->book_item_entity->field_book->target_id);
    if ($book_entity->get('field_book_depreciated')->getString() == FALSE) {
       // Extracts the entity ID from the autocompletion result.
      $student = EntityAutocomplete::extractEntityIdFromAutocompleteInput($form_state->getValue('student'));
      $this->store->set('student', $student);
      $this->store->set('condition', $form_state->getValue('condition'));
      $this->store->set('active_record', 0);
      // Save the data
      parent::saveData();
      $form_state->setRedirectUrl(Url::fromRoute('book_management.check_book_status'));
    }
    else {
      \Drupal::messenger()->addError(t("This book is currently depreciated and cannot be checked out!\n"));
    }
  }
}
