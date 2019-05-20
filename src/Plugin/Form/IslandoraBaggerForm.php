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
    if (\Drupal::routeMatch()->getParameter('node')) {
      $node = \Drupal::routeMatch()->getParameter('node');
      $nid = $node->id();
      $form['actions']['#type'] = 'actions';
      $form['nid'] = array(
        '#type' => 'value',
        '#value' => $nid,
      );
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Bag'),
        '#button_type' => 'primary',
      ];
      return $form;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');
    $node = \Drupal\node\Entity\Node::load($nid);
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $access = $node->access('view', $user);
    if (FALSE == $access) {
      $form_state->setErrorByName('submit',
        t("Sorry, you do not have sufficient permission to create a Bag for this node.")
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (\Drupal::routeMatch()->getParameter('node')) {
      $nid = $form_state->getValue('nid');
      $node = \Drupal\node\Entity\Node::load($nid);
      $title = $node->getTitle();

      $config = \Drupal::config('islandora_bagger_integration.settings');
      $endpoint = $config->get('islandora_bagger_rest_endpoint');

      if (\Drupal::moduleHandler()->moduleExists('context')) {
        $context_manager = \Drupal::service('context.manager');
        // If there are multiple contexts that provide a path to a config file, it's OK to use the last one.
        foreach ($context_manager->getActiveReactions('islandora_bagger_integration_config_file_paths') as $reaction) {
          $islandora_bagger_config_file_path_from_context = $reaction->execute();
        }
      }

      if (isset($islandora_bagger_config_file_path_from_context) && strlen($islandora_bagger_config_file_path_from_context)) {
        $islandora_bagger_config_file_path = $islandora_bagger_config_file_path_from_context;
      }
      else {
        $islandora_bagger_config_file_path = $config->get('islandora_bagger_default_config_file_path');
      }

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
