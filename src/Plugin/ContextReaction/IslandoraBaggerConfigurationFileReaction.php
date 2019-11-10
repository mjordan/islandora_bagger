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
    $module_config = \Drupal::config('islandora_bagger_integration.settings');
    $mode = $module_config->get('islandora_bagger_mode');

    $utils = \Drupal::service('islandora_bagger_integration.utils');
    $bagger_settings = $utils->getIslandoraBaggerConfig(trim($form_state->getValue('bagger_config_file_path')));

    if (!$utils->configFileIsReadable(trim($form_state->getValue('bagger_config_file_path')))) {
      $form_state->setErrorByName(
        'bagger_config_file_path',
	$this->t('Cannot find or read the Islandora Bagger config file at @path.',
	  ['@path' => $form_state->getValue('bagger_config_file_path')])
      );
    }

    if ($mode == 'local' && $utils->configFileIsReadable(trim($form_state->getValue('bagger_config_file_path')))) {
      if (!is_writable($bagger_settings['output_dir'])) {
        $form_state->setErrorByName(
          'bagger_config_file_path',
          $this->t('@dir identified in the "output_dir" setting in @path is not writable.',
          ['@dir' => $bagger_settings['output_dir'],
          '@path' => ($form_state->getValue('bagger_config_file_path'))])
        );
      }
    }

    if ($mode == 'local' && $utils->configFileIsReadable(trim($form_state->getValue('bagger_config_file_path')))) {
      $allowed_serializations = array('zip', 'tgz');
      if (!in_array($bagger_settings['serialize'], $allowed_serializations)) {
        $form_state->setErrorByName(
          'bagger_config_file_path',
          $this->t('The "serialize" setting in @path is "@serialization". It must be either "zip" or "tgz".',
          ['@path' => $form_state->getValue('bagger_config_file_path'),
          '@serialization' => $bagger_settings['serialize']])
        );
      }
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
