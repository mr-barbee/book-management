<?php

use Drupal\views\ViewExecutable;
/**
 * [book_management_views_data_alter description]
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function book_management_views_data_alter(&$data) {
  // Add the date format to the view.
  // Adds the date dropdown to the search
  // records page.
  $data['book_management_transaction_records']['check_out_date']['field']['id'] = 'date';
  $data['book_management_transaction_records']['check_out_date']['filter']['id'] = 'date';
  $data['book_management_transaction_records']['check_in_date']['field']['id'] = 'date';
  $data['book_management_transaction_records']['check_in_date']['filter']['id'] = 'date';
}
