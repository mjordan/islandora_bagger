<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds serialized PREMIS Turtle RDF for the Islandora object metadata to the Bag.
 */
class AddPremis extends AbstractIbPlugin
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
   * Adds serialized PREMIS Turtle RDF produced by the Islandora PREMIS module.
   */
  public function execute($bag, $bag_temp_dir, $nid, $node_json)
  {

    // Assemble the Datacite XML URL and add it to the Bag.
    $drupal_url = rtrim($this->settings['drupal_base_url'], '/') . '/node/' . $nid . '/premis';
    // Get the xml from Drupal.
    $client = new \GuzzleHttp\Client();
    $response = $client->get($drupal_url);
    $response_body = (string) $response->getBody();

    $bag->createFile($response_body, 'PREMIS.turtle');

    return $bag;
  }
}
