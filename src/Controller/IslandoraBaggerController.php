<?php
// src/Controller/IslandoraBaggerController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Service\IslandoraBagger;

use Psr\Log\LoggerInterface;

// For now, until we figure out how to pass in config data.
use Symfony\Component\Yaml\Yaml;

class IslandoraBaggerController extends AbstractController
{
    public function create(Request $request, LoggerInterface $logger)
    {
        $nid = $request->headers->get('Islandora-Node-ID');

        $settings = Yaml::parseFile('/tmp/sample_config.yml');

        $islandora_bagger = new IslandoraBagger($settings, $logger);
        $bag_dir = $islandora_bagger->createBag($nid);

        // Dummy data.
        $data = array(
            'Bag created for ' . $nid,
        );
        $response = new JsonResponse($data);
        return $response;
    }
}
