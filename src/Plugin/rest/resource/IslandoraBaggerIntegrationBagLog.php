<?php

namespace Drupal\islandora_bagger_integration\Plugin\rest\resource;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;

/**
 * @RestResource(
 *   id = "islandora_bagger_integration_log_bag_creation",
 *   label = @Translation("Islandora Bagger Integration Bag Log"),
 *   uri_paths = {
 *     "canonical" = "/islandora_bagger_integration/bag_log",
 *     "https://www.drupal.org/link-relations/create" = "/islandora_bagger_integration/bag_log"
 *   }
 * )
 */
class IslandoraBaggerIntegrationBagLog extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    $response = ['message' => 'This does not do anything.'];
    return new ResourceResponse($response);
  }

  /**
   * Responds to POST requests.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public static function post(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $database = Database::getConnection();
    $result = $database->insert('islandora_bagger_integration_bag_log')
      ->fields([
        'nid' => $data['nid'],
        'ip_address' => $request->getClientIp(),
        'created' => \Drupal::time()->getRequestTime(),
        'user' => $request->getUser(),
        'bag_name' => $data['bag_name'],
        'bagit_version' => $data['bagit_version'],
        'hash_algorithm' => $data['hash_algorithm'],
        'manifest' => trim($data['manifest']),
        'bag_info' => trim($data['bag_info']),
        'fetch' => trim($data['fetch']),
      ])
      ->execute();

    return new ResourceResponse(['islandora_bagger_integration_bag_log_id' => $result], 201);
  }

}
