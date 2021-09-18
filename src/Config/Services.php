<?php

namespace Drupal\book_management\Config;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
    $students = $this->getStudents([]);
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
    return $this->getStudents([]);
  }

  /**
   * Retrieve the list of book notes in the system.
   * @param  string $id
   *    The student ID we want to look up
   * @return array $students
   *    The array of students.
   */
  public function getAllBookNotes($limit = NULL) {
    return $this->getBookNotes(['limit' => $limit]);
  }

  /**
   * Retrieve the list of book notes in the system.
   * @param  array $filters
   *    The student ID we want to look up
   */
  private function getBookNotes($filters) {
    $book_notes = [];
    try {
      // Load all the book notes.
      $query = $this->connection->select('paragraphs_item', 'p');
      $query->condition('p.type', 'book_item_notes', '=')
         ->fields('p', ['id']);
      $query->leftJoin('paragraph__field_book_note', 'note', 'note.entity_id = p.id');
      $query->leftJoin('paragraph__field_book_note_date', 'date', 'date.entity_id = p.id');
      $query->fields('note', ['field_book_note_value']);
      $query->fields('date', ['field_book_note_date_value']);
      $query->orderBy('date.field_book_note_date_value', 'DESC');
      if (!empty($filters['limit'])) {
        $query->range(0, $filters['limit']);
      }
      $result = $query->execute();

      foreach ($result as $record) {
        if (!empty($record->field_book_note_date_value)) {
          // Gather the paragraph items.
          $paragraph = Paragraph::load($record->id);
          $parent = $paragraph->getParentEntity();
          if (isset($parent)) {
            $book_notes[$record->id] = [
              'book_link' => Link::fromTextAndUrl(t($parent->get('title')->getValue()[0]['value']), Url::fromUserInput('/book-management/individual-book-listing?book_id=' . $parent->get('field_book_item_id')->getValue()[0]['value']))->toString(),
              'date' => \Drupal::service('date.formatter')->format(strtotime($record->field_book_note_date_value), 'custom', 'm/d/Y - g:ia'),
              'note' => $record->field_book_note_value
            ];
          }

        }
      }
    }
    catch (Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return $book_notes;
  }

  /**
   * Retrieve the list of students in the system.
   * @param  string $id
   *    The student ID we want to look up
   * @return array $students
   *    The array of students.
   */
  private function getStudents($filters) {
    $students = [];
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
          $students[$user->id()] = [
            'name' => $user->get('field_student_name')->getString(),
            'grade' => $this->getTaxonomyNameFromId('grade', $user->get('field_student_grade')->getString())
          ];
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
    return $this->getBooks([]);
  }

  /**
   * [getBookByIsbn description]
   * @param  int $id [description]
   * @return [type]       [description]
   */
  public function getBookById($id) {
    try {
      // Load the book based on the Book ID
      $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties(['field_book_id' => $id]);
      // Make sure that node exists first.
      if ($node = reset($nodes)) {
        return $node;
      }
      return FALSE;
    } catch (\Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * [getBooks description]
   * @param  [type] $filters [description]
   * @return [type]          [description]
   */
  private function getBooks($filters) {
    $books =  [];
    try {
      $book_nids = \Drupal::entityQuery('node')->condition('type','book')->sort('created' , 'DESC')->execute();
      $raw_books =  \Drupal\node\Entity\Node::loadMultiple($book_nids);
      // Gather a list of all the books in the system.
      foreach ($raw_books as $key => $raw_book) {
        $books[$raw_book->id()] = [
          'title' => $raw_book->get('title')->getString(),
          'isbn' => $raw_book->get('field_book_isbn')->getString(),
          'subject' => $raw_book->get('field_book_subject')->getString(),
          'grade' => $raw_book->get('field_book_grade')->getString(),
          'depreciated' => $raw_book->get('field_book_depreciated')->getValue()[0]['value'] ? 'Yes' : 'No',
          'book_count' => count($raw_book->get('field_book_items')->getValue())
        ];
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
    return $this->getTransactionRecords([]);
  }

  /**
   * [getLastBookId description]
   * @param  object $book_entity          [description]
   * @return string $book_id              [description]
   */
  public function getNextBookId($book_entity, $book_id = NULL) {
    if (empty($book_id)) {
      $book_ids = [];
      foreach ($book_entity->get('field_book_items')->getValue() as $key => $book_item) {
        // Gather a list of book item deltas saved to the node.
        $book_item_entity = \Drupal::entityTypeManager()->getStorage('node')->load($book_item['target_id']);
        if (!empty($book_item_entity)) {
          $book_ids[] = $book_item_entity->get('field_book_item_id')->getString();
        }
      }
      // if we dont have any books set yet
      // then we can default to the first book.
      if (empty($book_ids)) {
        return $this->getBookIdFormat($book_entity->get('field_book_id')->getString(), 1);
      }
      // Sort book ID's
      // from high to low.
      arsort($book_ids);
      $book_id = array_shift($book_ids);
    }
    // Get the book number and increamment by 1;
    $book_count = explode('-', $book_id)[1];
    $next_id_number = (int) $book_count + 1;
    // We only want to return the book ID if there is less than 1000 count.
    return $next_id_number < 1000 ? $this->getBookIdFormat($book_entity->get('field_book_id')->getString(), $next_id_number) : FALSE;

  }

  /**
   * [getBookIdFormat description]
   * Book ID Fromat: XXX-XXX => the book ID - the stock count
   * @param  [type] $book_id       [description]
   * @param  [type] $book_number [description]
   * @return [type]             [description]
   */
  protected function getBookIdFormat($book_id, $book_number) {
    return $book_id . '-' . sprintf("%03d", $book_number);
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
    $records =  [];
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
      // We always want to sort by checkout date.
      $query->orderBy('records.check_out_date', 'DESC');
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
   * Retrieve the list of options as is its in the CMS.
   */
  public function getTaxonomyIdFromVid($vid) {
    $options = [
      '' => '- Select -'
    ];
    if (empty($vid)) {
      \Drupal::logger('book_management')->error('Taxonomy ID not specified.');
      return FALSE;
    }
    try {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid, 0, NULL, TRUE);
      // Update the empty option with the name of the taxonmy.
      $options[''] = '- Select ' . \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($terms[0]->bundle())->label() . ' -';
      foreach ($terms as $term) {
        $options[$term->id()] = $term->get('name')->getValue()[0]['value'];
      }
    }
    catch (Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return $options;
  }

  /**
   * [getTaxonomyIdFromMachineName description]
   * @param  [type] $vid               [description]
   * @param  [type] $value                  [description]
   * @return [type]           [description]
   */
  public function getTaxonomyIdFromMachineName($vid, $value) {
    try {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid, 0, NULL, TRUE);
      switch ($vid) {
        case 'grade':
          foreach ($terms as $term) {
            if ($term->get('field_grade_machine_name')->getValue()[0]['value'] == strtolower($value)) {
              return $term->id();
            }
          }
          break;
        case 'book_category':
          foreach ($terms as $term) {
            if ($term->get('field_category_machine_name')->getValue()[0]['value'] == strtolower($value)) {
              return $term->id();
            }
          }
          break;
        case 'book_type':
          foreach ($terms as $term) {
            if ($term->get('field_type_machine_name')->getValue()[0]['value'] == strtolower($value)) {
              return $term->id();
            }
          }
          break;
        default:
          \Drupal::logger('book_management')->error('Taxonomy ID not specified.');
          break;
      }
    }
    catch (Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return FALSE;
  }

  /**
   * [getTaxonomyIdFromMachineName description]
   * @param  [type] $vid               [description]
   * @param  [type] $value                  [description]
   * @return [type]           [description]
   */
  public function getTaxonomyMachineNameFromId($vid, $value) {
    try {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid, 0, NULL, TRUE);
      switch ($vid) {
        case 'grade':
          foreach ($terms as $term) {
            if ($term->id()== $value) {
              return $term->get('field_grade_machine_name')->getValue()[0]['value'];
            }
          }
          break;
        case 'book_category':
          foreach ($terms as $term) {
            if ($term->id()== $value) {
              return $term->get('field_category_machine_name')->getValue()[0]['value'];
            }
          }
          break;
        case 'book_type':
          foreach ($terms as $term) {
            if ($term->id()== $value) {
              return $term->get('field_type_machine_name')->getValue()[0]['value'];
            }
          }
          break;
        default:
          \Drupal::logger('book_management')->error('Taxonomy ID not specified.');
          break;
      }
    }
    catch (Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return FALSE;
  }

  /**
   * [getTaxonomyIdFromMachineName description]
   * @param  [type] $vid               [description]
   * @param  [type] $value                  [description]
   * @return [type]           [description]
   */
  public function getTaxonomyNameFromId($vid, $value) {
    try {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid, 0, NULL, TRUE);
      foreach ($terms as $term) {
        if ($term->id() == $value) {
          return $term->get('name')->getValue()[0]['value'];
        }
      }
    }
    catch (Exception $e) {
      \Drupal::logger('book_management')->error($e->getMessage());
    }
    return FALSE;
  }
}
