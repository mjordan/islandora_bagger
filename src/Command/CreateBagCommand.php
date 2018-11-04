<?php
// src/Command/CreateBagCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Stopwatch\Stopwatch;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

require 'vendor/scholarslab/bagit/lib/bagit.php';

class CreateBagCommand extends ContainerAwareCommand
{
    private $params;

    public function __construct(LoggerInterface $logger = null) {
        // Set log output path in config/packages/{environment}/monolog.yaml
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:islandora_bagger:create_bag')
            ->setDescription('Console tool for generating Bags from Islandora content.')
            ->addOption('node', null, InputOption::VALUE_REQUIRED, 'Drupal node ID to create Bag from.')
            ->addOption('settings', null, InputOption::VALUE_REQUIRED, 'Absolute path to YAML settings file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('create_bag');

        $nid = $input->getOption('node');
        $settings_path = $input->getOption('settings');
        $settings = Yaml::parseFile($settings_path);

        @mkdir($settings['output_dir']);
        @mkdir($settings['temp_dir']);

        $client = new \GuzzleHttp\Client();

        // Get the node's UUID from Drupal.
        $drupal_url = $settings['drupal_base_url'] . $nid . '?_format=json';
        $response = $client->get($drupal_url);
        $response_body = (string) $response->getBody();
        $body_array = json_decode($response_body, true);
        $uuid = $body_array['uuid'][0]['value'];

        // Assemble the Fedora URL.
        $uuid_parts = explode('-', $uuid);
        $subparts = str_split($uuid_parts[0], 2);
        $fedora_url = $settings['fedora_base_url'] . implode('/', $subparts) . '/'. $uuid;

        // Get the Turtle from Fedora.
        $response = $client->get($fedora_url);
        $response_body = (string) $response->getBody();

        // Create directories.
        $bag_dir = $settings['output_dir'] . DIRECTORY_SEPARATOR . $nid;
        @mkdir($bag_dir);
        $bag_temp_dir = $settings['temp_dir'] . DIRECTORY_SEPARATOR . $nid;
        @mkdir($bag_temp_dir);

        // Assemble data files.
        $data_files = array();
        $turtle_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'turtle.rdf';
        file_put_contents($turtle_file_path, $response_body);

        // Create the Bag.
        if ($settings['include_basic_baginfo_tags']) {
            $bag_info = array(
                'Internal-Sender-Identifier' => $settings['drupal_base_url'] . $nid,
                'Bagging-Date' => date("Y-m-d"),
            );
        } else {
            $bag_info = array();
        }
        $bag = new \BagIt($bag_dir, true, true, true, $bag_info);
        $bag->addFile($turtle_file_path, basename($turtle_file_path));

        foreach ($settings['bag-info'] as $key => $value) {
            $bag->setBagInfoData($key, $value);
        }

        $bag->update();

        $fixity_check = $stopwatch->stop('create_bag');
        $duration = $fixity_check->getDuration(); // milliseconds
        $duration = $duration / 1000; // seconds
        $output->writeln("Bag created in in $duration seconds.");
    }
}
