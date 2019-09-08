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
     * Adds CSV file created from node field data.
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

        $header = array_keys($metadata);
        $record = array();
        foreach ($metadata as $field_name => $field_values) {
          if (count($field_values) > 0) {
            foreach ($field_values as $field_value) {
              $field_value_string = '';

              // For string fields, etc.
              if (in_array('value', array_keys($field_value))) {
                $field_value_string .= ';' . trim($field_value['value']);
              }
              // For taxonomies.
              if (in_array('target_type', array_keys($field_value)) && $field_value['target_type'] == 'taxonomy_term') {
                $field_value_string .= ';' . trim($field_value['url']);
              }
 
              $field_value_string = trim($field_value_string);
              $field_value_string = ltrim($field_value_string, ';');
              $record[] = $field_value_string;
            }
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

        $csv_output_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $this->settings['csv_output_filename'];
        file_put_contents($csv_output_file_path, trim($csv_string));
        $bag->addFile($csv_output_file_path, $this->settings['csv_output_filename']);

        return $bag;
    }
}
