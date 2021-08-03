<?php

namespace Drupal\book_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\user\Entity\User;

/**
 * Defines a route controller for watches autocomplete form elements.
 */
class StudentAutoCompleteController extends ControllerBase {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorage
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStroage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Handler for autocomplete request.
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');

    // Get the typed string from the URL, if it exists.
    if (!$input) {
      return new JsonResponse($results);
    }

    $input = Xss::filter($input);

    // Load all the studnet users.
    $query = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('field_student_name', $input, 'CONTAINS')
      ->condition('roles', 'student')
      ->sort('created', 'DESC')
      ->range(0, 10);

    $ids = $query->execute();
    $users = $ids ? User::loadMultiple($ids) : [];

    foreach ($users as $user) {
      // Make the label for the autocomplete widget.
      $label = [
        $user->get('field_student_name')->getString(),
        '<small>(Grade: ' . $user->get('field_student_grade')->getString() . ')</small>'
      ];
      // we want to return to the json data.
      $results[] = [
        'value' => EntityAutocomplete::getEntityLabels([$user]),
        'label' => implode(' ', $label),
      ];
    }

    return new JsonResponse($results);
  }
}
