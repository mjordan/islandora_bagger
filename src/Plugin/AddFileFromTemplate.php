<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use \Twig\Twig;

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
     * Adds file created from a Twig template, with values from a specified source.
     */
    public function execute($bag, $bag_temp_dir, $nid, $node_json)
    {
        $metadata = json_decode($node_json, true);
        var_dump($metadata);

        $loader = new \Twig_Loader_Filesystem(dirname($this->settings['template_path']));
        $twig = new \Twig_Environment($loader);
        $output_from_template = $twig->render(basename($this->settings['template_path']), (array) $metadata);

        $template_output_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $this->settings['templated_output_filename'];
        file_put_contents($template_output_file_path, trim($output_from_template));
        $bag->addFile($template_output_file_path, $this->settings['templated_output_filename']);

        return $bag;
    }
}
