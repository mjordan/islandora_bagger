<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds a node's media to the Bag.
 */
class AddMultifileMedia extends AbstractIbPlugin {
  /**
   * Constructor.
   *
   * @param array $settings
   *    The configuration data from the .ini file.
   * @param object $logger
   *    The Monolog logger from the main Command.
   */
  public function __construct($settings, $logger) {
    parent::__construct($settings, $logger);
  }

  /**
   * Adds a node's media to the Bag.
   */
  public function execute($bag, $bag_temp_dir, $nid, $node_json) {
    $this->settings['include_media_use_list'] = (!isset($this->settings['include_media_use_list'])) ?
      FALSE : $this->settings['include_media_use_list'];

    $this->settings['media_file_directories'] = (!isset($this->settings['media_file_directories'])) ?
      '' : $this->settings['media_file_directories'];
    $this->settings['media_file_fields'] = (!isset($this->settings['media_file_fields'])) ?
      '' : $this->settings['media_file_fields'];

    // Get the media associated with this node using the Islandora-supplied Manage Media View.
    $media_client = new \GuzzleHttp\Client();
    $media_url = $this->settings['drupal_base_url'] . '/node/' . $nid . '/media';
    $media_response = $media_client->request('GET', $media_url, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json'],
    ]);
    $media_list = (string) $media_response->getBody();
    $json_data = $media_list;
    $media_list = json_decode($media_list, TRUE);

    // Loop through all the media and pick the ones that are tagged with terms in
    // $this->settings['drupal_media_tags']. If that list is empty, add all media to the Bag.
    if ($this->settings['include_media_use_list']) {
      $file_use_list = '';
    }
    foreach ($media_list as $media) {
      $file_fields = [];
      foreach ($media as $key => $field) {
        if (isset($field[0]['target_type']) && $field[0]['target_type'] == 'file') {
          $file_fields[$key] = $field;
        }
      }
      foreach ($file_fields as $key => $field) {
        if (in_array($key, $this->settings['media_file_fields'])) {
          $file_url = $media[$key][0]['url'];
          $filename = $this->getFilenameFromUrl($file_url);
          $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $filename;
          // Fetch file and save it to $bag_temp_dir with its original filename.
          // @todo: Determine what to do if the file already exists.
          $file_client = new \GuzzleHttp\Client();
          $file_response = $file_client->get($file_url, ['stream' => TRUE,
            'timeout' => $this->settings['http_timeout'],
            'connect_timeout' => $this->settings['http_timeout'],
            'verify' => $this->settings['verify_ca'],
          ]);
          $file_body = $file_response->getBody();
          while (!$file_body->eof()) {
            file_put_contents($temp_file_path, $file_body->read(2048), FILE_APPEND);
          }
          $bag->addFile($temp_file_path, $this->settings['media_file_directories'] . basename($temp_file_path));
        }
      }
    }
    if ($this->settings['include_media_use_list']) {
      $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'media_use_summary.tsv';
      file_put_contents($temp_file_path, $file_use_list);
      $bag->addFile($temp_file_path, 'media_use_summary.tsv');
    }
    return $bag;
  }

  protected function getFilenameFromUrl($url) {
    $path = parse_url($url, PHP_URL_PATH);
    $filename = pathinfo($path, PATHINFO_BASENAME);
    return $filename;
  }

  protected function fetchTermInfo($term) {
    $client = new \GuzzleHttp\Client();
    $url = $this->settings['drupal_base_url'] . $term;
    $response = $client->request('GET', $url, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json'],
    ]);
    $body = (string) $response->getBody();
    $tag_info = json_decode($body, TRUE);
    return $tag_info;
  }
}
