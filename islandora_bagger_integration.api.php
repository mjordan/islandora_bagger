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
 * If modules want to manipulate the Islandora Bagger config data as YAML,
 * they will need to parse $config_file_content, manipulate the YAML object,
 * and reserialize it.
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
