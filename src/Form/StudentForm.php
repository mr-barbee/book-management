<?php
/**
 * @file
 * Contains \Drupal\book_management\Form\StudentForm.
 */
namespace Drupal\book_management\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Link;
use Drupal\Core\Url;

class StudentForm extends FormBase {

  protected $student;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_book_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, $uid = NULL) {
    try {
      $active_records = array();
      if (!empty($uid)) {
        $student = User::load($uid);
        if (!empty($student)) {
          $this->student = $student;
          $active_records = $this->service->getStudentTransactionRecords($uid, TRUE);
        }
      }
      $form['name'] = array (
       '#type' => 'textfield',
       '#title' => t('Name:'),
       '#required' => TRUE,
       '#default_value' => isset($student) ? $student->get('field_student_name')->getString() : NULL,
      );
      $form['grade'] = array (
       '#type' => 'select',
       '#title' => t('Grade'),
       '#options' => $this->service->getGrade(),
       '#required' => TRUE,
       '#default_value' => isset($student) ? $student->get('field_student_grade')->getString(): NULL,
      );

      $form['book_item'] = array(
        '#type' => 'container',
        '#attributes' => array('id' => 'book-items'),
        '#prefix' => '<div class="book-items"><h4>Actively Checked Out Books:</h4>',
        '#suffix' => '</div>',
      );

      foreach ($active_records as $rid => $active_record) {
        $book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($active_record['book']);

        if (isset($book_item_entity)) {
          $form['book_item']['container_' . $rid] = array(
            '#prefix' => '<div class="row">',
            '#suffix' => '</div>',
          );
          // Gather a list of links for the page.
          $link = Link::fromTextAndUrl($book_item_entity->get('title')->getString(), Url::fromUserInput('/book-management/search-records/' . $book_item_entity->get('field_book_item_active_record')->getString()))->toString();
          $form['book_item']['container_' . $rid]['book_id_label' . $rid] = array(
           '#markup' => '<div><a<h5>' . $link . '</h5></div>',
           '#prefix' => '<div class="col-md-12">',
           '#suffix' => '</div>',
          );
        }
      }

    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to load the User!\n"));
    }

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
     '#submit' => array('::CancelStudent'),
    );
    return $form;
  }


  /**
   * {@inheritdoc}
   * @param array              $form       [description]
   * @param FormStateInterface $form_state [description]
   */
  public function CancelStudent(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('view.list_of_students.all_students'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the values from the form state.
    $values = $form_state->getValues();
    try {
      $student = $this->student;
      if (empty($student)) {
        $student = User::create();
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        // Mandatory settings not used currently.
        $student->setPassword('T!es$tud3nT8437');
        $student->enforceIsNew();
        $student->addRole('student');
        // Optional settings
        $student->set('langcode', $language);
        $student->set('preferred_langcode', $language);
        $student->set('preferred_admin_langcode', $language);
        $student->activate();
      }
      // Get a custom name for the student.
      $email_name = filter_var($values['name'], FILTER_SANITIZE_EMAIL);
      $student->setEmail($email_name . '@' . \Drupal::request()->getHost());
      $student->setUsername($email_name . REQUEST_TIME);
      $student->set('field_student_name', $values['name']);
      $student->set('field_student_grade', $values['grade']);
      // Save user
      if ($student->save()) {
        \Drupal::messenger()->addStatus(t("Ther Student saved successfully!\n"));
        $form_state->setRedirectUrl(Url::fromRoute('view.list_of_students.all_students'));
      }
      else {
        \Drupal::messenger()->addError(t("There was an error while trying to save the User!\n"));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to save the User!\n"));
    }
  }
}
