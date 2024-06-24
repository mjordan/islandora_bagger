<?php
// src/Command/CreateBagCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use App\Service\IslandoraBagger;

use Psr\Log\LoggerInterface;

class CreateBagCommand extends Command
{
    private $logger;
    private $params;
    private $settings;

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
            ->addOption('settings', null, InputOption::VALUE_REQUIRED, 'Absolute path to YAML settings file.')
          ->addOption('extra', null, InputOption::VALUE_OPTIONAL, 'Serialized JSON object containing key:value settings.')
          ->addOption('token', null, InputOption::VALUE_OPTIONAL, 'JWT token for authentication.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ret;

        $io = new SymfonyStyle($input, $output);

        $nid = $input->getOption('node');
        $settings_path = $input->getOption('settings');
        $this->settings = Yaml::parseFile($settings_path);
        $token = $input->getOption('token');

        // Loop through $this->params and add each param to $this->settings with a
        // key sans the 'app.' namespace.
        $params_keys = array_keys($this->params->all());
        foreach ($params_keys as $param_key) {
            if (preg_match('/^app\./', $param_key)) {
                $param_key_trimmed = preg_replace('/^app\./', '', $param_key);
                // If the parameter defined in config/settings.yml doesn't exist in the
                // per-Bag config file, add it to the settings array. We use this logic
                // so we can add any parameter defined in config/services.yml to the
                // settings array while stil being able to override parameters in
                // config/services.yml in the per-Bag config file.
                if (!array_key_exists($param_key_trimmed, $this->settings)) {
                    $this->settings[$param_key_trimmed] = $this->params->get($param_key);
                }
            }
        }

        // Add or override config settings via the command line.
        if (!is_null($input->getOption('extra'))) {
            $extra = json_decode($input->getOption('extra'), true);
            foreach ($extra as $extra_setting_key  => $extra_setting_value) {
                $this->settings[$extra_setting_key] = $extra_setting_value;
            }
        }

        $this->settings['log_bag_location'] = (!isset($this->settings['log_bag_location'])) ?
            false : $this->settings['log_bag_location'];
        $this->settings['post_bag_scripts'] = (!isset($this->settings['post_bag_scripts'])) ?
            array() : $this->settings['post_bag_scripts'];

        $islandora_bagger = new IslandoraBagger($this->settings, $this->logger, $this->params, $token);
        $bag_dir = $islandora_bagger->createBag($nid, $settings_path, $token);

        if ($bag_dir) {
            $io->success("Bag created for " . $this->settings['drupal_base_url'] . '/node/' . $nid
                . " at " . $bag_dir);

            if (count($this->settings['post_bag_scripts']) > 0) {
                foreach ($this->settings['post_bag_scripts'] as $script) {
                    $script = $script . " $nid $bag_dir $settings_path";
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
            $ret = Command::SUCCESS;
        } else {
            $io->error("Bag not created for " . $this->settings['drupal_base_url'] . '/node/' . $nid
                . " at " . $bag_dir);
            $ret = Command::FAILURE;
        }
        return $ret;
    }
}
