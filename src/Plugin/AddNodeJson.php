<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

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
     * {@inheritdoc}
     *
     * Adds Drupal's JSON representation of the Islandora object to the Bag.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json, $token = NULL)
    {
        $bag->createFile($node_json, 'node.json');

        return $bag;
    }
}
