<?php
/**
 *
 */

namespace JPry\VVVBase;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// Composer autoloading.
require_once(__DIR__ . '/vendor/autoload.php');

// Load helper functions.
require_once(__DIR__ . '/functions.php');

// Grab CLI options
$options = get_cli_options();

try {
    // Ensure we have all of the necessary options.
    validate_flags($options);

    // Parse the config file.
    $config = Yaml::parse(file_get_contents($options['vvv_config']));

    // Ensure we can find the site in the config array.
    validate_site($config, $options['site_escaped']);

} catch (ParseException $e) {
    echo "Unable to parse config file: {$options['vvv_config']}\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit($e->getCode());
}

// Set up logger.
$stream = new StreamHandler('php://stdout', Logger::INFO);
$stream->setFormatter(new LineFormatter("%channel%.%level_name%: %message%\n"));
$logger = new Logger('provisioner', array($stream));

// Set up and run our provisioner.
echo "Connecting to DB...\n";
$db = new \mysqli('localhost', 'root', 'root');
if ($db->connect_errno) {
    echo "DB connect error! ABORTING...\n";
    exit(1);
}

$provisioner = new Provisioner(
    new ProcessBuilder(),
    $db,
    $options['vm_dir'],
    $options['site_escaped'],
    $config['sites'][$options['site_escaped']],
    $logger
);
$provisioner->provision();

