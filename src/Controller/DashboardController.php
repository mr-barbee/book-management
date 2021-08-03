<?php
namespace Drupal\book_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides route responses for the Example module.
 */
class DashboardController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->service = \Drupal::service('book_management.services');
  }

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function mainDashboardCallback() {
    $books = array();
    $recent_checkout_books = array();
    $students = array();
    $record_count = 10;
    try {
      // Deny any page caching on the current request.
      \Drupal::service('page_cache_kill_switch')->trigger();
      // Get a list of all the books.
      $books = $this->service->getAllBooks();
      $first_ten_books = array_slice($books, 0, $record_count, TRUE);
      // Get recenlty checkout book records.
      $recent_checkout_books = $this->service->getActiveTransactionRecords();
      $first_ten_checkout_books = array_slice($recent_checkout_books, 0, $record_count, TRUE);
      // Get a list of students.
      $students = $this->service->getAllStudents();
      $ten_students = array_slice($students, 0, $record_count, TRUE);
      // Collect all relevent info from the records.
      foreach ($first_ten_checkout_books as $rid => $recent_checkout_book) {
        // Load the content from the system.
        $teacher = User::load($recent_checkout_book['teacher']);
        $student = User::load($recent_checkout_book['student']);
        $book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($recent_checkout_book['book']);
        $book_entity = \Drupal::entityTypeManager()->getStorage('node')->load($book_item_entity->get('field_book')->getString());
        // Add the updated data back to the array.
        $first_ten_checkout_books[$rid]['student'] = $student->get('field_student_name')->getString();
        $first_ten_checkout_books[$rid]['teacher'] = $teacher->get('field_student_name')->getString();
        // Format the checkout date to be displayed.
        $first_ten_checkout_books[$rid]['check_out_date'] = \Drupal::service('date.formatter')->format($recent_checkout_book['check_out_date'], 'custom', 'd/m/Y - H:s');
        // Set up the edit book link for the transaction title.
        $first_ten_checkout_books[$rid]['book'] = Link::fromTextAndUrl($book_item_entity->get('title')->getString(), Url::fromUserInput('/book-management/edit-book/' . $book_entity->id()))->toString();
      }
      // Set up all of the students and book urls.
      foreach ($ten_students as $uid => $student) {
        $ten_students[$uid]['name'] = Link::fromTextAndUrl($student['name'], Url::fromUserInput('/book-management/edit-student/' . $uid))->toString();
      }
      foreach ($first_ten_books as $nid => $book) {
        $first_ten_books[$nid]['title'] = Link::fromTextAndUrl($book['title'], Url::fromUserInput('/book-management/edit-book/' . $nid))->toString();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    $element = array(
      '#theme' => 'main_dashboard',
      '#book_count' => count($books),
      '#books' => isset($first_ten_books) ? $first_ten_books : array(),
      '#checkout_books_count' => count($recent_checkout_books),
      '#checkout_books' => isset($first_ten_checkout_books) ? $first_ten_checkout_books : array() ,
      '#student_count' => count($students),
      '#students' => isset($ten_students) ? $ten_students : array(),
    );
    return $element;
  }
}
