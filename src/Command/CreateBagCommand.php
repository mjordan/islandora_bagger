<?php
// src/Command/CreateBagCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\IslandoraBagger;

use Psr\Log\LoggerInterface;

class CreateBagCommand extends ContainerAwareCommand
{
    private $params;

    public function __construct(LoggerInterface $logger = null, ParameterBagInterface $params = null)
    {
        // Set in the parameters section of config/services.yaml.
        $this->params = $params;

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

        $this->settings['log_bag_location'] = (!isset($this->settings['log_bag_location'])) ?
            false : $this->settings['log_bag_location'];
        $this->settings['post_bag_scripts'] = (!isset($this->settings['post_bag_scripts'])) ?
            array() : $this->settings['post_bag_scripts'];

        $islandora_bagger = new IslandoraBagger($this->settings, $this->logger, $this->params);
        $bag_dir = $islandora_bagger->createBag($nid, $settings_path);

        if ($bag_dir) {
            $io->success("Bag created for " . $this->settings['drupal_base_url'] . '/node/' . $nid
                . " at " . $bag_dir);

            if (count($this->settings['post_bag_scripts']) > 0) {
                foreach ($this->settings['post_bag_scripts'] as $script) {
                    $script = $script . " $nid $bag_dir";
                    exec($script, $script_output, $script_return);
                    $script_details = array(
                        'node ID' => $nid,
                        'script' => $script,
                        'exit code' => $script_return
                    );
                    if ($this->logger) {
                        if ($script_return == 0) {
                            $this->logger->info("Post-Bag script ran successfully", $script_details);
                        } else {
                            $this->logger->warning("Post-Bag script encountered a problem", $script_details);
                        }
                    }
                }
            }
        } else {
            $io->error("Bag not created for " . $this->settings['drupal_base_url'] . '/node/' . $nid
                . " at " . $bag_dir);
        }
    }
}
