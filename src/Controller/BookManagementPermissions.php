<?php
/**
 * @file
 * Contains \Drupal\book_management\BookManagementPermissions.
 */

namespace Drupal\book_management;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BookManagementPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a BookManagementPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.manager'));
  }

  /**
   * Get permissions for Taxonomy Views Integrator.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions() {
    $permissions = [];

    foreach ($this->entityManager->getStorage('taxonomy_vocabulary')->loadMultiple() as $vocabulary) {
      $permissions += [
        'define view for vocabulary ' . $vocabulary->id() => [
          'title' => $this->t('Define the view override for the vocabulary %vocabulary', array('%vocabulary' => $vocabulary->label())),
        ]
      ];

      $permissions += [
        'define view for terms in ' . $vocabulary->id() => [
          'title' => $this->t('Define the view override for terms in %vocabulary', array('%vocabulary' => $vocabulary->label())),
        ]
      ];
    }

    return $permissions;
  }
}
