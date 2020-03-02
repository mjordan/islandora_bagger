<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

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
     * {@inheritdoc}
     *
     * Adds basic bag-info.txt tags from dynamically generated data.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        $node_id = rtrim($this->settings['drupal_base_url'], '/') . '/node/' . $nid;
        $bag->addBagInfoTag('Internal-Sender-Identifier', $node_id);

        if ($this->settings['log_bag_creation']) {
            $this->logger->info(
                "Internal-Sender-Id Tags added from AddBasicTags plugin.",
                array(
                    'Internal-Sender-Id' => $node_id,
                )
            );
        }

        return $bag;
    }
}
