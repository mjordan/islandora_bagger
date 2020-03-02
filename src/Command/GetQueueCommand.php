<?php
// src/Command/GetQueueCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Psr\Log\LoggerInterface;

class GetQueueCommand extends ContainerAwareCommand
{
    public function __construct(LoggerInterface $logger = null, ParameterBagInterface $params = null)
    {
        // Set in the parameters section of config/services.yaml.
        $this->params = $params;

        // Set log output path in config/packages/{environment}/monolog.yaml
        $this->logger = $logger;

        $this->application_directory = dirname(__DIR__, 2);

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:islandora_bagger:get_queue')
            ->setDescription('Console tool for viewing items in the Islandora Bagger queue.')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Absolute path to Islandora Bagger queue file.')
            ->addOption('output_format', 'json', InputOption::VALUE_REQUIRED, 'Output format. Defaults to "json". Must be either "csv" or "json".');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue_path = $input->getOption('queue');
        $output_format = $input->getOption('output_format');

        if (!file_exists($this->queue_path)) {
            $this->logger->info("Queue file not found", $details);
            return;
        } 

        $entries = file($this->queue_path, FILE_IGNORE_NEW_LINES);
        $num_entries_in_queue = count($entries);
        if (is_null($output_format) || $output_format == 'json') {
            $entries_as_json = json_encode($entries);
            print $entries_as_json;
        }
        if ($input->getOption('output_format') == 'csv') {
            print file_get_contents($this->queue_path);
        }
    }
}
