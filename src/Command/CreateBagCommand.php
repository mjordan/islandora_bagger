<?php
// src/Command/CreateBagCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);

        $nid = $input->getOption('node');
        $settings_path = $input->getOption('settings');
        $this->settings = Yaml::parseFile($settings_path);
        $this->settings['drupal_base_url'] .= '/node/';

        if (!file_exists($this->settings['output_dir'])) {
            mkdir($this->settings['output_dir']);
        }
        if (!file_exists($this->settings['temp_dir'])) {
            mkdir($this->settings['temp_dir']);
        }

        $client = new \GuzzleHttp\Client();

        // Get the node's UUID from Drupal.
        $drupal_url = $this->settings['drupal_base_url'] . $nid . '?_format=json';
        $response = $client->get($drupal_url);
        $response_body = (string) $response->getBody();
        $body_array = json_decode($response_body, true);
        $uuid = $body_array['uuid'][0]['value'];

        if ($this->settings['bag_name'] == 'uuid') {
            $bag_name = $uuid;
        } else {
            $bag_name = $nid;
        }

        // Assemble the Fedora URL.
        $uuid_parts = explode('-', $uuid);
        $subparts = str_split($uuid_parts[0], 2);
        $fedora_url = $this->settings['fedora_base_url'] . implode('/', $subparts) . '/'. $uuid;

        // Get the Turtle from Fedora.
        $response = $client->get($fedora_url);
        $response_body = (string) $response->getBody();

        // Create directories.
        $bag_dir = $this->settings['output_dir'] . DIRECTORY_SEPARATOR . $bag_name;
        if (!file_exists($bag_dir)) {
            mkdir($bag_dir);
        }
        $bag_temp_dir = $this->settings['temp_dir'] . DIRECTORY_SEPARATOR . $bag_name;
        if (!file_exists($bag_temp_dir)) {
            mkdir($bag_temp_dir);
        }

        $media_file_paths = $this->fetch_media($nid, $bag_temp_dir);

        // Assemble data files. Fow now we only have one.
        $data_files = array();
        $turtle_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'turtle.rdf';
        file_put_contents($turtle_file_path, $response_body);

        // Create the Bag.
        if ($this->settings['include_basic_baginfo_tags']) {
            $bag_info = array(
                'Internal-Sender-Identifier' => $this->settings['drupal_base_url'] . $nid,
                'Bagging-Date' => date("Y-m-d"),
            );
        } else {
            $bag_info = array();
        }
        $bag = new \BagIt($bag_dir, true, true, true, $bag_info);
        $bag->addFile($turtle_file_path, basename($turtle_file_path));
        foreach ($media_file_paths as $media_file_path) {
            $bag->addFile($media_file_path, basename($media_file_path));
        }

        foreach ($this->settings['bag-info'] as $key => $value) {
            $bag->setBagInfoData($key, $value);
        }

        $bag->update();
        $this->remove_dir($bag_temp_dir);

        $package = isset($this->settings['serialize']) ? $this->settings['serialize'] : false;
        if ($package) {
           $bag->package($bag_dir, $package);
           $this->remove_dir($bag_dir);
           $bag_name = $bag_name . '.' . $package;
        }

        $io->success("Bag created for node " . $nid . " at " . $bag_dir);
        if ($this->settings['log_bag_creation']) {
            $this->logger->info(
                "Bag created.",
                array(
                    'node URL' => $this->settings['drupal_base_url'] . $nid,
                    'node UUID' => $uuid,
                    'Bag location' => $this->settings['output_dir'],
                    'Bag name' => $bag_name
                )
            );
        }
    }

    protected function fetch_media($nid, $bag_temp_dir)
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
        $media_list = json_decode($media_list, true);

        // Loop through all the media and pick the ones that are tagged with terms in $taxonomy_terms_to_check.
        foreach ($media_list as $media) {
            if (count($media['field_media_use'])) {
                $media_file_paths = array();
                foreach ($media['field_media_use'] as $term) {
                    if (in_array($term['url'], $this->settings['drupal_media_tags'])) {
                        if (isset($media['field_media_image'])) {
                            $file_url = $media['field_media_image'][0]['url'];
                        } else {
                            $file_url = $media['field_media_file'][0]['url'];
                        }
                        $filename = $this->get_filename_from_url($file_url);
                        $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $filename;
                        // Fetch file and save it to $bag_temp_dir with its original filename.
                        $file_client = new \GuzzleHttp\Client();
                        $file_response = $file_client->get($file_url, ['stream' => true,
                            //'timeout' => $this->settings['http_timeout'],
                            //'connect_timeout' => $this->settings['http_timeout'],
                            //'verify' => $this->settings['verifyCA']
                        ]);
                        $file_body = $file_response->getBody();
                        while (!$file_body->eof()) {
                            file_put_contents($temp_file_path, $file_body->read(2048), FILE_APPEND);
                        }
                        $media_file_paths[] = $temp_file_path;
                    }
                }
                return $media_file_paths;
            }
        }
    }

    /**
     * Deletes a directory and all of its contents.
     *
     * @param $dir string
     *   Path to the directory.
     *
     * @return bool
     *   True if the directory was deleted, false if not.
     *
     */
    protected function remove_dir($dir)
    {
        // @todo: Add list here of invalid $dir values, e.g., /, /tmp.
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->remove_dir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    protected function get_filename_from_url($url)
    {
        $path = parse_url('http://example.com/foo/bar/baz.jpg', PHP_URL_PATH);
        $filename = pathinfo($path, PATHINFO_BASENAME);
        return $filename;
    }

}
