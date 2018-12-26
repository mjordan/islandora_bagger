<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds the Turtle representation of the Islandora object to the Bag.
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
     * Adds the Turtle representation of the Islandora object to the Bag.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $node_json_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'node.json';
        file_put_contents($node_json_file_path, $node_json);
        $bag->addFile($node_json_file_path, 'node.json');

        return $bag;
    }
}
