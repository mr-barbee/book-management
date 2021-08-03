<?php

namespace Drupal\book_management\Element;

use Drupal\file\Element\ManagedFile;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
// use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * My Managed File Element.
 *
 * @FormElement("book_managed_file")
 */
class BookManagedFile extends ManagedFile {

 /**
 * {@inheritdoc}
 */
 public static function uploadAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
    $response = parent::uploadAjaxCallback($form, $form_state, $request);
    // We want to make sure the image fids are set first.
    if (!empty($form_state->getValues()['image']['fids'])) {
      // Make the image for the book image markup.
      $file = \Drupal\file\Entity\File::load($form_state->getValues()['image']['fids'][0]);
      $url = \Drupal\image\Entity\ImageStyle::load('medium')->buildUrl($file->getFileUri());
      $image = '<img src="' . $url . '" />';
    }
    else {
      $image = '';
    }
    // Update the book image display.
    $form['image_markup'] = [
      '#markup' => '<div id="bookManagedFileImage">' . $image . '</div>',
    ];
    // Attach the image markup to the form.
    $response->addCommand(new ReplaceCommand('#bookManagedFileImage', $form['image_markup'] ));
    return $response;
  }

}
