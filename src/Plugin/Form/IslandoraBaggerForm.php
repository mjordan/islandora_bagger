<?php

namespace Drupal\islandora_bagger_integration\Plugin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Drupal\jwt\Authentication\Provider\JwtAuth;

/**
 * Implements a form.
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
      $form['info'] = array(
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Clicking this button will request a Bag be created for this object.'),
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
        $this->t("Sorry, you do not have sufficient permission to create a Bag for this Islandora object.")
      );
    }

    $config = \Drupal::config('islandora_bagger_integration.settings');
    $utils = \Drupal::service('islandora_bagger_integration.utils');
    if (!$utils->configFileIsReadable()) {
      $message = $this->t("Sorry, the Bagger configuration file at @file is not readable.",
	  ['@file' => $config->get('islandora_bagger_default_config_file_path')]);
      $form_state->setErrorByName('submit', $message);
      \Drupal::logger('islandora_bagger_integration')->{'error'}($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::config('islandora_bagger_integration.settings');
    $mode = $config->get('islandora_bagger_mode');

    if (\Drupal::routeMatch()->getParameter('node') && $mode == 'local') {
      $nid = $form_state->getValue('nid');
      $node = \Drupal\node\Entity\Node::load($nid);
      $title = $node->getTitle();

      $config = \Drupal::config('islandora_bagger_integration.settings');
      // @Todo: if this is FALSE, report error.
      $utils = \Drupal::service('islandora_bagger_integration.utils');

      $islandora_bagger_config_file_path = $utils->getConfigFilePath();

      // Allow other modules to modify the Islandor Bagger config file. Write out modified config
      // file contents and modify $islandora_bagger_config_file_path to point to the modified file.
      $config_file_contents = file_get_contents($islandora_bagger_config_file_path);
      \Drupal::moduleHandler()->invokeAll('islandora_bagger_config_file_contents_alter', [$nid, &$config_file_contents]);
      $tmp_dir = \Drupal::service('file_system')->getTempDirectory();
      $tmp_islandora_bagger_config_file_path = $tmp_dir . DIRECTORY_SEPARATOR .
	      pathinfo($islandora_bagger_config_file_path, PATHINFO_BASENAME) . '.islandora_bagger.' . $nid . '.tmp.yml';
      file_put_contents($tmp_islandora_bagger_config_file_path, $config_file_contents);
      $islandora_bagger_config_file_path = $tmp_islandora_bagger_config_file_path;

      $bagger_directory = $config->get('islandora_bagger_local_bagger_directory');

      /**
       * @var JwtAuth $jwt
       */
      $jwt = \Drupal::service('jwt.authentication.jwt');
      $bagger_cmd = ['./bin/console', 'app:islandora_bagger:create_bag', '--settings=' . $islandora_bagger_config_file_path, '--node=' . $nid,
       '--token=' . $jwt->generateToken()];


      $process = new Process($bagger_cmd);
      $process->setWorkingDirectory($bagger_directory);
      $process->run();

      $path_to_bag = preg_replace('/^.*\s+at\s+/', '', trim($process->getOutput()));
      $bag_filename = pathinfo($path_to_bag, PATHINFO_BASENAME);
      $path_to_bag = file_create_url('public://' . $bag_filename);
      $url = Url::fromUri($path_to_bag);
      $link = \Drupal::service('link_generator')->generate($this->t('here'), $url);

      if ($process->isSuccessful()) {
        $messenger_level = 'addStatus';
        $logger_level = 'notice';
        $message = $this->t('Download your Bag @link.',
          ['@link' => $link]
        );
	@unlink($tmp_islandora_bagger_config_file_path);
      }
      else {

        $messenger_level = 'addWarning';
        $logger_level = 'warning';
        $message = $this->t('Request to create Bag for "@title" (node @nid) failed with return code @return_code and error text @error_text.',
          ['@title' => $title, '@nid' => $nid, '@return_code' => $return_code, '@error_text' => $process->getErrorOutput()]
        );
      }

      \Drupal::logger('islandora_bagger_integration')->{$logger_level}($message);
      $this->messenger()->{$messenger_level}($message);
    }

    if (\Drupal::routeMatch()->getParameter('node') && $mode == 'remote') {
      $nid = $form_state->getValue('nid');
      $node = \Drupal\node\Entity\Node::load($nid);
      $title = $node->getTitle();

      $endpoint = $config->get('islandora_bagger_rest_endpoint');

      $utils = \Drupal::service('islandora_bagger_integration.utils');
      $islandora_bagger_config_file_path = $utils->getConfigFilePath();

      // Allow other modules to modify $config_file_contents before it is POSTed to the microservice.
      $config_file_contents = file_get_contents($islandora_bagger_config_file_path);
      \Drupal::moduleHandler()->invokeAll('islandora_bagger_config_file_contents_alter', [$nid, &$config_file_contents]);

      if ($config->get('islandora_bagger_add_email_user')) {
        $user = \Drupal::currentUser();
        $user_email = $user->getEmail();
        $user_email_yaml_string = "\n# Added by the Islandora Bagger Integration module\nrecipient_email: $user_email";
        $config_file_contents = $config_file_contents . $user_email_yaml_string;
      }

      $headers = array('Islandora-Node-ID' => $nid);
      $response = \Drupal::httpClient()->post(
        $endpoint,
        array('headers' => $headers, 'body' => $config_file_contents, 'allow_redirects' => ['strict' => true])
      );
      $http_code = $response->getStatusCode();
      if ($http_code == 200) {
        $messenger_level = 'addStatus';
        $logger_level = 'notice';
        if ($config->get('islandora_bagger_add_email_user')) {
          $message = $this->t('Request to create Bag for "@title" (node @nid) submitted. You will receive an email at @email when the Bag is ready to download.',
            ['@title' => $title, '@nid' => $nid, '@email' => $user_email]
          );
        } else {
          $message = $this->t('Request to create Bag for "@title" (node @nid) submitted.',
            ['@title' => $title, '@nid' => $nid]
          );
        }
      }
      else {
        $messenger_level = 'addWarning';
        $logger_level = 'warning';
        $message = $this->t('Request to create Bag for "@title" (node @nid) failed with status code @http.',
          ['@title' => $title, '@nid' => $nid, '@http' => $http_code]
        );
      }

      \Drupal::logger('islandora_bagger_integration')->{$logger_level}($message);
      $this->messenger()->{$messenger_level}($message);
    }
  }
}
