<?php
/**
 * @file
 * Contains \Drupal\book_management\Form\BookForm.
 */
namespace Drupal\book_management\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Url;

class BookForm extends FormBase {
  // The default delta is one.
  protected $book_item_deltas = array();
  protected $book_entity;
  protected $book_item_entity;
  protected $account;
  protected $service;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'book_form';
  }

  protected function setBookItemDelta($value) {
    $this->book_item_deltas[$value] = $value;
  }

  public function getBookItemDeltas() {
    return $this->book_item_deltas;
  }

  protected function setBookEntity($value) {
    $this->book_entity = $value;
  }

  public function getBookEntity() {
    return $this->book_entity;
  }

  protected function setBookItemEntity($value) {
    $this->book_item_entity = $value;
  }

  public function getBookItemEntity() {
    return $this->book_item_entity;
  }

  /**
   * Class constructor.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
    // Load a empty book and book itme entity.
    $this->book_entity = Node::create(['type' => 'book']);
    $this->book_entity->enforceIsNew();
    $this->book_item_entity = Node::create(['type' => 'book_item']);
    $this->service = \Drupal::service('book_management.services');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    try {
      // Get current user data.
      $uid = $this->account->id();
      // Set default to TRUE to allow
      // the user to delete the node.
      $allow_deletion = TRUE;
      // Load the book entity if set.
      if (!empty($nid)) {
        if ($book_entity = \Drupal::entityTypeManager()->getStorage('node')->load($nid)){
          // if the book entity is set save it
          // to the form class and the list of deltas.
          $this->setBookEntity($book_entity);
          foreach ($this->book_entity->get('field_book_items')->getValue() as $key => $book_item) {
            // Gather a list of book item deltas saved to the node.
            $this->setBookItemDelta($book_item['target_id']);
          }
        }
      }
      // Gather the list of form fields and
      // setting the default value based on
      // node data.
      $form['title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title:'),
        '#required' => TRUE,
        '#default_value' => $this->book_entity->get('title')->getString(),
      );
      $form['isbn'] = array(
        '#type' => 'textfield',
        '#title' => t('ISBN:'),
        '#required' => TRUE,
        '#maxlength' => 13,
        '#default_value' => $this->book_entity->get('field_book_isbn')->getString(),
      );
      $form['subject'] = array (
        '#type' => 'textfield',
        '#title' => t('Subject:'),
        '#default_value' => $this->book_entity->get('field_book_subject')->getString(),
      );
      $form['grade'] = array (
        '#type' => 'select',
        '#title' => t('Grade'),
        '#options' => $this->service->getGrade(),
        '#required' => TRUE,
        '#default_value' => $this->book_entity->get('field_book_grade')->getString(),
      );
      $form['volume'] = array (
        '#type' => 'textfield',
        '#title' => t('Volume:'),
        '#default_value' => $this->book_entity->get('field_book_volume')->getString(),
      );
      $form['depreciated'] = array (
        '#type' => 'checkbox',
        '#title' => ('Book is depreciated.'),
        '#default_value' => $this->book_entity->get('field_book_depreciated')->getString(),
      );

      $form['book_item']['new'] = array(
        '#prefix' => '<div id="book-items">',
        '#suffix' => '</div>',
      );

      foreach ($this->book_item_deltas as $delta) {
        // Only retrieve the entity if is not a new one in the system.
        if (strpos($delta, '_new') === FALSE) {
          $book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($delta);
          // Dont allow the node deletion if we have an active recod for any of its related book items.
          $allow_deletion = $allow_deletion && $book_item_entity->get('field_book_item_active_record')->getString() == 0 ? TRUE : FALSE;
          // Gather the link for the active record.
          $link = Link::fromTextAndUrl($book_item_entity->get('field_book_item_id')->getString(), Url::fromUserInput('/book-management/search-records?book_id=' . $book_item_entity->get('field_book_item_id')->getString()))->toString();
          $form['book_item']['old']['container_' . $delta]['book_id_label_' . $delta] = array(
           '#markup' => '<h3>' . $link . '</h3>',
          );
          // This field will be diabled if we have an active record in the account
          // bc we dont want to be able to update the condition if its not currently
          // checked out.
          $form['book_item']['old']['container_' . $delta]['condition_' . $delta] = array (
            '#type' => 'select',
            '#options' => $this->service->getConditions(),
            '#required' => TRUE,
            '#default_value' => $book_item_entity->get('field_book_item_condition')->getString(),
            '#disabled' => $book_item_entity->get('field_book_item_active_record')->getString() !== '0',
            '#description' => $this->t('This can only be changed if the book is Checked In.'),
          );
          // Also only add the link to record if active record is set.
          $markup = $book_item_entity->get('field_book_item_active_record')->getString() !== '0' ?
            Link::fromTextAndUrl(t('Checked Out'), Url::fromUserInput('/book-management/search-records/' . $book_item_entity->get('field_book_item_active_record')->getString()))->toString() :
            $this->t('Checked In');
          $form['book_item']['old']['container_' . $delta]['checked_status'] = array(
           '#markup' => '<div><H5>' . $markup . '</h5></div>',
          );
          // If there isnt an active record set than allow the user to delete the book item.
          if ($book_item_entity->get('field_book_item_active_record')->getString() == '0') {
            $form['book_item']['old']['container_' . $delta]['delete'] = array (
              '#type' => 'link',
              '#title' => $this->t('Delete'),
              '#url' => Url::fromRoute('book_management.delete_book_item', ['bid' => $this->getBookEntity()->id(), 'nid' => $delta]),
           );
          }
        }
        else {
          // Here we allow the user to add the book item to the book node.
          $form['book_item']['new']['container_' . $delta] = array(
            '#prefix' => '<div class="row">',
            '#suffix' => '</div>',
          );

          $form['book_item']['new']['container_' . $delta]['book_id_' . $delta] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#maxlength' => 3,
            '#description' => $this->t('This cant be changed once saved. You <strong>ONLY</strong> need to enter in the book number. Up-to 3-digits (XXX).'),
            '#attributes' => array('placeholder' => $this->t('Book Number')),
            '#prefix' => '<div class="col-md-4">',
            '#suffix' => '</div>',
          );

          $form['book_item']['new']['container_' . $delta]['condition_' . $delta] = array (
            '#type' => 'select',
            '#options' => $this->service->getConditions(),
            '#required' => TRUE,
            '#description' => $this->t('This can only be changed if the book is Checked In.'),
            '#prefix' => '<div class="col-md-4">',
            '#suffix' => '</div>',
          );

          $form['book_item']['new']['container_' . $delta]['checked_status'] = array(
            '#markup' => '<div><h4>' . $this->t('Checked In') . '</h4></div>',
            '#prefix' => '<div class="col-md-4">',
            '#suffix' => '</div>',
          );

          $form['book_item']['new']['container_' . $delta]['remove'] = array (
            '#type' => 'submit',
            '#value' => $this->t('remove'),
            '#limit_validation_errors' => array(),
            '#submit' => array('::RemoveBookItem'),
            '#ajax' => array(
              'callback' => '::BookFormAjaxCallback',
              'wrapper' => 'book-items',
            ),
            '#name' => 'book_item_' . $delta,
            '#prefix' => '<div class="col-md-12">',
            '#suffix' => '</div>'
         );
        }
      }
      // Disable caching on this form.
      $form_state->setCached(FALSE);

      $form['actions']['add_book_item'] = array(
       '#type' => 'submit',
       '#value' => $this->t('Add a Book Item'),
       '#limit_validation_errors' => array(),
       '#submit' => array('::AddBookItem'),
       '#ajax' => array(
         'callback' => '::BookFormAjaxCallback',
         'wrapper' => 'book-items',
       ),
      );

      // Add the js to the form.
      $form['#attached']['library'][] = 'book_management/book_management';
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
       '#type' => 'submit',
       '#value' => $this->t('Save'),
       '#button_type' => 'primary',
      );
      $form['actions']['cancel'] = array(
       '#type' => 'submit',
       '#value' => $this->t('Cancel'),
       '#button_type' => 'secondary',
       '#limit_validation_errors' => array(),
       '#submit' => array('::CancelEditBook'),
      );

      if ($allow_deletion && $this->getBookEntity()->id() !== NULL) {
        $form['actions']['delete_book'] = array (
         '#type' => 'link',
         '#title' => $this->t('Delete'),
         '#url' => Url::fromRoute('book_management.delete_book', ['nid' => $this->getBookEntity()->id()])
       );
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      drupal_set_message(t("There was an error while trying to load the Book!\n"), 'error');
    }

    /**
     * [return description]
     * @todo theme the add book form.
     */
    $form['#theme'] = 'book_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function BookFormAjaxCallback($form, $form_state) {
    return $form['book_item']['new'];
  }

  /**
   * {@inheritdoc}
   */
  public function AddBookItem(array &$form, FormStateInterface $form_state) {
    // Return all the deltas and strip out the _new tag from created book items.
    $output = array_map(function($val) { return str_replace('_new', '', $val); }, $this->book_item_deltas);
    // Increment the previous delta to get the new delta.
    $new_delta = !empty($output) ? max($output) + 1 : 1;
    // Add the _new tag back to the delta.
    $this->book_item_deltas[$new_delta . '_new'] = $new_delta . '_new';
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function RemoveBookItem(array &$form, FormStateInterface $form_state) {
    // Remove the triggering element fromt he deltas.
    $triggering_element = $form_state->getTriggeringElement()['#name'];
    // Remove the book item based on delta.
    $delta_remove = str_replace('book_item_', '', $triggering_element);
    unset($this->book_item_deltas[$delta_remove]);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   * @param array              $form       [description]
   * @param FormStateInterface $form_state [description]
   */
  public function CancelEditBook(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('view.list_of_books.page_1'));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('title'))) {
      $form_state->setErrorByName('title', $this->t('This Field is Required.'));
    }
    if (empty($form_state->getValue('isbn'))) {
      $form_state->setErrorByName('isbn', $this->t('This Field is Required.'));
    }
    if (empty($form_state->getValue('subject'))) {
      $form_state->setErrorByName('subject', $this->t('This Field is Required.'));
    }
    if (empty($form_state->getValue('grade'))) {
      $form_state->setErrorByName('grade', $this->t('This Field is Required.'));
    }
    foreach ($this->book_item_deltas as $delta) {
      // Check all of the deltas and sort out all
      // off the new deltas created and make sure the book id
      // and condition is set.
      if (strpos($delta, '_new') !== FALSE) {
        if (empty($form_state->getValue('book_id_' . $delta))) {
          $form_state->setErrorByName('book_id_' . $delta, $this->t('This Field is Required.'));
        }
        if (empty($form_state->getValue('condition_' . $delta))) {
          $form_state->setErrorByName('condition_' . $delta, $this->t('This Field is Required.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the values from the form state.
    $values = $form_state->getValues();
    try {
      // Make the book node and save all the fields from
      // the form state values.
      $book_node = $this->getBookEntity();
      $book_node->set('title', $values['title']);
      $book_node->set('field_book_isbn', $values['isbn']);
      $book_node->set('field_book_subject', $values['subject']);
      $book_node->set('field_book_grade', $values['grade']);
      $book_node->set('field_book_volume', $values['volume']);
      $book_node->set('field_book_depreciated', $values['depreciated']);
      $book_node->status = 1;
      $book_node->save();
      // Loop through all of the book item deltas.
      foreach ($this->book_item_deltas as $delta) {
        // Make a new book item and save all the fields.
        // Else then its an existing book item so update it.
        if (strpos($delta, '_new') === FALSE) {
          $book_item = \Drupal::entityTypeManager()->getStorage('node')->load($delta);
          $book_item->set('title', $values['title'] . ' Book #' . $book_item->get('field_book_item_id')->getString());
        }
        else {
          $book_item = Node::create(['type' => 'book_item']);
          $book_item->enforceIsNew();
          // Format the Book ID value.
          $book_id = $this->service->getBookIdFormat($this->book_entity->get('field_book_isbn')->getString(), $values['book_id_' . $delta]);
          $book_item->set('field_book_item_id', $book_id);
          $book_item->set('title', $values['title'] . ' Book #' . $book_id);
        }
        // Update the condition and save the book node.
        $book_item->set('field_book_item_condition', $values['condition_' . $delta]);
        // And the book node to the book item.
        $book_item->field_book->target_id = $book_node->id();
        $book_item->status = 1;
        $book_item->save();
        // Only add the book delta if it isnt already set.
        if (!in_array($book_item->id(), $this->getBookItemDeltas())) {
          // Save the book item to the book node.
          $book_node->field_book_items[] = ['target_id' => $book_item->id()];
        }
      }
      // Resave the bok node.
      $book_node->save();
      // Link to the edit book page.
      $book_link = Link::fromTextAndUrl(t($values['title']), Url::fromUserInput('/book-management/edit-book/' . $book_node->id()))->toString();
      drupal_set_message(t("Book @link saved!\n", array('@link' => $book_link)));
      $form_state->setRedirectUrl(Url::fromRoute('view.list_of_books.page_1'));
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      drupal_set_message(t("There was an error with the update!\n"), 'error');
    }

  }
}
