<?php
namespace Drupal\islandora_bagger_integration\Plugin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;

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

    return parent::buildForm($form, $form_state);
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
       $this->configFactory->getEditable('islandora_bagger_integration.settings')
      ->set('islandora_bagger_rest_endpoint', $form_state->getValue('islandora_bagger_rest_endpoint'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}

