<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds Drupal's JSON representation of the Islandora object to the Bag.
 */
class AddNodeJson extends AbstractIbPlugin
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
     * Adds Drupal's JSON representation of the Islandora object to the Bag.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $bag->createFile($node_json, 'node.json');

        return $bag;
    }
}
