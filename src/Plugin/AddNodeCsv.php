<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use \League\Csv\Writer;

/**
 * Adds a CSV file created from node fields.
 */
class AddNodeCsv extends AbstractIbPlugin
{
    /**
     * Constructor.
     *
     * @param array $settings
     *    The configuration data from the .ini file.
     * @param object $logger
     *    The Monolog logger from the main Command.
     */
    public function __construct($settings, $logger)
    {
        parent::__construct($settings, $logger);
    }

    /**
     * Adds CSV file created from node field data. Multiple values are subdelimited
     * by a semicolon.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $metadata = json_decode($node_json, true);

        // Remove some fields from $metadata.
        unset($metadata['revision_log']);
        unset($metadata['promote']);
        unset($metadata['sticky']);
        unset($metadata['default_langcode']);
        unset($metadata['revision_translation_affected']);
        unset($metadata['content_translation_source']);
        unset($metadata['content_translation_outdated']);

        // type has a structure that differs from other fields, so we convert
        // its structure here to that of the other fields for processing below.
        $metadata['type'][0]['value'] = $metadata['type'][0]['target_id'];

        // The Drupal user is a linked entity.
        $metadata['uid'][0]['value'] = $metadata['uid'][0]['url'];

        $header = array_keys($metadata);
        $record = array();
        foreach ($metadata as $field_name => $field_values) {
          $field_value_strings = array();
          $field_value_string = '';
          if (count($field_values) > 0) {
            foreach ($field_values as $field_value) {
              // For typed relation fields.
              if (in_array('rel_type', array_keys($field_value))) {
                $field_value_strings[] = $field_value['rel_type'] . ':' . trim($field_value['url']);
              }
              // For string fields, etc.
              elseif (in_array('value', array_keys($field_value))) {
                $field_value_strings[] = trim($field_value['value']);
              }
              // For taxonomies.
              elseif (!in_array('rel_type', array_keys($field_value)) &&
                in_array('target_type', array_keys($field_value)) &&
                  $field_value['target_type'] == 'taxonomy_term') {
                  $field_value_strings[] = trim($field_value['url']
                );
              }
              // For referenced node fields.
              elseif (!in_array('rel_type', array_keys($field_value)) &&
                in_array('target_type', array_keys($field_value)) &&
                  $field_value['target_type'] == 'node') {
                  $field_value_strings[] = trim($field_value['url']
                );
              }
            }
            $field_value_string = implode(';', $field_value_strings);
            $field_value_string = ltrim($field_value_string, ';');
            $record[] = $field_value_string;            
          }
          else {
            $record[] = '';
          }
        }

        $csv = Writer::createFromString('');
        $csv->insertOne($header);
        // There will only be one record.
        $csv->insertOne($record);
        $csv_string = $csv->getContent();

        $bag->createFile($csv_string, $this->settings['csv_output_filename']);

        return $bag;
    }
}
