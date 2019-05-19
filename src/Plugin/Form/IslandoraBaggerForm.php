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

      $context_manager = \Drupal::service('context.manager');
      foreach ($context_manager->getActiveReactions('islandora_bagger_integration_config_file_paths') as $reaction) {
        $islandora_bagger_config_file_path = $reaction->execute();
      }

      // For now, we use a sample config file.
      $sample_config_file_path = drupal_get_path('module', 'islandora_bagger_integration') .
        '/assets/sample_islandora_bagger_config.yml';
      $sample_config_file_contents = fopen($sample_config_file_path, 'r');

      $headers = array('Islandora-Node-ID' => $nid);
      $response = \Drupal::httpClient()->post(
        $endpoint,
        array('headers' => $headers, 'body' => $sample_config_file_contents)
      );
      $http_code = $response->getStatusCode();
      if ($http_code == 200) {
        $messanger_level = 'addStatus';
        $logger_level = 'notice';
        $message = $this->t('Request to create Bag for "@title" (node @nid) submitted.',
          ['@title' => $title, '@nid' => $nid]
        );
      }
      else {
        $messanger_level = 'addWarning';
        $logger_level = 'warning';
        $message = $this->t('Request to create Bag for "@title" (node @nid) failed with status code @http.',
          ['@title' => $title, '@nid' => $nid, '@http' => $http_code]
        );
      }

      \Drupal::logger('islandora_bagger_integration')->{$logger_level}($message);
      $this->messenger()->{$messanger_level}($message);
    }
  }

}
