<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds entries to fetch.txt.
 */
class AddFetch extends AbstractIbPlugin
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
     * Adds URLs listed in the config setting 'fetch_urls'.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        if (array_key_exists('fetch_urls', $this->settings)) {
          foreach ($this->settings['fetch_urls'] as $url_to_add) {
            $bag->addFetchFile($url_to_add, basename($url_to_add));
          }
        }
        return $bag;
    }
}
