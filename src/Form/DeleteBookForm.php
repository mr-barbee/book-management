<?php
/**
 * @file
 * Contains \Drupal\book_management\Form\DeleteBookForm.
 */
namespace Drupal\book_management\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Link;
use Drupal\Core\Url;

class DeleteBookForm extends FormBase {

  protected $entity;
  protected $bid;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_book_form';
  }

  protected function setEntity($value) {
    $this->entity = $value;
  }

  public function getEntity() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $bid = NULL, $nid = NULL) {
    try {
      $this->bid = $bid;
      $entity = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $this->setEntity($entity);

      $form['title'] = [
       '#markup' => '<div>Title: ' . $this->t($entity->get('title')->getString()) . '</div>',
     ];
      if ($entity->bundle() == 'book_item') {
        $form['id'] = [
         '#markup' => '<div>ID: ' . $this->t($entity->get('field_book_item_id')->getString()) . '</div>',
       ];
        $form['warning'] = [
         '#markup' => '<div><strong>' . $this->t('Are you sure you want to delete this book item?<br/> This will DELETE all associated book notes!') . '</strong></div>',
       ];
      }
      elseif ($entity->bundle() == 'book') {
        $form['isbn'] = [
         '#markup' => '<div>ISBN: ' . $this->t($entity->get('field_book_isbn')->getString()) . '</div>',
       ];
        $form['warning'] = [
         '#markup' => '<div><strong>' . $this->t('Are you sure you want to delete this book?<br/> This will DELETE all associated book items and its notes!') . '</strong></div>',
       ];
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to load the Book!\n"));
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
     '#type' => 'submit',
     '#value' => $this->t('Delete'),
     '#button_type' => 'primary',
   ];
    $form['actions']['cancel'] = [
     '#type' => 'submit',
     '#value' => $this->t('Cancel'),
     '#button_type' => 'secondary',
     '#limit_validation_errors' => [],
     '#submit' => ['::CancelDeleteBook'],
   ];
    return $form;
  }


  /**
   * {@inheritdoc}
   * @param array              $form       [description]
   * @param FormStateInterface $form_state [description]
   */
  public function CancelDeleteBook(array &$form, FormStateInterface $form_state) {
    // Return to the edit book form.
    if (!empty($this->bid)) {
      $form_state->setRedirectUrl(Url::fromRoute('book_management.edit_book', ['nid' => $this->bid]));
    }
    else {
      $entity = $this->getEntity();
      $form_state->setRedirectUrl(Url::fromRoute('book_management.edit_book', ['nid' => $entity->id()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $entity = $this->getEntity();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $nid = $entity->id();

      if ($entity->bundle() == 'book_item') {
        // Remove the book item from the book content type.
        $book = \Drupal::entityTypeManager()->getStorage('node')->load($entity->get('field_book')->getString());
        foreach ($book->field_book_items as $key => $value) {
          if ($value->target_id == $nid) {
            unset($book->field_book_items[$key]);
          }
        }
        // We want to loop through all of the notes and delete them.
        foreach ($entity->get('field_book_item_notes')->getValue() as $key => $note) {
          $p = Paragraph::load($note['target_id']);
          if ($p) $p->delete();
        }
        // Resave the bok node.
        $book->save();
      }
      elseif ($entity->bundle() == 'book') {
        foreach ($entity->field_book_items as $key => $value) {
          // Delete the book item node associated with the entity.
          $node = $node_storage->load($value->target_id);
          // We want to loop through all of the notes and delete them.
          foreach ($node->get('field_book_item_notes')->getValue() as $key => $note) {
            $p = Paragraph::load($note['target_id']);
            if ($p) $p->delete();
          }
          $node->delete();
        }
      }
      // Delete the entity node.
      $node = $node_storage->load($nid);
      $node->delete();
      \Drupal::messenger()->addStatus(t("Book @id deleted!\n", ['@id' => $entity->get('title')->getString()]));
      if (!empty($this->bid)) {
        $form_state->setRedirectUrl(Url::fromRoute('book_management.edit_book', ['nid' => $this->bid]));
      }
      else {
        $form_state->setRedirectUrl(Url::fromRoute('view.list_of_books.page_1'));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      \Drupal::messenger()->addError(t("There was an error while trying to delete the Book!\n"));
    }
  }
}
