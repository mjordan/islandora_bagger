<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use \Twig\Twig;
use whikloj\BagItTools\Bag;

/**
 * Adds a file created from a Twig template.
 */
class AddFileFromTemplate extends AbstractIbPlugin
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
     * Adds file created from a Twig template, with values from the node's JSON.
     * The node's canonical URL is added to the metadata array, in 'node_url'.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
    {
        $metadata = json_decode($node_json, true);
        $metadata['node_url'] = rtrim($this->settings['drupal_base_url'], '/') . '/node/' . $nid;

        $loader = new \Twig_Loader_Filesystem(dirname($this->settings['template_path']));
        $twig = new \Twig_Environment($loader);
        $output_from_template = $twig->render(basename($this->settings['template_path']), (array) $metadata);

        $template_output_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $this->settings['templated_output_filename'];
        $bag->createFile($output_from_template, $this->settings['templated_output_filename']);

        return $bag;
    }
}
