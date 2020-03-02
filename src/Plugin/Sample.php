<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

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
     * @param Bag $bag
     *    The Bag object.
     * @param string $bag_temp_dir
     *    The absolute path to the directory where content files, etc. are to be downloaded.
     * @param int $nid
     *    The node ID.
     * @param string $node_json
     *    The node's JSON representation.
     *
     * @return Bag
     *    The modified Bag.
     *
     * @throws \whikloj\BagItTools\BagItException
     *    Problems creating or modifying the bag.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        // Assemble, fetch, or copy data from somewhere to add
        // to the Bag.
        $my_data = "This is a special file to include in the Bag.";

        // You can write content directly to a new file using the BagIt library's
        // createFile() method, which adds the resulting file to the Bag.
        // $bag->createFile($my_data, 'mydata.dat']);

        // If you put other files into the Bag's $bag_temp_dir, they will be
        // added to the Bag
        $my_data_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . 'mydata.dat';
        copy($some_external_file_path, $my_data_file_path);
        // Then you must call the $bag->addFile() method.
        $bag->addFile($my_data_file_path, 'mydata.dat');

        // You may want to add a custom bag-info.txt tag.
        $bag->addBagInfoTag('My-Custom-Tag', "some value");

        // And you probaby should log something, for example, a value from the configuration file.
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
