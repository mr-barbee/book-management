<?php
/**
 * @file
 * Contains \Drupal\book_management\Form\BookForm.
 */
namespace Drupal\book_management\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Url;

class BookForm extends FormBase {
  // The default delta is one.
  protected $book_item_deltas = [];
  protected $book_item_note_deltas = [];
  protected $book_entity;
  protected $book_item_entity;
  protected $next_book_id;
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

  protected function setBookItemNoteDeltas($book_id, $value) {
    $this->book_item_note_deltas[$book_id][$value] = $value;
  }

  public function getBookIteNotemDeltas() {
    return $this->book_item_note_deltas;
  }

  protected function setBookEntity($value) {
    $this->book_entity = $value;
  }

  public function getBookEntity() {
    return $this->book_entity;
  }

  protected function setNextBookId($value) {
    $this->next_book_id = $value;
  }

  public function getNextBookId() {
    return $this->next_book_id;
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
    $this->next_book_id = NULL;
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
      $image = '';
      // We want to add the default image to the form if it is set.
      if ($photo = !empty($this->book_entity->get('field_book_image')->getValue()) ? $this->book_entity->get('field_book_image')->getValue()[0] : FALSE) {
        $file = \Drupal\file\Entity\File::load($photo['target_id']);
        $url = \Drupal\image\Entity\ImageStyle::load('medium')->buildUrl($file->getFileUri());
        $image = '<img src="' . $url . '" />';
      }
      $form['image_markup'] = [
        '#markup' => '<div id="bookManagedFileImage">' . $image . '</div>',
      ];
      // Gather the list of form fields and
      // setting the default value based on
      // node data.
      $form['image'] = [
        '#type' => 'book_managed_file',
        '#upload_location' => 'public://book-images',
        '#multiple' => FALSE,
        '#description' => t('Allowed extensions: <em>png jpg jpeg</em>'),
        '#upload_validators' => [
          'file_validate_is_image' => [],
          'file_validate_extensions' => ['png jpg jpeg'],
          'file_validate_size' => [10485760]
        ],
        '#title' => t('Upload a Book Image'),
        '#default_value' => ['fid' => $photo ? $photo['target_id'] : '']
      ];
      $form['title'] = [
        '#type' => 'textfield',
        '#title' => t('Title:'),
        '#required' => TRUE,
        '#default_value' => $this->book_entity->get('title')->getString(),
      ];
      $form['isbn'] = [
        '#type' => 'textfield',
        '#title' => t('ISBN:'),
        '#maxlength' => 15,
        '#default_value' => $this->book_entity->get('field_book_isbn')->getString(),
      ];
      $form['book_id'] = [
        '#type' => 'textfield',
        '#title' => t('Book ID:'),
        '#required' => TRUE,
        '#maxlength' => 3,
        '#description' => $this->t('This book ID follows the Dewey Decimal System format. This <strong>cant</strong> be changed once set.'),
        '#default_value' => $this->book_entity->get('field_book_id')->getString(),
      ];
      if (!empty($this->book_entity->get('field_book_id')->getString())) {
        $form['book_id']['#attributes'] = ['disabled' => TRUE];
      }
      $form['subject'] = [
        '#type' => 'textfield',
        '#title' => t('Subject:'),
        '#default_value' => $this->book_entity->get('field_book_subject')->getString(),
      ];
      $form['grade'] = [
        '#type' => 'select',
        '#title' => t('Grade'),
        '#options' => $this->service->getTaxonomyIdFromVid('grade'),
        '#required' => TRUE,
        '#default_value' => $this->book_entity->get('field_book_grade')->getString(),
      ];
      $form['volume'] = [
        '#type' => 'textfield',
        '#title' => t('Volume:'),
        '#default_value' => $this->book_entity->get('field_book_volume')->getString(),
      ];
      $form['type'] = [
        '#type' => 'select',
        '#title' => t('Type'),
        '#options' => $this->service->getTaxonomyIdFromVid('book_type'),
        '#default_value' => $this->book_entity->get('field_book_type')->getString(),
      ];
      $form['category'] = [
        '#type' => 'select',
        '#title' => t('Category'),
        '#options' => $this->service->getTaxonomyIdFromVid('book_category'),
        '#required' => TRUE,
        '#default_value' => $this->book_entity->get('field_book_category')->getString(),
      ];
      $form['depreciated'] = [
        '#type' => 'checkbox',
        '#title' => ('Book is depreciated?'),
        '#default_value' => $this->book_entity->get('field_book_depreciated')->getString(),
      ];

      $form['book_item']['new'] = [
        '#prefix' => '<div id="book-items">',
        '#suffix' => '</div>',
      ];

      foreach ($this->book_item_deltas as $delta) {
        // Only retrieve the entity if is not a new one in the system.
        if (strpos($delta, '_new') === FALSE) {
          $book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($delta);
          if (!empty($book_item_entity)) {
            foreach ($book_item_entity->get('field_book_item_notes')->getValue() as $key => $note) {
              // Gather a list of book item note deltas saved to
              // the book item node.
              $this->setBookItemNoteDeltas($delta, $note['target_id']);
            }
            // Dont allow the node deletion if we have an active recod for any of its related book items.
            $allow_deletion = $allow_deletion && $book_item_entity->get('field_book_item_active_record')->getString() == 0 ? TRUE : FALSE;
            // Gather the link for the active record.
            $link = Link::fromTextAndUrl($book_item_entity->get('field_book_item_id')->getString(), Url::fromUserInput('/book-management/search-records?book_id=' . $book_item_entity->get('field_book_item_id')->getString()))->toString();
            $form['book_item']['old']['container'][$delta]['book_id_label_' . $delta] = [
             '#markup' => '<h3>' . $link . '</h3>',
            ];
            $form['book_item']['old']['container'][$delta]['book_id'] = [
              '#markup' => '<h3>' . $book_item_entity->get('field_book_item_id')->getString() . '</h3>',
            ];
            // This field will be diabled if we have an active record in the account
            // bc we dont want to be able to update the condition if its not currently
            // checked out.
            $form['book_item']['old']['container'][$delta]['condition_' . $delta] = [
              '#type' => 'select',
              '#options' => $this->service->getTaxonomyIdFromVid('conditions'),
              '#required' => TRUE,
              '#default_value' => $book_item_entity->get('field_book_item_condition')->getString(),
              '#disabled' => $book_item_entity->get('field_book_item_active_record')->getString() !== '0',
              '#description' => $this->t('This can only be changed if the book is Checked In.'),
            ];
            // Also only add the link to record if active record is set.
            $markup = $book_item_entity->get('field_book_item_active_record')->getString() !== '0' ?
              Link::fromTextAndUrl(t('Checked Out'), Url::fromUserInput('/book-management/search-records/' . $book_item_entity->get('field_book_item_active_record')->getString()))->toString() :
              $this->t('Checked In');
            $form['book_item']['old']['container'][$delta]['checked_status'] = [
             '#markup' => '<div><H5>' . $markup . '</h5></div>',
           ];
            $form['book_item']['old']['container'][$delta]['disallow_' . $delta] = [
              '#type' => 'checkbox',
              '#title' => ('Dont allow this book to be checked out.'),
              '#default_value' => $book_item_entity->get('field_book_item_disallow')->getString(),
            ];

            $form['book_item']['old']['container'][$delta]['book_notes']['new'] = [
              '#prefix' => '<div id="book-items-notes-' . $delta . '" >',
              '#suffix' => '</div>'
            ];

            // We want to loop through the note deltas and print them
            // to the page for each book item.
            if (!empty($this->book_item_note_deltas[$delta])) {
              foreach ($this->book_item_note_deltas[$delta] as $note_id) {
                // Check to see if this is a new note item.
                if (strpos($note_id, '_new') === FALSE) {
                  $p = Paragraph::load($note_id);
                  $text = $p->field_book_note->getValue();
                  if ($id = $p->field_book_note_record_id->getValue()[0]['value']) {
                    $markup = Link::fromTextAndUrl(t($text[0]['value']), Url::fromUserInput('/book-management/search-records/' . $id))->toString();
                  }
                  else {
                    $markup = $text[0]['value'];
                  }
                  $form['book_item']['old']['container'][$delta]['book_notes']['old'][$note_id]['note'] = [
                    '#markup' => '<p>' . $markup . '</p>'
                  ];
                  if (!empty($p->field_book_note_date)) {
                    $form['book_item']['old']['container'][$delta]['book_notes']['old'][$note_id]['date'] = [
                      '#markup' => '<p>' . $p->field_book_note_date->date->format('m/d/Y - g:ia') . '</p>'
                    ];
                  }
                }
                else {
                  $form['book_item']['old']['container'][$delta]['book_notes']['new'][$note_id]['note_' . $note_id . '_' . $delta] = [
                    '#type' => 'textarea',
                    '#attributes' => ['placeholder' => $this->t('Note')],
                    '#prefix' => '<div class="col-md-12">',
                    '#suffix' => '</div>',
                    '#rows' => 3,
                    '#col' => 2,
                  ];
                }
              }
            }

            $form['book_item']['old']['container'][$delta]['add_notes'] = [
              '#type' => 'submit',
              '#value' => $this->t('Add a Note'),
              '#limit_validation_errors' => [],
              '#submit' => ['::AddBookItemNotes'],
              '#ajax' => [
                'callback' => '::BookNoteFormAjaxCallback',
                'wrapper' => 'book-items-notes-' . $delta,
              ],
              '#name' => 'book_item_note_' . $delta
           ];

           // If there isnt an active record set than allow the user to delete the book item.
           if ($book_item_entity->get('field_book_item_active_record')->getString() == '0') {
             $form['book_item']['old']['container'][$delta]['delete'] = array (
               '#type' => 'link',
               '#title' => $this->t('Delete'),
               '#url' => Url::fromRoute('book_management.delete_book_item', ['bid' => $this->getBookEntity()->id(), 'nid' => $delta]),
             );
           }
          }
        }
        else {
          // Here we allow the user to add the book item to the book node.
          $form['book_item']['new']['container'][$delta] = [
            '#prefix' => '<hr /><div class="row">',
            '#suffix' => '</div>',
          ];
          $form['book_item']['new']['container'][$delta]['book_id_' . $delta] = [
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => $this->getNextBookId(),
            '#maxlength' => 7,
            '#description' => $this->t('This cant be changed once saved.<br /><strong>NOTE:</strong> Book ID Fromat: XXX-XXX => the book ID - the book count.'),
            '#attributes' => ['placeholder' => $this->t('Book Number')],
            '#prefix' => '<div class="col-md-4">',
            '#suffix' => '</div>',
          ];

          $form['book_item']['new']['container'][$delta]['condition_' . $delta] = [
            '#type' => 'select',
            '#options' => $this->service->getTaxonomyIdFromVid('conditions'),
            '#required' => TRUE,
            '#description' => $this->t('This can only be changed if the book is Checked In.'),
            '#prefix' => '<div class="col-md-4">',
            '#suffix' => '</div>',
          ];

          $form['book_item']['new']['container'][$delta]['checked_status'] = [
            '#markup' => '<div><h4>' . $this->t('Checked In') . '</h4></div>',
            '#prefix' => '<div class="col-md-4">',
            '#suffix' => '</div>',
          ];

          $form['book_item']['new']['container'][$delta]['disallow_' . $delta] = [
            '#type' => 'checkbox',
            '#title' => ('Dont allow this book to be checked out.'),
            '#prefix' => '<div class="col-md-12">',
            '#suffix' => '</div>',
          ];

          $form['book_item']['new']['container'][$delta]['note_1_new_' . $delta] = [
            '#type' => 'textarea',
            '#attributes' => ['placeholder' => $this->t('Note')],
            '#prefix' => '<div class="col-md-12">',
            '#suffix' => '</div>',
            '#rows' => 3,
            '#col' => 2,
         ];

          $form['book_item']['new']['container'][$delta]['remove'] = [
            '#type' => 'submit',
            '#value' => $this->t('remove'),
            '#limit_validation_errors' => [],
            '#submit' => ['::RemoveBookItem'],
            '#ajax' => [
              'callback' => '::BookFormAjaxCallback',
              'wrapper' => 'book-items',
            ],
            '#name' => 'book_item_' . $delta,
            '#prefix' => '<div class="col-md-12">',
            '#suffix' => '</div>'
         ];
        }
      }
      // Disable caching on this form.
      $form_state->setCached(FALSE);
      if (!empty($nid)) {
        $form['actions']['add_book_item'] = [
         '#type' => 'submit',
         '#value' => $this->t('Add a Book Item'),
         '#limit_validation_errors' => [],
         '#submit' => ['::AddBookItem'],
         '#ajax' => [
           'callback' => '::BookFormAjaxCallback',
           'wrapper' => 'book-items',
         ],
        ];
      }

      // Add the js to the form.
      $form['#attached']['library'][] = 'book_management/book_management';
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
       '#type' => 'submit',
       '#value' => $this->t('Save'),
       '#button_type' => 'primary',
      ];
      $form['actions']['cancel'] = [
       '#type' => 'submit',
       '#value' => $this->t('Cancel'),
       '#button_type' => 'secondary',
       '#limit_validation_errors' => [],
       '#submit' => ['::CancelEditBook'],
      ];
      if (!empty($nid)) {
        $form['actions']['export_books'] = [
          '#type' => 'submit',
          '#value' => $this->t('Export Books'),
          '#limit_validation_errors' => [],
          '#submit' => ['::ExportBooks']
        ];
      }

