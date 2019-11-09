<?php

namespace Drupal\islandora_bagger_integration\Plugin\ContextReaction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\context\ContextReactionPluginBase;

/**
 * Provide paths to Islandora Bagger config files.
 *
 * @ContextReaction(
 *   id = "islandora_bagger_integration_config_file_paths",
 *   label = @Translation("Islandora Bagger config file")
 * )
 */
class IslandoraBaggerConfigurationFileReaction extends ContextReactionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'bagger_config_file_path' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('Determine paths to Islandora Bagger config files.');
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $config = $this->getConfiguration();
    return $config['bagger_config_file_path'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form['bagger_config_file_path'] = [
      '#title' => $this->t('Islandora Bagger config file path'),
      '#type' => 'textfield',
      '#maxlength' => 256,
      '#description' => $this->t('Absolute path on the Drupal server to the Islandora Bagger config file to use.'),
      '#default_value' => isset($config['bagger_config_file_path']) ? $config['bagger_config_file_path'] : '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $utils = \Drupal::service('islandora_bagger_integration.utils');
    if (!$utils->configFileIsReadable(trim($form_state->getValue('bagger_config_file_path')))) {
      $form_state->setErrorByName(
        'bagger_config_file_path',
        $this->t('Cannot find or read the Islandora Bagger config file at the path specified.')
      );
    }
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration([
      'bagger_config_file_path' => trim($form_state->getValue('bagger_config_file_path')),
    ]);
  }

}
