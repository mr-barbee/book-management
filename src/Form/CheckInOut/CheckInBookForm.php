<?php

/**
 * @file
 * Contains \Drupal\book_management\Form\CheckInOut\CheckInBookForm.
 */

namespace Drupal\book_management\Form\CheckInOut;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class CheckInBookForm extends MakeRecordFormBase {

  /**
   * @var \Drupal\entity
   */
  protected $book_item_entity;

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'check_in_book_form';
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
      $rid = $this->book_item_entity->get('field_book_item_active_record')->getString();
      $records = $service->getTransactionRecordById($rid);
      // Load the student from the CMS.
      $student = User::load($records[$rid]['student']);
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

      $form['student'] = array(
       '#markup' => '<div><label><strong>Student:</strong></label><p>' . $student->get('field_student_name')->value . '</p></div>',
      );

      $form['condition'] = array (
        '#type' => 'select',
        '#title' => t('Condition'),
        '#options' => $service->getConditions(),
        '#required' => TRUE,
        '#default_value' => $this->book_item_entity->get('field_book_item_condition')->getString(),
      );

      $form['warning'] = array(
       '#markup' => '<div><strong>' . $this->t('Are you sure you want to checkin this book!') . '</strong></div>',
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to load the Book or record!\n"));
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the relevant information to the store.
    $this->store->set('condition', $form_state->getValue('condition'));
    $this->store->set('active_record', $this->book_item_entity->get('field_book_item_active_record')->getString());

    // Save the data
    parent::saveData();
    $form_state->setRedirectUrl(Url::fromRoute('book_management.check_book_status'));
  }
}
