<?php

namespace Drupal\islandora_bagger_integration\Plugin\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;


/**
 * Admin settings form.
 */
class IslandoraBaggerIntegrationSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_bagger_integration_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'islandora_bagger_integration.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $actual_path = \Drupal::service('file_system')->realpath('private://');
    if (!$actual_path) {
      $this->messenger()->addWarning("No Private File Folder found, please contact system administrator");
    }
    $config = $this->config('islandora_bagger_integration.settings');
    $current_config = file_get_contents($config->get('islandora_bagger_default_config_file_path'));
    if (!$current_config) {
      $current_config = t("No saved configuration has been found.");
    }
    $form['islandora_bagger_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Islandora Bagger location'),
      '#options' => [
        'remote' => $this->t('Remote'),
        'local' => $this->t('Local'),
      ],
      '#default_value' => $config->get('islandora_bagger_mode'),
      '#attributes' => [
        'id' => 'bagger_location',
      ],
    ];
    $form['islandora_bagger_default_config_file_path'] = [
      '#type' => 'textfield',
      '#maxlength' => 256,
      '#title' => $this->t('Path to default Islandora Bagger microservice config file.  Normally starts with private://'),
      '#description' => $this->t('This file must exist on your Drupal server. You can use other config files via Context.'),
      '#default_value' => $config->get('islandora_bagger_default_config_file_path'),
    ];
    $form['islandora_bagger_rest_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Islandora Bagger microservice REST endpoint'),
      '#description' => $this->t('Do not include the trailing /.'),
      '#default_value' => $config->get('islandora_bagger_rest_endpoint'),
      '#states' => [
        'visible' => [
          ':input[id="bagger_location"]' => ['value' => 'remote'],
        ],
      ],
    ];
    $form['islandora_bagger_add_email_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Add user's email address to configuration file using the key 'recipient_email'."),
      '#default_value' => $config->get('islandora_bagger_add_email_user'),
      '#states' => [
        'visible' => [
          ':input[id="bagger_location"]' => ['value' => 'remote'],
        ],
      ],
    ];

    $form['islandora_bagger_local_bagger_directory'] = [
      '#type' => 'textfield',
      '#maxlength' => 256,
      '#title' => $this->t('Absolute path to your local Islandora Bagger installation'),
      '#description' => $this->t('For example, "/var/local/islandora_bagger". Used only when running in "local" mode. Ignore if you are using Islandora Bagger as a microservice.'),
      '#default_value' => $config->get('islandora_bagger_local_bagger_directory'),
      '#states' => [
        'visible' => [
          ':input[id="bagger_location"]' => ['value' => 'local'],
        ],
      ],
    ];

    $form['current_setup'] = [
      '#type' => 'textarea',
      '#default_value' => $current_config,
      '#title' => t('Configure Bagger'),
      '#rows' => 50,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $utils = \Drupal::service('islandora_bagger_integration.utils');
    try {
      $bagger_settings = Yaml::parse(trim($form_state->getValue('current_setup')));
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(
        'current_setup',
        $this->t('Invalid Yaml.')
      );
      return;
    }
    if (!is_array($bagger_settings)) {
      return;
    }
    if (!$utils->configFileIsReadable(trim($form_state->getValue('islandora_bagger_default_config_file_path')))) {
      $form_state->setErrorByName(
        'islandora_bagger_default_config_file_path',
        $this->t('Cannot find the Islandora Bagger config file at the path specified.')
      );
    }

    if ($form_state->getValue('islandora_bagger_mode') == 'local' && !is_writable($bagger_settings['output_dir'])) {
      $form_state->setErrorByName(
        'islandora_bagger_default_config_file_path',
        $this->t('@dir identified in the "output_dir" setting in @path is not writable.',
          ['@dir' => $bagger_settings['output_dir'],
            '@path' => ($form_state->getValue('islandora_bagger_default_config_file_path'))])
      );
    }

    if ($form_state->getValue('islandora_bagger_mode') == 'local') {
      $allowed_serializations = ['zip', 'tgz'];
      if (!in_array($bagger_settings['serialize'], $allowed_serializations)) {
        $form_state->setErrorByName(
          'islandora_bagger_default_config_file_path',
          $this->t('The "serialize" setting in @path is "@serialization". It must be either "zip" or "tgz".',
            ['@path' => $form_state->getValue('islandora_bagger_default_config_file_path'),
              '@serialization.' => $bagger_settings['serialize']])
        );
      }
    }

    if ($form_state->getValue('islandora_bagger_mode') == 'local') {
      if (!file_exists(trim($form_state->getValue('islandora_bagger_local_bagger_directory')))) {
        $form_state->setErrorByName(
          'islandora_bagger_local_bagger_directory',
          $this->t('Cannot find the Islandora Bagger installation directory at the path specified.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('islandora_bagger_integration.settings')
      ->set('islandora_bagger_mode', $form_state->getValue('islandora_bagger_mode'))
      ->set('islandora_bagger_default_config_file_path', trim($form_state->getValue('islandora_bagger_default_config_file_path')))
      ->set('islandora_bagger_rest_endpoint', trim($form_state->getValue('islandora_bagger_rest_endpoint')))
      ->set('islandora_bagger_add_email_user', $form_state->getValue('islandora_bagger_add_email_user'))
      ->set('islandora_bagger_local_bagger_directory', trim($form_state->getValue('islandora_bagger_local_bagger_directory')))
      ->save();
    $config = $form_state->getValue('current_setup');
    $success = file_save_data($config, trim($form_state->getValue('islandora_bagger_default_config_file_path')), FILE_EXISTS_REPLACE);
    parent::submitForm($form, $form_state);
  }
}

