<?php
/**
 *
 */

// Composer autoloading.
require_once(dirname(__DIR__) . '/vendor/autoload.php');

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

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
 *
 *
 * @author Jeremy Pry
 *
 * @param array $positional Array of positional arguments.
 * @param array $flags      Array of flags and values.
 * @param bool  $root       Whether to use root. Default false.
 *
 * @return string
 */
function get_cmd($positional = array(), $flags = array(), $root = false)
{
    $pre = $root ? '' : 'sudo -EH -u "vagrant" -- ';
    $cmd = $pre;

    // Sanitize!
    $positional = array_map('escapeshellarg', $positional);
    $cmd .= join(' ', $positional) . '';

    foreach ($flags as $flag => $value) {
        // Build flag, including value if truthy
        $_cmd = "--{$flag}" . ($value ? "={$value}" : '');
        $cmd .= ' ' . escapeshellarg($_cmd) . '';
    }

    return $cmd;
}

// Grab CLI options
$flags = array(
    'site:',
    'site_escaped:',
    'vm_dir:',
    'vvv_path_to_site:',
    'vvv_config:',
);
$options = getopt('', $flags);

// Set up required options.
$required_options = array();
foreach ($flags as $flag) {
    $required_options[rtrim($flag, ':')] = 1;
}

try {
    // Ensure we have all of the necessary options.
    $missing_flags = array_diff_key($required_options, $options);
    if (!empty($missing_flags)) {
        throw new Exception('Missing flags from command line: ' . join(', ', $missing_flags), 1);
    }

    // Parse the config file.
    $config = Yaml::parse(file_get_contents($options['vvv_config']));

    // Ensure we can find the site in the config array.
    if (!isset($config['sites'][$options['site_escaped']])) {
        throw new Exception("Cannot find site in config: {$options['site_escaped']}", 2);
    }
} catch (ParseException $e) {
    echo "Unable to parse config file: {$options['vvv_config']}\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit($e->getCode());
}

// Run composer
exec(get_cmd(array('composer', 'install')));

$current_site = $config['sites'][$options['site_escaped']];
$custom = el('custom', $current_site, array());
$hosts = el('hosts', $current_site, array($options['site_escaped'] . '.local'));
$main_host = $hosts[0];

// Set up variables we'll need for the current site.
$site = array(
    'user' => el('admin_user', $custom, 'admin'),
    'pass' => el('admin_password', $custom, 'password'),
    'email' => el('admin_email', $custom, 'admin@localhost.local'),
    'title' => el('title', $custom, 'My Awesome VVV site'),
    'db_prefix' => el('prefix', $custom, 'wp_'),
    'multisite' => el('multisite', $custom, false),
    'xipio' => el('xipio', $custom, true),
);

// Set up the DB.
echo "Connecting to DB...\n";
$db = new mysqli('localhost', 'root', 'root');
if ($db->connect_errno) {
    echo "DB connect error! ABORTING...\n";
    exit(1);
}

echo "Checking database for site...\n";
$result = $db->query("SHOW DATABASE LIKE '{$options['site_escaped']}'");
if (empty($result)) {
    echo "Creating DB for {$options['site_escaped']}\n";
    $db->query("CREATE DATABASE `{$options['site_escaped']}`;");
    echo "Granting privileges on DB...\n";
    $db->query("GRANT ALL PRIVILEGES ON `{$options['site_escaped']}`.* TO wp@localhost IDENTIFIED BY 'wp'");
    echo "DB setup complete.\n";
}

// Create log files if not present.
if (!file_exists($options['vvv_path_to_site'] . '/log')) {
    mkdir($options['vvv_path_to_site'] . '/log');
}

foreach (array('error.log', 'access.log') as $logfile) {
    $file = "{$options['vvv_path_to_site']}/log/{$logfile}";
    if (!file_exists($file)) {
        touch($file);
    }
}

// Maybe install WordPress
$cmd = get_cmd(array('wp', 'core', 'is-installed'));
exec($cmd, $output, $is_installed);
var_dump($is_installed);
if (0 !== $is_installed) {

    // Create wp-config.php file.
    $extra_php = <<<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'SCRIPT_DEBUG', true );
define( 'JETPACK_DEV_DEBUG', true );
define( 'JETPACK_STAGING_MODE', true );
PHP;

    passthru(
        get_cmd(
            array('wp', 'config', 'create'),
            array(
                'force' => null,
                'dbname' => $options['site_escaped'],
                'dbuser' => 'wp',
                'dbpass' => 'wp',
                'dbhost' => 'localhost',
                'dbprefix' => $site['db_prefix'],
                'locale' => 'en_US',
                'extra-php' => $extra_php,
            )
        )
    );

    // Install WordPress.
    $install_command = $site['multisite'] ? 'multisite-install' : 'install';
    $install_flags = array(
        'url' => $main_host,
        'title' => $site['title'],
        'admin_user' => $site['user'],
        'admin_password' => $site['pass'],
        'admin_email' => $site['email'],
    );

    // Include the flag for subdomains if needed.
    if ($site['multisite'] && 0 === stripos($site['multisite'], 'subdomain')) {
        $install_flags['subdomains'] = null;
    }

    passthru(
        get_cmd(
            array('wp', 'core', $install_command),
            $install_flags
        )
    );
}

// Set up the Nginx config file.
echo "Setting up Nginx config\n";
$nginx_config = __DIR__ . '/vvv-nginx.conf';
$contents = !file_exists($nginx_config) ? file_get_contents(__DIR__ . '/vvv-nginx.template') : file_get_contents($nginx_config);

// Build the hosts directive, maybe including xipio.
$nginx_hosts = join(' ', $hosts);
if ($site['xipio']) {
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

