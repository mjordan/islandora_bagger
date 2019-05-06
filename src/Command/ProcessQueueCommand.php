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

        $this->application_directory = dirname(__DIR__, 2);

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
        $this->queue_path = $input->getOption('queue');
        $entries = file($this->queue_path, FILE_IGNORE_NEW_LINES);
        foreach ($entries as $entry) {
            $current = array_shift($entries);
            list($nid, $path_to_yaml) = explode('	', $current);

            $command = $this->getApplication()->find('app:islandora_bagger:create_bag');
            $options = [
                '--node' => $nid,
                '--settings' => $path_to_yaml,
            ];
            $input = new ArrayInput($options);
            $return_code = $command->run($input, $output);
            $this->log($return_code, $nid, $path_to_yaml);

            // Update the queue file after each entry is processed.
            $this->updateQueue($entries);
        }

    }

    /**
     * Writes the queueu file with the updated queue.
     *
     * @param array $entries
     *    The updated (i.e., array_shifted) array of entries in the queue.
     *
     * @return bool
     *   Whether or not the queue file was written.
     */
    private function updateQueue($entries)
    {
        $updated_queue = implode(PHP_EOL, $entries);

        $fp = fopen($this->queue_path, "w");
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $updated_queue);
            fflush($fp);
            flock($fp, LOCK_UN);
            return true;
        }
        fclose($fp);
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
     */
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
