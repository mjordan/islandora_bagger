<?php

/**
 * @file
 * Hooks for the Islandora Bagger Integration module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the serialized YAML contents of the config file.
 *
 * @param string $config_file_contents
 *   File contents, as a string, to alter.
 */
function hook_islandora_bagger_config_file_contents_alter(&$config_file_contents) {
  $yaml_string = "\nmy_custom_bagger_yaml_key: Foo";
  $config_file_contents = $config_file_contents . $yaml_string;
}

/**
 * @} End of "addtogroup hooks".
 */
