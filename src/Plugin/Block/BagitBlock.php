<?php

namespace Drupal\islandora_bagger_integration\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides a block.
 *
 * @Block(
 *   id = "islandora_bagger_block",
 *   admin_label = @Translation("BagIt block"),
 *   category = @Translation("Create a Bag for a node")
 * )
 */
class BagitBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\islandora_bagger_integration\Plugin\Form\IslandoraBaggerForm');
    return $form;
   }
}
