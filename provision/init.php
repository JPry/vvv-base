<?php

namespace JPry\VVVBase;

use JPry\VVVBase\Configuration\Site;
use JPry\VVVBase\Configuration\VBExtra;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

// Composer autoloading.
require_once(__DIR__ . '/vendor/autoload.php');

// Load helper functions.
require_once(__DIR__ . '/functions.php');

// Grab CLI options
$options = get_cli_options();

// Set up logger.
$stream = new StreamHandler('php://stdout', Logger::INFO);
$stream->setFormatter(new LineFormatter("%channel%: [%level_name%] %message%\n"));
$logger = new Logger('init', [$stream]);

try {
    // Set up filesystem object.
    $filesystem = new Filesystem();

    // Ensure we have all of the necessary options.
    validate_flags($options);

    // Parse the config file.
    $config = Yaml::parse(file_get_contents($options['vvv_config']));

    // Ensure we can find the site in the config array.
    validate_site($config, $options['site_escaped']);

    // Ensure the site config is valid.
    $siteConfig = $config['sites'][$options['site_escaped']];
    $processor  = new Processor();
    $site       = $processor->processConfiguration(new Site(), [$siteConfig]);

    // Process vvvbase config
    $vvvBase = ($processor->processConfiguration(new VBExtra(), [$config]))['vvvbase'];

    // Set up and run our provisioner.
    $logger->info('Connecting to the DB...');
    $db = new \mysqli($vvvBase['db']['host'], $vvvBase['db']['user'], $vvvBase['db']['pass']);
    if ($db->connect_errno) {
        throw new \Exception("Unable to connect to DB. Error: {$db->connect_error}", 1);
    }

    $container = new ProvisionContainer();
    $container->addProvisioner(new DBProvisioner(
        $options['site_escaped'],
        $db,
        new Logger('DBProvisioner', [$stream]),
        $filesystem
    ));
    $container->addProvisioner(new Provisioner(
        new ProcessBuilder(),
        $options['vm_dir'],
        $options['site_escaped'],
        $site,
        new Logger('Provisioner', [$stream]),
        $vvvBase
    ));

    $container->provision();

} catch (ParseException $e) {
    $logger->error("Unable to parse config file: {$options['vvv_config']}");
    $logger->error($e->getMessage());
    exit(1);
} catch (\Exception $e) {
    $logger->error($e->getMessage());
    $code = $e->getCode() ?: 1;
    exit($code);
}

