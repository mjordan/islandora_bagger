<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds basic bag-info.txt tags from dynamically generated data.
 */
class AddBasicTags extends AbstractIbPlugin
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
     * Adds basic bag-info.txt tags from dynamically generated data.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $node_id = rtrim($this->settings['drupal_base_url'], '/') . '/node/' . $nid;
        $bag->setBagInfoData('Internal-Sender-Identifier', $node_id);
        $bagging_date = date("Y-m-d");
        $bag->setBagInfoData('Bagging-Date', $bagging_date);

        if ($this->settings['log_bag_creation']) {
            $this->logger->info(
                "Internal-Sender-Id and Bagging-Date Tags added from AddBasicTags plugin.",
                array(
                    'Internal-Sender-Id' => $node_id,
                    'Bagging-Date' => $bagging_date,
                )
            );
        }

        return $bag;
    }
}
