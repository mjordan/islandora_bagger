<?php

namespace Drupal\islandora_bagger_integration\Plugin\ContextReaction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\context\ContextReactionPluginBase;
use Symfony\Component\Yaml\Yaml;

/**
 * Add Islandora Bagger config options.
 *
 * @ContextReaction(
 *   id = "islandora_bagger_integration_config_options",
 *   label = @Translation("Islandora Bagger config options")
 * )
 */
class IslandoraBaggerConfigurationOptionsReaction extends ContextReactionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'bagger_config_options' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('Modify Islandora Bagger config options.');
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $config = $this->getConfiguration();
    return trim($config['bagger_config_options']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form['bagger_config_options'] = [
      '#title' => $this->t('Islandora Bagger config options'),
      '#type' => 'textarea',
      '#description' => $this->t("Key:value pairs of options to add/modify. One pair per line. See module's README file for examples."),
      '#default_value' => isset($config['bagger_config_options']) ? $config['bagger_config_options'] : '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
	  // @todo: Make sure its valid YAML.
	  // try {
            //  $value = Yaml::parse('...');
            // } catch (ParseException $exception) {
              // printf('Unable to parse the YAML string: %s', $exception->getMessage());
           // }

    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration([
      'bagger_config_options' => trim($form_state->getValue('bagger_config_options')),
    ]);
  }

}
