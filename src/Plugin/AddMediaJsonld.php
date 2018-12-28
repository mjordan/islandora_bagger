<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds the JSON-LD representation of the Islandora object's media to the Bag.
 */
class AddMediaJsonld extends AbstractIbPlugin
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
     * Adds the JSON-LD representation of the Islandora object's media to the Bag.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $client = new \GuzzleHttp\Client();
        $media_url = $this->settings['drupal_base_url'] . '/node/' . $nid . '/media';
        $response = $client->request('GET', $media_url, [
            'http_errors' => false,
            'auth' => $this->settings['drupal_media_auth'],
            'query' => ['_format' => 'jsonld']
        ]);
        $media_json = (string) $response->getBody();
        $media_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'media.jsonld';
        file_put_contents($media_file_path, $media_json);
        $bag->addFile($media_file_path, 'media.jsonld');

        return $bag;
    }
}
