<?php

/**
 * @file
 * Contains \Drupal\book_management\Form\CheckInOut\CheckInBookForm.
 */

namespace Drupal\book_management\Form\CheckInOut;

use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
use Drupal\Core\Link;
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
        '#options' => $service->getTaxonomyIdFromVid('conditions'),
        '#required' => TRUE,
        '#default_value' => $this->book_item_entity->get('field_book_item_condition')->getString(),
      );

      if (!empty($this->book_item_entity->get('field_book_item_notes')->getValue())) {
        foreach ($this->book_item_entity->get('field_book_item_notes')->getValue() as $key => $note) {
          // Gather a list of book item note deltas saved to
          // the book item node.
          $p = Paragraph::load($note['target_id']);
          $text = $p->field_book_note->getValue();
          if ($id = $p->field_book_note_record_id->getValue()[0]['value']) {
            $markup = Link::fromTextAndUrl(t($text[0]['value']), Url::fromUserInput('/book-management/search-records/' . $id, ['attributes' => ['target' => '_blank']]))->toString();
          }
          else {
            $markup = $text[0]['value'];
          }
          $form['old_note'][$note['target_id']]['note'] = [
            '#markup' => '<p>' . $markup . '</p>'
          ];
          if (!empty($p->field_book_note_date)) {
            $form['old_note'][$note['target_id']]['date'] = [
              '#markup' => '<p>' . $p->field_book_note_date->date->format('m/d/Y - g:ia') . '</p>'
            ];
          }
        }
      }

      $form['note'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Note: (Optional)'),
        '#description' => $this->t('Adding a note will link the book note with this record'),
        '#rows' => 3,
        '#col' => 2,
     ];

      $form['warning'] = array(
       '#markup' => '<div>' . $this->t('Are you sure you want to <strong><u>CHECK IN</u></strong> this book!') . '</div><br />',
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to load the Book or record!\n"));
    }

    $form['actions']['submit']['#value'] = $this->t('Check In');

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
    $this->store->set('note', $form_state->getValue('note'));

    // Save the data
    parent::saveData();
    $form_state->setRedirectUrl(Url::fromRoute('book_management.check_book_status'));
  }
}
