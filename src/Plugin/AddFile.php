<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds one or more files specified in the 'files_to_add' config option.
 */
class AddFile extends AbstractIbPlugin
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
     * {@inheritdoc}
     *
     * Adds file listed in the config setting 'files_to_add'.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        // @todo: Log if this setting doesn't exist.
        if (array_key_exists('files_to_add', $this->settings)) {
          foreach ($this->settings['files_to_add'] as $file_to_add) {
            // @todo: Log if file cannot be found.
            if (file_exists($file_to_add)) {
              $bag->addFile($file_to_add, basename($file_to_add));
            }
          }
        }
        return $bag;
    }
}
