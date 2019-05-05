<?php
// src/Command/ProcessQueueCommand.php
namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

use Psr\Log\LoggerInterface;

class ProcessQueueCommand extends ContainerAwareCommand
{
    public function __construct(LoggerInterface $logger = null)
    {
        // Set log output path in config/packages/{environment}/monolog.yaml
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:islandora_bagger:process_queue')
            ->setDescription('Console tool for processing items in the Islandora Bagger queue.')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Absolute path to Islandora Bagger queue file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue_path = $input->getOption('queue');
        $entries = file($queue_path, FILE_IGNORE_NEW_LINES);
        foreach ($entries as $entry) {
            list($nid, $path_to_yaml, $timestamp) = explode('	', $entry);
            $command = $this->getApplication()->find('app:islandora_bagger:create_bag');
            $options = [
                '--node' => $nid,
                '--settings' => $path_to_yaml,
            ];
            $input = new ArrayInput($options);
            $return_code = $command->run($input, $output);
            $this->log($return_code, $nid, $path_to_yaml);
        }
    }

    private function log($return_code, $nid, $path_to_yaml)
    {
        $details = array(
            'node ID' => $nid,
            'settings file' => $path_to_yaml,
            'exit code' => $return_code
        );

        if ($this->logger && $return_code === 0) {
            $this->logger->info("Queue entry processed successfully", $details);
        } else {
            $this->logger->warning("Queue entry processed with warning", $details);
        }
    }

}
