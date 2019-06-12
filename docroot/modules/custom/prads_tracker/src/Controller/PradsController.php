<?php

namespace Drupal\prads_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Defines HelloController class.
 */
class PradsController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() {
    return [
      '#type' => 'markup',
      '#markup' => '<h1>' . $this->t(' Prads tracker results for : ') . date('Y:m:d', time()) . '</h1>',
    ];
  }

}