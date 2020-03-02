<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds Fedora's Turtle representation of the Islandora object to the Bag.
 */
class AddFedoraTurtle extends AbstractIbPlugin
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
     * Adds Fedora's Turtle representation of the Islandora object to the Bag.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        $node_data = json_decode($node_json, true);
        $uuid = $node_data['uuid'][0]['value'];

        // Assemble the Fedora URL and add it to the Bag.
        $uuid_parts = explode('-', $uuid);
        $subparts = str_split($uuid_parts[0], 2);
        $fedora_url = $this->settings['fedora_base_url'] . implode('/', $subparts) . '/'. $uuid;

        // Get the Turtle from Fedora.
        $client = new \GuzzleHttp\Client();
        $response = $client->get($fedora_url);
        $response_body = (string) $response->getBody();

        $bag->createFile($response_body, 'node.turtle.rdf');

        return $bag;
    }
}
