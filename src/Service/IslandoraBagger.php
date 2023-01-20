<?php
// src/Service/IslandoraBagger.php
namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use whikloj\BagItTools\Bag;


class IslandoraBagger
{
    public function __construct($settings, $logger, $params)
    {
        $this->params = $params;

        $this->settings = $settings;
        $this->logger = $logger;

        $this->application_directory = dirname(__DIR__, 2);
    }

    /**
     * Create a Bag for the current node.
     *
     * @param string $nid
     *   The node ID.
     * @param string $settings_path
     *   The path to the settings YAML file passed in from the Create Bag command.
     * @param string $token
     *   A JWT token
     *
     * @return string|bool
     *   The path to the Bag if successful, false if unsuccessful.
     *   If Bag is serialized, path includes path and Bag filename.
     *
     * @throws \whikloj\BagItTools\Exceptions\BagItException
     *   Problems creating the bag, adding files or writing to disk.
     */
    public function createBag(string $nid, string $settings_path, string $token='')
    {
      $this->setDefaults();

      // If no token is provided on the command-line, login using credentials in settings.
      if (empty($token)) {
        $token = $this->retrieveJwtToken();
      }

      if (empty($token)) {
        $this->logger->info("JWT token not supplied or received on login. This is OK unless needing access to unpublished content");
      }

      $client = new Client();

        // Get the node's UUID from Drupal.
        $drupal_url = $this->settings['drupal_base_url'] . '/node/' . $nid . '?_format=json';
	      $response = $client->get($drupal_url, ['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $response_body = (string) $response->getBody();
        $node_json = $response_body;
        $body_array = json_decode($response_body, true);
        $uuid = $body_array['uuid'][0]['value'];

        if ($this->settings['bag_name'] == 'uuid') {
            $bag_name = $uuid;
        } else {
            $bag_name = $nid;
        }

        if (array_key_exists('bag_name_template', $this->settings)) {
            $bag_name = preg_replace('/%/', $bag_name, $this->settings['bag_name_template']);
        }

        // Ensure bag directories don't exist. They are created by the Bag library.
        $bag_dir = $this->settings['output_dir'] . DIRECTORY_SEPARATOR . $bag_name;
        if (file_exists($bag_dir)) {
            $this->removeDir($bag_dir);
        }

        $bag_temp_dir = $this->settings['temp_dir'] . DIRECTORY_SEPARATOR . $bag_name;
        if (file_exists($bag_temp_dir)) {
            $this->removeDir($bag_temp_dir);
        }
        mkdir($bag_temp_dir);

        // Create the Bag.
        $bag = Bag::create($bag_dir);
        $bag->setExtended(true);
        if (is_string($this->settings['hash_algorithm'])) {
            $bag->setAlgorithm($this->settings['hash_algorithm']);
        }
        if (is_array($this->settings['hash_algorithm'])) {
            if (count($this->settings['hash_algorithm']) == 1) {
                $bag->setAlgorithm($this->settings['hash_algorithm'][0]);
            } else {
                $first_algo = array_shift($this->settings['hash_algorithm']);
                $bag->setAlgorithm($first_algo);
                foreach ($this->settings['hash_algorithm'] as $secondary_algo) {
                    $bag->addAlgorithm($secondary_algo);
                }
            }
        }

        // Add tags registered in the config file.
        foreach ($this->settings['bag-info'] as $key => $value) {
            $bag->addBagInfoTag($key, $value);
        }

        // Execute registered plugins.
        foreach ($this->settings['plugins'] as $plugin) {
            $plugin_name = 'App\Plugin\\' . $plugin;
            $bag_plugin = new $plugin_name($this->settings, $this->logger);
            $bag = $bag_plugin->execute($bag, $bag_temp_dir, $nid, $node_json, $token);
        }

        $bag->update();
        $this->removeDir($bag_temp_dir);

        $package = $this->settings['serialize'] ?? false;
        if ($package) {
            $bag_file_path = $this->settings['output_dir'] . DIRECTORY_SEPARATOR . $bag_name  . '.' . $package;
            if (file_exists($bag_file_path)) {
                @unlink($bag_file_path);
            }
            $bag->finalize();
            $this->registerBagWithIslandora($nid, $bag_name, $bag, $token);
            $bag->package($bag_file_path);
            $this->removeDir($bag_dir);
            if ($this->settings['log_bag_location']) {
                $this->logBagLocation($nid, $bag_name . '.' . $package);
            }
        } else {
          $bag->finalize();
          $this->registerBagWithIslandora($nid, $bag_name, $bag, $token);
        }

        if ($this->settings['log_bag_creation']) {
            $this->logger->info(
                "Bag created.",
                array(
                    'node URL' => $this->settings['drupal_base_url'] . '/node/' . $nid,
                    'node UUID' => $uuid,
                    'Bag location' => $this->settings['output_dir'],
                    'Bag name' => $bag_name
                )
            );
        }

        if ($this->settings['delete_settings_file']) {
            unlink(realpath($settings_path));
        }


        // @todo: Return Bag directory path on success or false failure.
        if ($package) {
            return $bag_file_path;
        } else {
            return $bag_dir;
        }
    }

    /**
     * @param object $bag
     *  The Bag object.
     *
     * @return string
     *   The Payload-Oxum value.
     */
    protected function generateOctetstreamSum($bag)
    {
        $file_counter = 0;
        $filesize_sum = 0;
        foreach ($bag->getBagContents() as $file_path) {
             $file_counter++;
             $filesize_sum = filesize($file_path) + $filesize_sum;
        }
        return $filesize_sum . '.' . $file_counter;
    }

    /**
     * @param string $nid
     *  The node ID of the Islandora object.
     *
     * @return bool
     *   Whether or not the location was logged.
     */
    protected function logBagLocation($nid, $bag_name)
    {
        $location_log_path = $this->params->get('app.location.log.path');
        $now_iso8601 = date(\DateTime::ISO8601);
        $bag_location = $this->params->get('app.bag.download.prefix') . $bag_name;
        $data = $nid . "\t" . $bag_location . "\t" . $now_iso8601 . PHP_EOL;
        if (file_put_contents($location_log_path, $data, FILE_APPEND)) {
            return true;
        } else {
            return false;
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
     */
    protected function removeDir($dir)
    {
        $invalid_dirs = array('/', '/tmp');
        if (in_array($dir, $invalid_dirs)) {
            $this->logger->warning(
                "Directory is in list of directories to not remove.",
                array('Directory' => $dir)
            );
            return false;
        }
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Registers the Bag with Islandora Bagger Integration.
     *
     * @param string $nid
     *   The node ID.
     * @param string $bag_name
     *   The Bag name.
     * @param \whikloj\BagItTools\Bag $bag
     *  The Bag object.
     * @param ?string $token
     *  JWT authorization token.
     */
    protected function registerBagWithIslandora(string $nid, string $bag_name, Bag $bag, ?string $token = NULL)
    {
        if (!$this->settings['register_bags_with_islandora']) {
          return;
        }

        $bag_info_contents = file_get_contents($bag->makeAbsolute('bag-info.txt'));
        $manifest_contents = file_get_contents($bag->makeAbsolute('manifest-' . $this->settings['hash_algorithm'] . '.txt'));
        $fetch_path = $bag->makeAbsolute('fetch.txt');
        if (file_exists($fetch_path)) {
          $fetch_contents = file_get_contents($fetch_path);
        } else {
          $fetch_contents = '';
        }

        $post_data = [
            'nid' => $nid,
            'bag_name' => $bag_name,
            'bagit_version' => $bag->getVersionString(),
            'hash_algorithm' => $this->settings['hash_algorithm'],
            'manifest' => $manifest_contents,
            'bag_info' => $bag_info_contents,
            'fetch' => $fetch_contents,
        ];
        $post_data = json_encode($post_data);

        $username = $this->settings['drupal_basic_auth'][0];
        $password = $this->settings['drupal_basic_auth'][1];

        $client = new Client(['http_errors' => false]);
        $drupal_url = $this->settings['drupal_base_url'] . '/islandora_bagger_integration/bag_log';
        $response = $client->post($drupal_url,
          ['headers' => ['Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $token], 'body' => $post_data]);
        $response_body = (string) $response->getBody();
        $response_body = json_decode($response_body, TRUE);
        $this->logger->info(
            "Bag registered with Islandora Bagger Integration.",
            array(
               'node URL' => $this->settings['drupal_base_url'] . '/node/' . $nid,
               'entry ID' => $response_body['islandora_bagger_integration_bag_log_id']
            )
        );
    }

  /**
   * Makes a login POST request to the Drupal site and retrieves
   * a JWT token. The site must be running the Get JWT on Login module.
   * @return mixed|null
   */
    protected function retrieveJwtToken() {
      $base_url = $this->settings['drupal_base_url'];

      try {
        $client = new Client([
          'base_url' => $base_url,
          'allow_redirects' => TRUE,
        ]);
        [$name, $pass] = $this->settings['drupal_basic_auth'];
        $response = $client->post($base_url . '/user/login?_format=json', [
          'json' => ['name' => $name, 'pass' => $pass],
        ]);
        $response_body = (string) $response->getBody();

        $body_array = json_decode($response_body, true);
      } catch (RequestException $e) {
        $this->logger->error("Request for login token returned error.", [
          'Code' => $response->getStatusCode(),
          'Message' => $e->getMessage()
        ]);
        return NULL;
      }

      // Validate the response contains a JWT
      if (!empty($body_array['csrf_token']) && empty($body_array['access_token'])) {
        $this->logger->error("Logged in successfully but no JWT token was received. ' 
        . 'Ensure that the Drupal site has the Get JWT on Login module installed and enabled.", [
          'name' => $name
        ]);
        return NULL;
      }
      elseif (empty($body_array['access_token'])) {
        $this->logger->error("Invalid response body from Drupal site. Check debug info.");
        $this->logger->debug("Response body", [
          'body' => $response_body
        ]);
      }

      $this->logger->info("Retrieved login token for user.", [
        'name' => $name
      ]);
      return $body_array['access_token'];
    }

  /**
   * Set some configuration defaults.
   * @return void
   */
  private function setDefaults(): void {

    $this->settings['http_timeout'] = (!isset($this->settings['http_timeout'])) ?
      60 : $this->settings['http_timeout'];

    $this->settings['verify_ca'] = (!isset($this->settings['verify_ca'])) ?
      TRUE : $this->settings['verify_ca'];
    $this->settings['hash_algorithm'] = (!isset($this->settings['hash_algorithm'])) ?
      'sha1' : $this->settings['hash_algorithm'];
    $this->settings['include_payload_oxum'] = (!isset($this->settings['include_payload_oxum'])) ?
      TRUE : $this->settings['include_payload_oxum'];
    $this->settings['delete_settings_file'] = (!isset($this->settings['delete_settings_file'])) ?
      FALSE : $this->settings['delete_settings_file'];
    $this->settings['log_bag_location'] = (!isset($this->settings['log_bag_location'])) ?
      FALSE : $this->settings['log_bag_location'];
    $this->settings['register_bags_with_islandora'] = (!isset($this->settings['register_bags_with_islandora'])) ?
      FALSE : $this->settings['register_bags_with_islandora'];

    if (!file_exists($this->settings['output_dir'])) {
      mkdir($this->settings['output_dir']);
    }
    if (!file_exists($this->settings['temp_dir'])) {
      mkdir($this->settings['temp_dir']);
    }
  }
}
