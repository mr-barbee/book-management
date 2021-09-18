<?php
/**
 * @file
 * Contains \Drupal\book_management\Form\CheckInOut\CheckBookStatusForm.
 */
namespace Drupal\book_management\Form\CheckInOut;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class CheckBookStatusForm extends MakeRecordFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'check_status_book_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['book_id'] = [
     '#type' => 'textfield',
     '#title' => t('Book ID:'),
     '#description' => t('Check whether the book is currently Checked In or Out.'),
     '#required' => TRUE,
    ];

    $form['actions']['submit']['#value'] = $this->t('Check Out / In');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('book_id'))) {
      $form_state->setErrorByName('book_id', $this->t('This Field is Required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      // Load the book based on the Book ID
      $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties(['field_book_item_id' => $form_state->getValue('book_id')]);
      // Make sure that node exists first.
      if ($node = reset($nodes)) {
        // Set the node id to the form store object.
        $this->store->set('node_id', $node->id());
        // Gather the book entity from the book item.
        $book_entity = \Drupal::entityTypeManager()->getStorage('node')->load($node->get('field_book')->getString());
        // Make sure the book is checked in first.
        if ($node->get('field_book_item_active_record')->getString()) {
          // Get the book type from the system to determine if this is a consumable course.
          // Buisness rule is that consumables shouldnt be checked back in.
          $book_type = \Drupal::service('book_management.services')->getTaxonomyMachineNameFromId('book_type', $book_entity->get('field_book_type')->getString());
          // Make sure the book is not a consumable.
          if ($book_type !== 'c') {
            // Go to the checkout page form.
            $form_state->setRedirectUrl(Url::fromRoute('book_management.check_in_book'));
          }
          else {
            \Drupal::messenger()->addError(t("The Book ID @book is a consumable book! You cannot check this back in!\n", ['@book' => $form_state->getValue('book_id')]));
          }
        }
        else {
          // If the book is depreciated dont allow them to check it out.
          if ($book_entity->get('field_book_depreciated')->getValue()[0]['value'] || $node->get('field_book_item_disallow')->getValue()[0]['value']) {
            \Drupal::messenger()->addError(t("This book @book is not allowed to be checked out!\n", ['@book' => $form_state->getValue('book_id')]));
          }
          else {
            // otherwise check in the book.
            $form_state->setRedirectUrl(Url::fromRoute('book_management.check_out_book'));
          }
        }
      }
      else {
        \Drupal::messenger()->addError(t("The Book ID @book is not in the system!\n", ['@book' => $form_state->getValue('book_id')]));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to retrieve the Book!\n"));
    }
  }
}
