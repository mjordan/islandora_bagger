<?php
// src/Command/ProcessQueueCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Psr\Log\LoggerInterface;

class ProcessQueueCommand extends Command
{
    use ContainerAwareTrait;

    private $params;

    private $logger;

    private $application_directory;

    private $queue_path;

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
            ->setName('app:islandora_bagger:process_queue')
            ->setDescription('Console tool for processing items in the Islandora Bagger queue.')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Absolute path to Islandora Bagger queue file.')
            ->addOption('entries', null, InputOption::VALUE_OPTIONAL, 'Number of queue entries to process. ' .
                'If omitted, all entries will be processed.', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue_path = $input->getOption('queue');
        $num_entries_to_process = $input->getOption('entries');
        $entries = file($this->queue_path, FILE_IGNORE_NEW_LINES);
        $num_entries_in_queue = count($entries);
        if ($num_entries_to_process === 0) {
            $num_entries_to_process = $num_entries_in_queue;
        }
        // In case someone enters a negative value.
        if ($num_entries_to_process < 0) {
            $num_entries_to_process = 0;
        }
        for ($i = 1; $i <= $num_entries_to_process; $i++) {
            $current = array_shift($entries);
            list($nid, $path_to_yaml, $timestamp) = explode('	', $current);

            $command = $this->getApplication()->find('app:islandora_bagger:create_bag');
            $options = [
                '--node' => $nid,
                '--settings' => $path_to_yaml,
            ];
            $input = new ArrayInput($options);
            $return_code = $command->run($input, $output);
            $this->log($return_code, $nid, $path_to_yaml, $timestamp);

            // Update the queue file after each entry is processed.
            $this->updateQueue($entries);
        }
    }

    /**
     * Writes the queue file with the updated queue.
     *
     * @param array $entries
     *    The updated (i.e., array_shifted) array of entries in the queue.
     *
     * @return bool
     *   Whether or not the queue file was written.
     */
    private function updateQueue(array $entries): bool
    {
        $updated_queue = implode(PHP_EOL, $entries);

        $update = false;
        $fp = fopen($this->queue_path, "w");
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $updated_queue);
            fflush($fp);
            flock($fp, LOCK_UN);
            $update = true;
        }
        fclose($fp);
        return $update;
    }

    /**
     * Logger function for this command.
     *
     * @param int $return_code
     *   The return code of the create_bag command.
     * @param string $nid
     *   The node ID of the Islandora object being Bagged.
     * @param string $path_to_yaml
     *   The full path to the settings file.
     * @param string $timestamp
     *   ISO8601 timestamp.
     */
    private function log(int $return_code, string $nid, string $path_to_yaml, string $timestamp)
    {
        $details = array(
            'node ID' => $nid,
            'settings file' => $path_to_yaml,
            'exit code' => $return_code,
            'time stamp' => $timestamp,
        );

        if ($this->logger && $return_code === 0) {
            $this->logger->info("Queue entry processed successfully", $details);
        } else {
            $this->logger->warning("Queue entry processed with warning", $details);
        }
    }
}
