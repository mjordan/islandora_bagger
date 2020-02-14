<?php

namespace Drupal\islandora_bagger_integration\Plugin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Implements a form.
 */
class IslandoraBaggerLocalForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_bagger_local_form';
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
        '#value' => $this->t('Create Bag Locally'),
        '#button_type' => 'primary',
      ];
      $form['info'] = array(
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Clicking this button will create a Bag for this node.'),
      );
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
      $bagger_directory = $config->get('islandora_bagger_local_bagger_directory');
      $actual_path = \Drupal::service('file_system')->realpath($islandora_bagger_config_file_path);
      $bagger_cmd = ['./bin/console', 'app:islandora_bagger:create_bag', "--settings=$actual_path", '--node=' . $nid];
      $process = new Process($bagger_cmd);
      $process->setWorkingDirectory($bagger_directory);
      $process->run();
      $path_to_bag = preg_replace('/^.*\s+at\s+/', '', trim($process->getOutput()));
      $bag_filename = pathinfo($path_to_bag, PATHINFO_BASENAME);
      $path_to_bag = file_create_url('public://' . $bag_filename);
      $url = Url::fromUri($path_to_bag);
      $link = \Drupal::service('link_generator')->generate($this->t('here'), $url);

      if ($process->isSuccessful()) {
        $messanger_level = 'addStatus';
        $logger_level = 'notice';
        $message = $this->t('Download your Bag @link.',
          ['@link' => $link]
        );
      }
      else {
	throw new ProcessFailedException($process);
        $messanger_level = 'addWarning';
        $logger_level = 'warning';
        $message = $this->t('Request to create Bag for "@title" (node @nid) failed with return code @return_code.',
          ['@title' => $title, '@nid' => $nid, '@return_code' => $return_code]
        );
      }

      \Drupal::logger('islandora_bagger_integration')->{$logger_level}($message);
      $this->messenger()->{$messanger_level}($message);
    }
  }

}
