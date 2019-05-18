<?php

namespace Drupal\islandora_bagger_integration\Plugin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements an example form.
 */
class IslandoraBaggerForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_bagger_form';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Bag'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

/**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (\Drupal::routeMatch()->getParameter('node')) {
      $node = \Drupal::routeMatch()->getParameter('node');
      $nid = $node->id();
      $title = $node->getTitle();

      $config = \Drupal::config('islandora_bagger_integration.settings');
      $endpoint = $config->get('islandora_bagger_rest_endpoint');
      // @todo: Add asyncronous Guzzle request here.

      \Drupal::logger('islandora_bagger_integration')->notice('Request to generate Bag submitted.');
      $this->messenger()->addStatus(
        $this->t('Request to create Bag for "@title" (node @nid) submitted.',
        ['@title' => $title, '@nid' => $nid])
      );
    }
  }

}
