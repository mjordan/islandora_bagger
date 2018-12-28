<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds a node's media to the Bag.
 */
class AddMedia extends AbstractIbPlugin
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
     * Adds a node's media to the Bag.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        // Get the media associated with this node using the Islandora-supplied Manage Media View.
        $media_client = new \GuzzleHttp\Client();
        $media_url = $this->settings['drupal_base_url'] . $nid . '/media';
        $media_response = $media_client->request('GET', $media_url, [
            'http_errors' => false,
            'auth' => $this->settings['drupal_media_auth'],
            'query' => ['_format' => 'json']
        ]);
        $media_status_code = $media_response->getStatusCode();
        $media_list = (string) $media_response->getBody();
        $json_data = $media_list;
        $media_list = json_decode($media_list, true);

        // Loop through all the media and pick the ones that are tagged with terms in $taxonomy_terms_to_check.
        foreach ($media_list as $media) {
            if (count($media['field_media_use'])) {
                foreach ($media['field_media_use'] as $term) {
                    if (count($this->settings['drupal_media_tags']) == 0 ||
                            in_array($term['url'], $this->settings['drupal_media_tags'])) {
                        if (isset($media['field_media_image'])) {
                            $file_url = $media['field_media_image'][0]['url'];
                        } else {
                            $file_url = $media['field_media_file'][0]['url'];
                        }
                        $filename = $this->getFilenameFromUrl($file_url);
                        $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $filename;
                        // Fetch file and save it to $bag_temp_dir with its original filename.
                        $file_client = new \GuzzleHttp\Client();
                        $file_response = $file_client->get($file_url, ['stream' => true,
                            'timeout' => $this->settings['http_timeout'],
                            'connect_timeout' => $this->settings['http_timeout'],
                            'verify' => $this->settings['verify_ca']
                        ]);
                        $file_body = $file_response->getBody();
                        while (!$file_body->eof()) {
                            file_put_contents($temp_file_path, $file_body->read(2048), FILE_APPEND);
                        }
                        $bag->addFile($temp_file_path, basename($temp_file_path));
                    }
                }
            }
        }
        return $bag;
    }

    protected function getFilenameFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = pathinfo($path, PATHINFO_BASENAME);
        return $filename;
    }
}
