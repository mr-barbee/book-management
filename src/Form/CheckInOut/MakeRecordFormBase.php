<?php

/**
 * @file
 * Contains \Drupal\book_management\Form\CheckInOut\MakeRecordFormBase.
 */

namespace Drupal\book_management\Form\CheckInOut;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class MakeRecordFormBase extends FormBase {

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a \Drupal\book_management\Form\CheckInOut\MakeRecordFormBase.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Database\Connection $connection
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user, Connection $connection) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->connection = $connection;

    $this->store = $this->tempStoreFactory->get('checkinout_data');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('current_user'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array();
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#weight' => 10,
    );

    return $form;
  }

  /**
   * Saves the data from the multistep form.
   */
  protected function saveData() {
    try {
      // Make a record in the records table to log transaction.
      // Load the transaction record if it is set.
      if ($active_record = $this->store->get('active_record')) {
        $result = $this->connection->update('book_management_transaction_records')
          ->fields([
            'check_in_condition' => $this->store->get('condition'),
            'check_in_date' => REQUEST_TIME,
          ])
          ->condition('rid', $active_record , '=')
          ->execute();
        // Reset the active record
        // to zero for the Book Node.
        $active_record = 0;
      }
      else {
        $result = $this->connection->insert('book_management_transaction_records')
          ->fields([
            'admin_id' => $this->currentUser->id(),
            'student_id' => $this->store->get('student'),
            'book_nid' => $this->store->get('node_id'),
            'check_out_condition' => $this->store->get('condition'),
            'check_out_date' => REQUEST_TIME,
            'check_in_condition' => NULL,
            'check_in_date' => NULL,
          ])
          ->execute();
        // Save the Record ID to the Node.
        $active_record = $result;
      }
      // Update the book item check status to "Checked Out".
      $book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($this->store->get('node_id'));
      $book_item_entity->set('field_book_item_active_record', $active_record);
      $book_item_entity->set('field_book_item_condition',  $this->store->get('condition'));
      $book_item_entity->save();

      $this->deleteStore();
      \Drupal::messenger()->addStatus($this->t('The book was checked successfully.'));
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to save the record!\n"));
    }
  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
   */
  protected function deleteStore() {
    $keys = ['node_id', 'student', 'condition', 'active_record'];
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }
}
