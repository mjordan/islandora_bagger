<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds serialized Datacite XML representation of the Islandora object metadata to the Bag. Relies on https://github.com/roblib/islandora_rdm
 */
class AddDataciteXML extends AbstractIbPlugin
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
   * Adds Datacite XML version of the Islandora object metadata to the Bag.
   */
  public function execute($bag, $bag_temp_dir, $nid, $node_json, $token = NULL)
  {

    // Assemble the Datacite XML URL and add it to the Bag.
    $drupal_url = $this->settings['drupal_base_url'] . '/islandora_rdm_datacite/get/' . $nid;
    // Get the xml from Drupal.
    $client = new \GuzzleHttp\Client();
    $response = $client->get($drupal_url, ['headers' => ['Authorization' => 'Bearer ' . $token]]);
    $response_body = (string) $response->getBody();

    $bag->createFile($response_body, $nid . '.datacite.xml');

    return $bag;
  }
}
