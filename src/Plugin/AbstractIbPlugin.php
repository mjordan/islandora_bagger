<?php

/**
 * @file
 * Defines the AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Abstract class for plugins.
 */
abstract class AbstractIbPlugin 
{
    /**
     * Constructor.
     *
     * @param array $config
     *    The configuration data from the .ini file.
     */
    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    /**
     * Modifies the current Bag.
     *
     * All plugins must implement this method.
     *
     * @param object $bag
     *    The Bag object.
     * @param int $nid
     *    The node ID.
     *
     * @return The modified Bag.
     */
    abstract public function execute($bag, $nid);
}
