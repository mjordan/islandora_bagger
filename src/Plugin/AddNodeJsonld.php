<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds Drupal's JSON-LD representation of the Islandora object to the Bag.
 */
class AddNodeJsonld extends AbstractIbPlugin
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
     * Adds Drupal's JSON-LD representation of the Islandora object to the Bag.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        $client = new \GuzzleHttp\Client();
        $url = $this->settings['drupal_base_url'] . '/node/' . $nid;
        $response = $client->request('GET', $url, [
            'http_errors' => false,
            'query' => ['_format' => 'jsonld']
        ]);
        $node_jsonld = (string) $response->getBody();
        $bag->createFile($node_jsonld, 'node.jsonld');

        return $bag;
    }
}
