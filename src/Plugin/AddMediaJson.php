<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds the JSON representation of the Islandora object's media to the Bag.
 */
class AddMediaJson extends AbstractIbPlugin
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

     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $client = new \GuzzleHttp\Client();
        $media_url = $this->settings['drupal_base_url'] . '/node/' . $nid . '/media';
        $response = $client->request('GET', $media_url, [
            'http_errors' => false,
            'auth' => $this->settings['auth'],
            'query' => ['_format' => 'json']
        ]);
        $media_json = (string) $response->getBody();

        $bag->createFile($media_json, 'media.json');

        return $bag;
    }
}
