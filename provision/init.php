<?php
/**
 *
 */

namespace JPry\VVVBase;

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
    $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($options['vvv_config']));

    // Ensure we can find the site in the config array.
    validate_site($config, $options['site_escaped']);

} catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
    echo "Unable to parse config file: {$options['vvv_config']}\n";
    echo $e->getMessage() . PHP_EOL;
    exit(1);
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit($e->getCode());
}

// Set up variables we'll need for the current site.
$site_escaped = $options['site_escaped'];
$site = get_site_config($config, $site_escaped);

// Set up the DB.
create_db($site_escaped);

// Create log files if not present.
create_logs($options['vvv_path_to_site']);

// Download WordPress if needed
if (!file_exists("{$options['vm_dir']}/htdocs")) {
    mkdir("{$options['vm_dir']}/htdocs", 0775);
}
if (!file_exists("{$options['vm_dir']}/htdocs/wp-admin")) {
    downloadWordPress();
}

// Create wp-config file if needed.
if (!file_exists("{$options['vm_dir']}/htdocs/wp-config.php")) {
    create_wp_config($site_escaped, $site['db_prefix']);
}


// Maybe install WordPress
$is_installed = get_cmd(array('wp', 'core', 'is-installed'))->mustRun()->getExitCode();
if (0 !== $is_installed) {
    // Install WordPress.
    $install_command = $site['multisite'] ? 'multisite-install' : 'install';
    $install_flags = array(
        'url'            => $site['main_host'],
        'title'          => $site['title'],
        'admin_user'     => $site['user'],
        'admin_password' => $site['pass'],
        'admin_email'    => $site['email'],
    );

    // Include the flag for subdomains if needed.
    if ($site['multisite'] && 0 === stripos($site['multisite'], 'subdomain')) {
        $install_flags['subdomains'] = null;
    }

    echo get_cmd(array('wp', 'core', $install_command), $install_flags)->mustRun()->getOutput();
}

// Set up the Nginx config file.
create_nginx($site['main_host'], $site['hosts'], $site['xipio']);

