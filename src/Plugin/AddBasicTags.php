<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Adds basic bag-info.txt tags.
 */
class AddBasicTags extends AbstractIbPlugin
{
    /**
     * Constructor.
     *
     * @param array $settings
     *    The configuration data from the .ini file.
     */
    public function __construct($settings)
    {
        parent::__construct($settings);
    }

    /**
     * Add two basic tags.
     */
    public function execute($bag, $nid)
    {
        $node_url = rtrim('/', $this->settings['drupal_base_url']) . '/node/' . $nid;
        $bag->setBagInfoData('Internal-Sender-Identifier', $node_url);
        $bag->setBagInfoData('Bagging-Date', date("Y-m-d"));
        return $bag;
    }
}
