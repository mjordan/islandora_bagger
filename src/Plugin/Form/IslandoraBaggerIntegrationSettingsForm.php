<?php
namespace Drupal\islandora_bagger_integration\Plugin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
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
    $config = $this->config('islandora_bagger_integration.settings');

    $form['islandora_bagger_rest_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Islandora Bagger microservice REST endpoint'),
      '#description' => $this->t('Do not include the trailing /.'),
      '#default_value' => $config->get('islandora_bagger_rest_endpoint') ? $config->get('islandora_bagger_rest_endpoint') : 'http://localhost:8000/api/createbag',
    );
    $form['islandora_bagger_default_config_file_path'] = array(
      '#type' => 'textfield',
      '#maxlength' => 256,
      '#title' => $this->t('Absolute path to default Islandora Bagger microservice config file'),
      '#description' => $this->t('This file must exist on your Drupal server. You can use other config files via Context.'),
      '#default_value' => $config->get('islandora_bagger_default_config_file_path') ? $config->get('islandora_bagger_default_config_file_path') : '/path/to_default_config.yml',
    );
    $form['islandora_bagger_integration_add_email_user'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t("Add user's email address to configuration file using the key 'recipient_email'."),
      '#default_value' => $config->get('islandora_bagger_integration_add_email_user') ? $config->get('islandora_bagger_integration_add_email_user') : FALSE,
    );
    $form['islandora_bagger_local_bagger_directory'] = array(
      '#type' => 'textfield',
      '#maxlength' => 256,
      '#title' => $this->t('Absolute path to your local Islandora Bagger installation'),
      '#description' => $this->t('Used only by the "local" Islandora Bagger block. Ignore if you are using Islandora Bagger as a microservice.'),
      '#default_value' => $config->get('islandora_bagger_local_bagger_directory') ? $config->get('islandora_bagger_local_bagger_directory') : '/path/to/islandora_bagger',
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!file_exists(trim($form_state->getValue('islandora_bagger_default_config_file_path')))) {
      $form_state->setErrorByName(
        'islandora_bagger_default_config_file_path',
        $this->t('Cannot find the Islandora Bagger config file at the path specified.')
      );
    }
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
       $this->configFactory->getEditable('islandora_bagger_integration.settings')
      ->set('islandora_bagger_default_config_file_path', trim($form_state->getValue('islandora_bagger_default_config_file_path')))
      ->set('islandora_bagger_rest_endpoint', trim($form_state->getValue('islandora_bagger_rest_endpoint')))
      ->set('islandora_bagger_integration_add_email_user', $form_state->getValue('islandora_bagger_integration_add_email_user'))
      ->set('islandora_bagger_local_bagger_directory', $form_state->getValue('islandora_bagger_local_bagger_directory'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}

