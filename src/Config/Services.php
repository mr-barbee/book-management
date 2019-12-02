<?php

namespace Drupal\book_management\Config;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;

/**
 *
 */
class Services {

  private $currentUser;
  private $connection;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $currentUser, Connection $connection) {
    $this->currentUser = $currentUser;
    $this->connection = $connection;
  }

  /**
   * Retrieve the list of students in the system.
   * @param  string $id
   *    The student ID we want to look up
   * @return array $students
   *    The array of students.
   */
  public function getListOfStudentsNames($id = NULL) {
    $students = $this->getStudents(array());
    foreach ($students as $uid => $student) {
      $students[$uid] = $student['name'];
    }
    return $students;
  }

  /**
   * Retrieve the list of students in the system.
   * @param  string $id
   *    The student ID we want to look up
   * @return array $students
   *    The array of students.
   */
  public function getAllStudents($limit = NULL) {
    return $this->getStudents(array());
  }

  /**
   * Retrieve the list of students in the system.
   * @param  string $id
   *    The student ID we want to look up
   * @return array $students
   *    The array of students.
   */
  private function getStudents($filters) {
    $students = array();
    try {
      // Load all the studnet users.
      $ids = \Drupal::entityQuery('user')
        ->condition('status', 1)
        ->condition('roles', 'student')
        ->execute();
      $users = User::loadMultiple($ids);
      // Collect a list of the users.
      if (is_array($users)) {
        foreach ($users as $key => $user) {
          $students[$user->id()] = array(
            'name' => $user->get('field_student_name')->getString(),
            'grade' => $user->get('field_student_grade')->getString()
          );
        }
      }
    }
    catch (Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return $students;
  }

  /**
   * [getAllBooks description]
   * @return [type] [description]
   */
  public function getAllBooks() {
    return $this->getBooks(array());
  }

  /**
   * [getBookByIsbn description]
   * @param  string $isbn [description]
   * @return [type]       [description]
   */
  public function getBookByIsbn(string $isbn) {
    // Load the book based on the Book ID
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['field_book_isbn' => $isbn]);
    // Make sure that node exists first.
    if ($node = reset($nodes)) {
      return $node;
    }
    return FALSE;
  }

  /**
   * [getBooks description]
   * @param  [type] $filters [description]
   * @return [type]          [description]
   */
  private function getBooks($filters) {
    $books =  array();
    try {
      $book_nids = \Drupal::entityQuery('node')->condition('type','book')->execute();
      $raw_books =  \Drupal\node\Entity\Node::loadMultiple($book_nids);
      // Gather a list of all the books in the system.
      foreach ($raw_books as $key => $raw_book) {
        $books[$raw_book->id()] = array (
          'title' => $raw_book->get('title')->getString(),
          'isbn' => $raw_book->get('field_book_isbn')->getString(),
          'subject' => $raw_book->get('field_book_subject')->getString(),
          'grade' => $raw_book->get('field_book_grade')->getString(),
          'depreciated' => $raw_book->get('field_book_depreciated')->getValue()[0]['value'] ? 'Yes' : 'No',
          'book_count' => count($raw_book->get('field_book_items')->getValue())
        );
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return $books;
  }

  /**
   * [getAllTransactionRecords description]
   * @return [type] [description]
   */
  public function getAllTransactionRecords() {
    return $this->getTransactionRecords(array());
  }

  /**
   * [getBookIdFormat description]
   * Book ID Fromat: XXX-XXXX => the book number - last 4 ISBN digits
   * @param  [type] $isbn       [description]
   * @param  [type] $book_number [description]
   * @return [type]             [description]
   */
  public function getBookIdFormat($isbn, $book_number) {
    return sprintf("%03d", $book_number) . '-' . substr($isbn, -4);
  }

  /**
   * [getStudentTransactionRecords description]
   * @param  [type] $uid [description]
   * @param  [type] $active [description]
   * @return [type]      [description]
   */
  public function getStudentTransactionRecords($uid, $active) {
    return $this->getTransactionRecords(array('uid' => $uid, 'acive' => $active));
  }

  /**
   * [getActiveTransactionRecords description]
   * @param  [type] $limit [description]
   * @return [type]        [description]
   */
  public function getActiveTransactionRecords($limit = NULL) {
    $filters = array( 'acive' => TRUE);
    if (isset($limit)) {
      $filters['limit'] = $limit;
    }
    return $this->getTransactionRecords($filters);
  }

  /**
   * [getTransactionRecordById description]
   * @param  [type] $rid [description]
   * @return [type]      [description]
   */
  public function getTransactionRecordById($rid) {
    return $this->getTransactionRecords(array('rid' => $rid));
  }

  /**
   * [getTransactionRecords description]
   * @param  string $value [description]
   * @return [type]        [description]
   */
  private function getTransactionRecords($filters) {
    $records =  array();
    try {
      // Create an object of type Select.
      $query = $this->connection->select('book_management_transaction_records', 'records');
      if (is_array($filters)) {
        foreach ($filters as $key => $filter) {
          switch ($key) {
            case 'rid':
              $query->condition('records.rid', $filter, '=');
              break;

            case 'uid':
              $query->condition('records.student_id', $filter, '=');
              break;

            case 'acive':
              $query->condition('records.check_in_date', NULL, 'IS NULL');
              break;

            case 'limit':
              $query->range(0, $filter);
              break;

            default:

              break;
          }
        }
      }
      $query->fields('records', ['rid', 'admin_id', 'student_id', 'book_nid', 'check_out_condition', 'check_out_date', 'check_in_condition', 'check_in_date']);
      // Gather results.
      $results = $query->execute();
      foreach ($results as $result) {
        $records[$result->rid] = array(
          'teacher' => $result->admin_id,
          'student' => $result->student_id,
          'book' => $result->book_nid,
          'check_out_condition' => $result->check_out_condition,
          'check_out_date' => $result->check_out_date,
          'check_in_condition' => $result->check_in_condition,
          'check_in_date' => $result->check_in_date,
        );
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return $records;
  }

  /**
   * Retrieve the list of conditions as
   * is its in the CMS.
   */
  public function getConditions() {
    return array(
      '' => '- Select Condition -',
      'excellent' => 'Excellent',
      'good' => 'Good',
      'bad' => 'Bad',
    );
  }

  /**
   * Retrieve the list of grades as
   * is its in the CMS.
   */
  public function getGrade() {
    return array(
      '' => '- Select -',
      'prek' => 'Preschool',
      '1' => '1st Grade',
      '2' => '2nd Grade',
      '3' => '3rd Grade',
      '4' => '4th Grade',
      '5' => '5th Grade',
      '6' => '6th Grade',
      '7' => '7th Grade',
      '8' => '8th Grade',
    );
  }
}
