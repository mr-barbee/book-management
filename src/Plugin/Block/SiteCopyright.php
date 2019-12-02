<?php

namespace Drupal\book_management\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Site Copyritght' Block.
 *
 * @Block(
 *   id = "site_copyright",
 *   admin_label = @Translation("Site Copyright"),
 *   category = @Translation("Menus"),
 * )
 */
class SiteCopyright extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Load the site name out of configuration.
    $config = \Drupal::config('system.site');
    return array(
      '#markup' => $this->t('<h5>© ' . date('Y') .' ' . $config->get('name') . '. All rights reserved.</h5>'),
    );
  }

}