<?php

namespace Drupal\islandora_bagger_integration\Utils;

/**
 * Utilitie methods.
 */
class IslandoraBaggerUtils {

  public function __construct() {
    $config = \Drupal::config('islandora_bagger_integration.settings');
    $this->config = $config;
  }

  /**
   * Gets the path to the Islandora Bagger config file.
   *
   * @return string|bool
   *   The absolute path to the file on the Drupal server, or FALSE if no file is found.
   */
  public function getConfigFilePath() {
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
      $islandora_bagger_config_file_path = $this->config->get('islandora_bagger_default_config_file_path');
    }

    if (file_exists($islandora_bagger_config_file_path)) {
      return $islandora_bagger_config_file_path;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Checks to see if the Islandora Bagger config file exists and is readable.
   *
   * @param string $path
   *   Path to the file to test.
   *
   * @return bool
   *   TRUE if it is, FALSE if not.
   */
  public function configFileIsReadable($path = NULL) {
    if (is_null($path)) {
      $path = $this->getConfigFilePath();
    }

    if (is_readable($path)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
}