      if ($allow_deletion && $this->getBookEntity()->id() !== NULL) {
        $form['actions']['delete_book'] = [
         '#type' => 'link',
         '#title' => $this->t('Delete'),
         '#url' => Url::fromRoute('book_management.delete_book', ['nid' => $this->getBookEntity()->id()])
       ];
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to load the Book!\n"));
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
  public function BookNoteFormAjaxCallback($form, $form_state) {
    // Remove the triggering element fromt he deltas.
    $triggering_element = $form_state->getTriggeringElement()['#name'];
    $delta = str_replace('book_item_note_', '', $triggering_element);
    return $form['book_item']['old']['container'][$delta]['book_notes']['new'];
  }


  /**
   * {@inheritdoc}
   */
  public function ExportBooks(array &$form, FormStateInterface $form_state) {
    try {
      // Get the book entity.
      $book_node = $this->getBookEntity();
      $isbn = $book_node->get('field_book_isbn')->getValue()[0]['value'];
      $file_uri = 'public://book_importer/'. $book_node->id() . '_books.csv';
      //instead of writing down to a file we write to the output stream
      $fh = fopen($file_uri, 'w');
      // Make the CSV file headers.
      fputcsv($fh, [t('Book ID'), t('ISBN'), t('Condition'), t('Active Record')]);
      // Garher all book items and generate CSV.
      foreach ($book_node->get('field_book_items')->getValue() as $key => $book_item) {
        // Get the Book Item ID.
        $book_item = \Drupal::entityTypeManager()->getStorage('node')->load($book_item['target_id']);
        fputcsv($fh, [
          $book_item->get('field_book_item_id')->getValue()[0]['value'],
          $isbn,
          $this->service->getTaxonomyNameFromId('grade', $this->book_entity->get('field_book_grade')->getString()),
          $book_item->get('field_book_item_active_record')->getValue()[0]['value']
        ]);
      }
      //close the stream
      fclose($fh);
      // Generate the CSV file and download it to the client computer.
      $content = file_get_contents(\Drupal::service('file_system')->realpath($file_uri));
      $file_size = strlen($content);
      $file_name = $book_node->get('title')->getString();
      header('Content-Description: File Transfer');
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="' . $file_name . ' Books.csv"');
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . $file_size);
      flush();
      // dowload the file.
      echo($content);
      // after download we
      // want to delete the file.
      unlink($file_uri);
    } catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error trying to export the books!\n"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function AddBookItemNotes(array &$form, FormStateInterface $form_state) {
    // Remove the triggering element fromt he deltas.
    $triggering_element = $form_state->getTriggeringElement()['#name'];
    // Get the next book Item and save it to the form.
    // Return all the deltas and strip out the _new tag from created book items.
    $delta = str_replace('book_item_note_', '', $triggering_element);
    // Return all the deltas and strip out the _new tag from created book items.
    if (is_array($this->book_item_note_deltas[$delta])) {
      $output = array_map(function($val) { return str_replace('_new', '', $val); }, $this->book_item_note_deltas[$delta]);
    }
    // Increment the previous delta to get the new delta.
    $new_delta = !empty($output) ? max($output) + 1 : 1;
    // Add the _new tag back to the delta.
    $this->setBookItemNoteDeltas($delta, $new_delta . '_new');
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function AddBookItem(array &$form, FormStateInterface $form_state) {
    // Get the next book Item and save it to the form.
    $next_book_id = $this->getNextBookId();
    if ($book_id = $this->service->getNextBookId($this->book_entity, $next_book_id)) {
      $this->setNextBookId($book_id);
      // Return all the deltas and strip out the _new tag from created book items.
      $output = array_map(function($val) { return str_replace('_new', '', $val); }, $this->book_item_deltas);
      // Increment the previous delta to get the new delta.
      $new_delta = !empty($output) ? max($output) + 1 : 1;
      $new_delta = $new_delta . '_new';
      // Add the _new tag back to the delta.
      $this->book_item_deltas[$new_delta] = $new_delta;
      // Add the note to the deltas.
      $this->setBookItemNoteDeltas($new_delta, '1_new');
    }
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
    if (empty($form_state->getValue('book_id'))) {
      $form_state->setErrorByName('book_id', $this->t('This Field is Required.'));
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
      $image = !empty($values['image']) ? [
        'target_id' => $values['image'][0],
        'alt' => $this->t('Book photo'),
      ] : [];
      $book_node->set('field_book_image', $image);
      $book_node->set('field_book_isbn', $values['isbn']);
      $book_node->set('field_book_id', $values['book_id']);
      $book_node->set('field_book_subject', $values['subject']);
      $book_node->set('field_book_grade', [['target_id' => $values['grade']]]);
      $book_node->set('field_book_volume', $values['volume']);
      if (!empty($values['type'])) {
        $book_node->set('field_book_type', [['target_id' => $values['type']]]);
      }
      $book_node->set('field_book_category', [['target_id' => $values['category']]]);
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
          $book_item->set('field_book_item_id', $values['book_id_' . $delta]);
          $book_item->set('title', $values['title'] . ' Book #' . $values['book_id_' . $delta]);
        }
        // Update book item fields and save the book node.
        $book_item->set('field_book_item_condition', [['target_id' => $values['condition_' . $delta]]]);
        $book_item->set('field_book_item_disallow', $values['disallow_' . $delta]);
        // And the book node to the book item.
        $book_item->field_book->target_id = $book_node->id();
        $book_item->status = 1;
        // We want to save the note paragraphs.
        if (!empty($this->book_item_note_deltas[$delta])) {
          $book_notes = $book_item->get('field_book_item_notes')->getValue();
          // Loop through all the book notes.
          foreach ($this->book_item_note_deltas[$delta] as $note_id) {
            // make new note paragraph if the value is not empty.
            if (strpos($note_id, '_new') !== FALSE && !empty($values['note_' . $note_id . '_' . $delta])) {
              // Make the book item notes paragraph content type.
              $paragraph = Paragraph::create(['type' => 'book_item_notes']);
              $paragraph->set('field_book_note', $values['note_' . $note_id . '_' . $delta]);
              $paragraph->set('field_book_note_date', date('Y-m-d\TH:i:s', REQUEST_TIME));
              $paragraph->isNew();
              $paragraph->save();
              $book_notes[] = [
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId()
              ];
            }
          }
          // If the notes are not empty
          // add them to the book item.
          if (!empty($book_notes)) {
            $book_item->set('field_book_item_notes', $book_notes);
          }
        }
        // Save the book item.
        $book_item->save();
        // Only add the book delta if it isnt already set.
        if (!in_array($book_item->id(), $this->getBookItemDeltas())) {
          // Save the book item to the book node.
          $book_node->field_book_items[] = ['target_id' => $book_item->id()];
        }
      }
      // Resave the bok node.
      $book_node->save();
      if (\Drupal::routeMatch()->getRouteName() == 'book_management.add_book') {
        $message = $this->t("Book has been made!\n");
        $url = Url::fromUserInput('/book-management/edit-book/' . $book_node->id());
      }
      else {
        // Link to the edit book page.
        $book_link = Link::fromTextAndUrl(t($values['title']), Url::fromUserInput('/book-management/edit-book/' . $book_node->id()))->toString();
        $message = $this->t("Book @link saved!\n", array('@link' => $book_link));
        $url = Url::fromRoute('view.list_of_books.page_1');
      }
      \Drupal::messenger()->addStatus($message);
      $form_state->setRedirectUrl($url);
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error with the update!\n"));
    }

  }
}
