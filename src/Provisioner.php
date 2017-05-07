<?php
/**
 *
 */

namespace JPry\VVVBase;


use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Process;

class Provisioner
{
    protected $builder;
    protected $db;
    protected $config;
    protected $site_name;
    protected $site;
    protected $vm_dir;

    /**
     * Provisioner constructor.
     *
     * @param ProcessBuilder $builder     ProcessBuider for generating commands.
     * @param \mysqli        $db          Database connection.
     * @param string         $vm_dir      Root directory for the site.
     * @param string         $site_name   The name of the site.
     * @param array          $site_config The config for the site.
     */
    public function __construct(ProcessBuilder $builder, \mysqli $db, $vm_dir, $site_name, array $site_config)
    {
        $this->builder = $builder;
        $this->db = $db;
        $this->vm_dir = $vm_dir;
        $this->site_name = $site_name;
        $this->config = (array) $site_config;

        // Ensure that there is a custom array in the site config.
        if (!array_key_exists('custom', $this->config)) {
            $this->config['custom'] = array();
        }

        $this->setupSite();
    }

    /**
     * Provision the site.
     */
    public function provision()
    {
        $this->createDB();
        $this->createLogs();
        $this->createBaseDir();
        $this->downloadWordPress();
        $this->createWpConfig();
        $this->installWordPress();
        $this->createNginxConfig();
    }

    /**
     * Set up the site config.
     */
    protected function setupSite()
    {
        $main_host = "{$this->site_name}.local";
        $hosts = array($main_host);
        $this->site = new ArrayHelper($this->config['custom']);
        $this->site->setDefaults(
            array(
                'admin_user'     => 'admin',
                'admin_password' => 'password',
                'admin_email'    => 'admin@localhost.local',
                'title'          => 'My Awesome VVV Site',
                'prefix'         => 'wp_',
                'multisite'      => false,
                'xipio'          => true,
                'version'        => 'latest',
                'locale'         => 'en_US',
                'main_host'      => $main_host,
                'hosts'          => $hosts,
            )
        );
    }

    /**
     * Create the base htdocs directory if needed.
     */
    protected function createBaseDir()
    {
        if (!file_exists("{$this->vm_dir}/htdocs")) {
            mkdir("{$this->vm_dir}/htdocs", 0775);
        }
    }

    /**
     * Create the database.
     */
    protected function createDB()
    {
        echo "Checking database for site...\n";
        $result = $this->db->query("SHOW DATABASE LIKE '{$this->site_name}'");
        if (empty($result)) {
            echo "Creating DB for {$this->site_name}\n";
            $this->db->query("CREATE DATABASE `{$this->site_name}`;");
            echo "Granting privileges on DB...\n";
            $this->db->query("GRANT ALL PRIVILEGES ON `{$this->site_name}`.* TO wp@localhost IDENTIFIED BY 'wp'");
            echo "DB setup complete.\n";
        }
    }

    /**
     * Create the log files and directory.
     */
    protected function createLogs()
    {
        if (!file_exists("{$this->vm_dir}/log")) {
            echo "Creating {$this->vm_dir}/log directory...\n";
            mkdir("{$this->vm_dir}/log", 0775);
        }

        foreach (array('error.log', 'access.log') as $logfile) {
            $file = "{$this->vm_dir}/log/{$logfile}";
            if (!file_exists($file)) {
                file_put_contents($file, '');
            }
        }
    }

    /**
     * Create or update the Nginx config file.
     */
    protected function createNginxConfig()
    {
        echo "Setting up Nginx config\n";
        $provision_dir = dirname(__DIR__) . '/provision';
        $nginx_config = "{$provision_dir}/vvv-nginx.conf";
        $contents = !file_exists($nginx_config) ? file_get_contents(
            "{$provision_dir}/vvv-nginx.template"
        ) : file_get_contents($nginx_config);

        // Build the hosts directive, maybe including xipio.
        $nginx_hosts = join(' ', $this->site['hosts']);
        if ($this->site['xipio']) {
            $xipio_base = preg_replace('#(.*)\.[a-zA-Z0-9_]+$#', '$1', $this->site['main_host']);
            $nginx_xipio = str_replace(
                    '.',
                    '\\.',
                    $xipio_base
                ) . '\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.xip\\\\.io$';
            $nginx_hosts .= " {$nginx_xipio}";
        }

        $contents = preg_replace('#(server_name\s*)(?:[^;]*);#', "\$1{$nginx_hosts};", $contents);
        $contents = str_replace('{wp_main_host}', $this->site['main_host'], $contents);
        file_put_contents($nginx_config, $contents);
    }

    /**
     * Create the wp-config.php file.
     */
    protected function createWpConfig()
    {
        $extra_php = <<<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'SCRIPT_DEBUG', true );
define( 'JETPACK_DEV_DEBUG', true );
define( 'JETPACK_STAGING_MODE', true );
PHP;

        echo $this->getCmd(
            array('wp', 'config', 'create'),
            array(
                'force'     => null,
                'dbname'    => $this->site_name,
                'dbuser'    => 'wp',
                'dbpass'    => 'wp',
                'dbhost'    => 'localhost',
                'dbprefix'  => $this->site['prefix'],
                'locale'    => 'en_US',
                'extra-php' => $extra_php,
            )
        )->mustRun()->getOutput();
    }

    /**
     * Download WordPress files.
     */
    protected function downloadWordPress()
    {
        if (file_exists("{$this->vm_dir}/htdocs/wp-admin")) {
            return;
        }

        echo $this->getCmd(
            array('wp', 'core', 'download'),
            array(
                'locale'  => $this->site['locale'],
                'version' => $this->site['version'],
            )
        )->mustRun()->getOutput();
    }

    /**
     * Get a Process object to run a command.
     *
     * @param array $positional Positional arguments.
     * @param array $flags      Key/value flags for the command.
     *
     * @return Process
     */
    protected function getCmd(array $positional, $flags = array())
    {
        $this->builder->setArguments($positional);
        foreach ($flags as $flag => $value) {
            // Build flag, including value if truthy
            $cmd = "--{$flag}" . ($value ? "={$value}" : '');
            $this->builder->add($cmd);
        }

        return $this->builder->getProcess();
    }

    /**
     * Install WordPress in the database.
     */
    protected function installWordPress()
    {
        $is_installed = $this->getCmd(array('wp', 'core', 'is-installed'))->run();
        if (0 !== $is_installed) {
            // Install WordPress.
            $install_command = $this->site['multisite'] ? 'multisite-install' : 'install';
            $install_flags = array(
                'url'            => $this->site['main_host'],
                'title'          => $this->site['title'],
                'admin_user'     => $this->site['admin_user'],
                'admin_password' => $this->site['admin_password'],
                'admin_email'    => $this->site['admin_email'],
            );

            // Include the flag for subdomains if needed.
            if ($this->site['multisite'] && 0 === stripos($this->site['multisite'], 'subdomain')) {
                $install_flags['subdomains'] = null;
            }

            echo $this->getCmd(array('wp', 'core', $install_command), $install_flags)->mustRun()->getOutput();
        }
    }
}

