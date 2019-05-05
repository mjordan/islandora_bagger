<?php
// src/Command/CreateBagCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\IslandoraBagger;

use Psr\Log\LoggerInterface;

class CreateBagCommand extends ContainerAwareCommand
{
    private $params;

    public function __construct(LoggerInterface $logger = null)
    {
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

        $islandora_bagger = new IslandoraBagger($this->settings, $this->logger);
        $bag_dir = $islandora_bagger->createBag($nid, $settings_path);

        if ($bag_dir) {
            $io->success("Bag created for " . $this->settings['drupal_base_url'] . '/node/' . $nid . " at " . $bag_dir);
        }
        else {
            $io->error("Bag not created for " . $this->settings['drupal_base_url'] . '/node/' . $nid . " at " . $bag_dir);
        }
    }
}
