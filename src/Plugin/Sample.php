<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Sample Islandora Bagger plugin.
 */
class Sample extends AbstractIbPlugin
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
     * Sample Islandora Bagger plugin.
     *
     * All plugins must implement an execute() method.
     *
     * @param object $bag
     *    The Bag object.
     * @param string $bag_temp_dir
     *    The absolute path to the directory where content files, etc. are to be downloaded.
     * @param int $nid
     *    The node ID.
     * @param string $node_json
     *    The node's JSON representation.
     *
     * @return The modified Bag.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        // Assemble, fetch, or copy data from somewhere to add
        // to the Bag.
        $my_data = "This is a special file to include in the Bag.";

        // All files you want to include in the Bag need to be
        // written to $bag_temp_dir. 
        $my_data_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'my.data';
        file_put_contents($my_data_file_path, $my_data);

        // Then you must call the $bag->addFile() method.
        $bag->addFile($my_data_file_path, 'my.data');

        // We also want to add a custom bag-info.txt tag.
        $bag->setBagInfoData('My-Custom-Tag', "some value");

        // Log something, for example, a value from the configuration file.
        $this->logger->info(
            "Hello from Drupal.",
            array(
                'drupal_url' => $this->settings['drupal_base_url'],
            )
        );

        // Return the modified Bag object.
        return $bag;
    }
}
