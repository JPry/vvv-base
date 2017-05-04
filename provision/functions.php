<?php
/**
 *
 */

namespace JPry\VVVBase;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Get an element from an array.
 *
 * @author Jeremy Pry
 *
 * @param string $key     The array key to search for.
 * @param array  $array   The array to search.
 * @param mixed  $default The default value if the element is not in the array.
 *
 * @return mixed The value from the array, or $default if it's not found.
 */
function el($key, $array, $default = null)
{
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }

    return $default;
}

/**
 * Generate a command string for use with exec().
 *
 * @author Jeremy Pry
 *
 * @param array $positional Array of positional arguments.
 * @param array $flags      Array of flags and values.
 * @param bool  $root       Whether to use root. Default false.
 *
 * @return Process
 */
function get_cmd($positional = array(), $flags = array(), $root = true)
{

    $process_builder = new ProcessBuilder();
    if (!$root) {
        $process_builder->setPrefix('sudo -EH -u "vagrant" -- ');
    }

    $process_builder->setArguments($positional);

    foreach ($flags as $flag => $value) {
        // Build flag, including value if truthy
        $cmd = "--{$flag}" . ($value ? "={$value}" : '');
        $process_builder->add($cmd);
    }

    return $process_builder->getProcess();
}

/**
 * Create the log files and directory if necessary.
 *
 * @param string $path
 */
function create_logs($path)
{
    if (!file_exists($path . '/log')) {
        echo "Creating {$path}/log directory...\n";
        mkdir($path . '/log', 0775);
    }

    foreach (array('error.log', 'access.log') as $logfile) {
        $file = "{$path}/log/{$logfile}";
        if (!file_exists($file)) {
            file_put_contents($file, '');
        }
    }
}

/**
 * Get the options passed via CLI.
 *
 * @return array
 */
function get_cli_options()
{
    return getopt('', get_flags());
}

/**
 * Get the array of options we recognize.
 *
 * @return array
 */
function get_options()
{
    return array(
        'site',
        'site_escaped',
        'vm_dir',
        'vvv_path_to_site',
        'vvv_config',
    );
}

/**
 * Convert the array of options into flags for CLI.
 *
 * @return array
 */
function get_flags()
{
    $flags = get_options();
    foreach ($flags as &$flag) {
        $flag .= ':';
    }

    return $flags;
}

/**
 * Create the table in the database.
 *
 * @param string $name The database name.
 */
function create_db($name)
{
    echo "Connecting to DB...\n";
    $db = new \mysqli('localhost', 'root', 'root');
    if ($db->connect_errno) {
        echo "DB connect error! ABORTING...\n";
        exit(1);
    }

    echo "Checking database for site...\n";
    $result = $db->query("SHOW DATABASE LIKE '{$name}'");
    if (empty($result)) {
        echo "Creating DB for {$name}\n";
        $db->query("CREATE DATABASE `{$name}`;");
        echo "Granting privileges on DB...\n";
        $db->query("GRANT ALL PRIVILEGES ON `{$name}`.* TO wp@localhost IDENTIFIED BY 'wp'");
        echo "DB setup complete.\n";
    }
}

/**
 * Create the vvv-nginx.conf file.
 *
 * @param string $main_host The main host for the site.
 * @param array  $hosts     All hosts for the site.
 * @param bool   $xipio     Whether to include Xip.io support.
 */
function create_nginx($main_host, $hosts, $xipio)
{
    echo "Setting up Nginx config\n";
    $nginx_config = __DIR__ . '/vvv-nginx.conf';
    $contents = !file_exists($nginx_config) ? file_get_contents(__DIR__ . '/vvv-nginx.template') : file_get_contents(
        $nginx_config
    );

    // Build the hosts directive, maybe including xipio.
    $nginx_hosts = join(' ', $hosts);
    if ($xipio) {
        $xipio_base = preg_replace('#(.*)\.[a-zA-Z0-9_]+$#', '$1', $main_host);
        $nginx_xipio = str_replace(
                '.',
                '\\.',
                $xipio_base
            ) . '\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.xip\\\\.io$';
        $nginx_hosts .= " {$nginx_xipio}";
    }

    $contents = preg_replace('#(server_name\s*)(?:[^;]*);#', "\$1{$nginx_hosts};", $contents);
    $contents = str_replace('{wp_main_host}', $main_host, $contents);
    file_put_contents($nginx_config, $contents);
}

/**
 * Validate that we were passed all of our flags that we need.
 *
 * @param array $options Parsed CLI options.
 *
 * @throws \Exception When some of the flags are missing.
 */
function validate_flags($options)
{
    $missing_flags = array_diff_key(array_flip(get_options()), $options);
    if (!empty($missing_flags)) {
        throw new \Exception('Missing flags from command line: ' . join(', ', $missing_flags), 1);
    }
}

/**
 * Validate that the site we need is found in the config array.
 *
 * @param array  $config The array of config data.
 * @param string $site   The site to validate.
 *
 * @throws \Exception When the site is not found in the config array.
 */
function validate_site($config, $site)
{
    if (!isset($config['sites'][$site])) {
        throw new \Exception("Cannot find site in config: {$site}", 2);
    }
}

/**
 * Get the config for the current site.
 *
 * @param array  $config The whole config file.
 * @param string $site   The name of the current site.
 *
 * @return array
 */
function get_site_config($config, $site)
{
    $current_site = $config['sites'][$site];
    $custom = el('custom', $current_site, array());
    $hosts = el('hosts', $current_site, array("{$site}.local"));

    // Set up variables we'll need for the current site.
    $site = array(
        'user'      => el('admin_user', $custom, 'admin'),
        'pass'      => el('admin_password', $custom, 'password'),
        'email'     => el('admin_email', $custom, 'admin@localhost.local'),
        'title'     => el('title', $custom, 'My Awesome VVV site'),
        'db_prefix' => el('prefix', $custom, 'wp_'),
        'multisite' => el('multisite', $custom, false),
        'xipio'     => el('xipio', $custom, true),
        'main_host' => $hosts[0],
        'hosts'     => $hosts,
    );

    return $site;
}

/**
 * Create the wp-config.php file.
 *
 * @param string $site   The site/database name.
 * @param string $prefix The database prefix.
 */
function create_wp_config($site, $prefix)
{
    $extra_php = <<<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'SCRIPT_DEBUG', true );
define( 'JETPACK_DEV_DEBUG', true );
define( 'JETPACK_STAGING_MODE', true );
PHP;

    echo get_cmd(
        array('wp', 'config', 'create'),
        array(
            'force'     => null,
            'dbname'    => $site,
            'dbuser'    => 'wp',
            'dbpass'    => 'wp',
            'dbhost'    => 'localhost',
            'dbprefix'  => $prefix,
            'locale'    => 'en_US',
            'extra-php' => $extra_php,
        )
    )->mustRun()->getOutput();
}


function downloadWordPress($version = 'latest', $locale = 'en_US')
{
    echo get_cmd(
        array('wp', 'core', 'download'),
        array(
            'locale'  => $locale,
            'version' => $version,
        )
    )->mustRun()->getOutput();
}
