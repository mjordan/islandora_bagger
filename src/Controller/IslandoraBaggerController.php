<?php
// src/Controller/IslandoraBaggerController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\IslandoraBagger;

use Psr\Log\LoggerInterface;

// For now, until we figure out how to pass in config data.
use Symfony\Component\Yaml\Yaml;

class IslandoraBaggerController extends AbstractController
{

    public function create(Request $request, ParameterBagInterface $params, LoggerInterface $logger)
    {
        $application_directory = dirname(__DIR__, 2);

        $nid = $request->headers->get('Islandora-Node-ID');

        // Get POSTed YAML from request body.
        $body = $request->getContent();
        $yaml_path = $application_directory . '/var/islandora_bagger.' . $nid . '.yml';
        file_put_contents($yaml_path, $body);

        // @todo: If this method fails (returned false), log that.
        $this->writeToQueue($params, $nid, $yaml_path);

        $data = array(
            'Entry for node ' . $nid . ' using configuration at ' . $yaml_path . ' added to queue.'
        );
        $response = new JsonResponse($data);
        return $response;
    }

    /**
     * Get the location of the Bag in to add to a GET response.
     */
    public function getLocation(Request $request, ParameterBagInterface $params, LoggerInterface $logger)
    {
        // Set in the parameters section of config/services.yaml.
        $location_log_path = $params->get('app.location.log.path');

        // If the log file doesn't exist, return an empty array.
        if (!file_exists($location_log_path)) {
          $response = new JsonResponse(array());
          return $response;
        }

        $nid = $request->headers->get('Islandora-Node-ID');

        // Read log file, get the current node's Bag's location.
        $locations = file($location_log_path, FILE_IGNORE_NEW_LINES);
        foreach ($locations as $location) {
            if (preg_match('/^' . $nid . '\t/', $location)) {
                list(, $bag_path, $timestamp) = explode('	', $location);
                break;
            }
        }

        $data = array(
            'nid' => $nid,
            'location' => $bag_path,
            'created' => $timestamp
        );
        $response = new JsonResponse($data);
        return $response;
    }

    /**
     * Writes a tab-delmited entry to the queue file.
     *
     * @param $params ParameterBagInterface
     *   Parameters section of config/services.yaml.
     * @param $nid string
     *   The node ID of the Islandora object to Bag.
     * @param $yaml_path string
     *   The full path to the YAML settings file.
     *
     * @return bool
     *   Whether or not the queue file was written.
     */
    private function writeToQueue(ParameterBagInterface $params, string $nid, string $yaml_path)
    {
        // Write the request to the queue.
        $queue_path = $params->get('app.queue.path');
        $update = false;
        $fp = fopen($queue_path, "a+");
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, "$nid\t$yaml_path\t" . date(\DateTime::ISO8601) . "\n");
            fflush($fp);
            flock($fp, LOCK_UN);
            $update = true;
        }
        fclose($fp);
        return $update;
    }

    /**
     * Reads the queue file and returns it to the client.
     */
    public function getQueue(Request $request, ParameterBagInterface $params, LoggerInterface $logger)
    {
        $queue_path = $params->get('app.queue.path');
        if ($entries = @file($queue_path)) {
            $response = new JsonResponse($entries);
        } else {
            $response = new JsonResponse(array('message' => 'Queue file not found at ' . $queue_path));
        }

        return $response;
    }

}
