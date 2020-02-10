<?php

namespace Drupal\islandora_bagger_integration\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides a block.
 *
 * @Block(
 *   id = "islandora_bagger_block_local",
 *   admin_label = @Translation("BagIt block (local)"),
 *   category = @Translation("Create a Bag for a node locally")
 * )
 */
class BagitBlockLocal extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\islandora_bagger_integration\Plugin\Form\IslandoraBaggerLocalForm');
    return $form;
   }
}
