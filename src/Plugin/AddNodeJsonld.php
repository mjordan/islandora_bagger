<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

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
     * Adds Drupal's JSON-LD representation of the Islandora object to the Bag.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $client = new \GuzzleHttp\Client();
        $url = $this->settings['drupal_base_url'] . '/node/' . $nid;
        $response = $client->request('GET', $url, [
            'http_errors' => false,
            'query' => ['_format' => 'jsonld']
        ]);
        $node_jsonld = (string) $response->getBody();
        $node_jsonld_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'node.jsonld';
        file_put_contents($node_jsonld_file_path, $node_jsonld);
        $bag->addFile($node_jsonld_file_path, 'node.jsonld');

        return $bag;
    }
}
