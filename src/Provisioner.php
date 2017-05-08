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
        $this->createNginxConfig();
        $this->installWordPress();

        // Either do custom wp-content, or handle plugins/themes, but not both.
        if ($this->hasWpContent()) {
            $this->setupWpContent();
        } else {
            $this->installPlugins();
            $this->deleteDefaultContent();
        }
    }

    /**
     * Set up the site config.
     */
    protected function setupSite()
    {
        $main_host = "{$this->site_name}.local";
        $hosts = array($main_host);
        $this->site = new DefaultsArray($this->config['custom']);
        $this->site->setDefaults(
            array(
                'admin_user'             => 'admin',
                'admin_password'         => 'password',
                'admin_email'            => 'admin@localhost.local',
                'title'                  => 'My Awesome VVV Site',
                'prefix'                 => 'wp_',
                'multisite'              => false,
                'xipio'                  => true,
                'version'                => 'latest',
                'locale'                 => 'en_US',
                'main_host'              => $main_host,
                'hosts'                  => $hosts,
                'plugins'                => array(),
                'delete_default_plugins' => false,
                'delete_default_themes'  => false,
                'wp-content'             => false,
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
        $result = $this->db->query("SHOW DATABASES LIKE '{$this->site_name}'");
        if (empty($result) || 0 === $result->num_rows) {
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
        $config = "{$provision_dir}/vvv-nginx.conf";
        $template = "{$provision_dir}/vvv-nginx.template";
        $contents = !file_exists($config) ? file_get_contents($template) : file_get_contents($config);

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

        // If the hosts string is found in the file contents, don't try to replace it.
        if (false !== strpos($contents, $nginx_hosts)) {
            return;
        }

        $contents = preg_replace('#(server_name\s*)(?:[^;]*);#', "\$1{$nginx_hosts};", $contents);
        $contents = str_replace('{wp_main_host}', $this->site['main_host'], $contents);
        file_put_contents($config, $contents);
    }

    /**
     * Create the wp-config.php file.
     */
    protected function createWpConfig()
    {
        if (file_exists("{$this->vm_dir}/htdocs/wp-config.php")) {
            return;
        }

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
     * Delete default plugins and themes.
     */
    protected function deleteDefaultContent()
    {
        if ($this->site['delete_default_plugins']) {
            echo "Removing default plugins...\n";
            $default_plugins = array(
                'akismet',
                'hello',
            );
            foreach ($default_plugins as $plugin) {
                $cmd = $this->getCmd(array('wp', 'plugin', 'delete', $plugin));
                $cmd->run();
                echo $cmd->getOutput();
            }
        }

        if ($this->site['delete_default_themes']) {
            echo "Removing default themes...\n";
            $default_themes = array(
                'twelve',
                'thirteen',
                'fourteen',
                'fifteen',
                'sixteen',
                'seventeen',
            );
            foreach ($default_themes as $theme) {
                $cmd = $this->getCmd(array('wp', 'theme', 'delete', "twenty{$theme}"));
                $cmd->run();
                echo $cmd->getOutput();
            }
        }
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
     * Determine whether the site has custom wp-content.
     *
     * @return bool
     */
    protected function hasWpContent()
    {
        return (bool) $this->site['wp-content'];
    }

    /**
     * Install plugins for the site.
     */
    protected function installPlugins()
    {
        $plugins = $this->site['plugins'];
        if (empty($plugins)) {
            return;
        }

        // Change the prefix for the command builder.
        $this->builder->setPrefix(array('wp', 'plugin', 'install'));

        echo "Installing plugins...\n";
        foreach ($plugins as $plugin) {
            // If the plugin is just a string, we can install it with no other options.
            if (is_string($plugin)) {
                $cmd = $this->getCmd(array($plugin));
            } elseif (is_array($plugin)) {
                if (!isset($plugin['plugin'])) {
                    continue;
                }

                // Grab the plugin name.
                $plugin_name = $plugin['plugin'];

                // Determine the plugin flags.
                $plugin_flags = array_intersect_key(
                    $plugin,
                    array(
                        'version'          => true,
                        'force'            => true,
                        'activate'         => true,
                        'activate-network' => true,
                    )
                );

                // Generate the command.
                $cmd = $this->getCmd(array($plugin_name), $plugin_flags);
            } else {
                continue;
            }

            // Now run the command.
            $cmd->run();
            echo $cmd->getOutput();
        }

        // Restore the normal prefix.
        $this->builder->setPrefix(array());
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

    /**
     * Set up a custom wp-content folder.
     */
    protected function setupWpContent()
    {
        if (!$this->hasWpContent()) {
            return;
        }

        $this->removeDefaultWpContent();
        $this->cloneWpContent();
    }

    /**
     * Remove the default wp-content folder.
     *
     * This method will also create a check file in the site root so that the default
     * directory is not removed on every provision.
     */
    protected function removeDefaultWpContent()
    {
        // Only continue if our check file isn't in place.
        $check_file = "{$this->vm_dir}/removed-default-wp-content";
        if (file_exists($check_file)) {
            return;
        }

        echo "Removing default wp-content directory...\n";
        echo $this->getCmd(array('rm', '-rf', "{$this->vm_dir}/htdocs/wp-content"))->mustRun()->getOutput();

        echo "Creating check file [{$check_file}]...\n";
        touch($check_file);
    }

    /**
     * Clone the custom repo into the wp-content directory.
     */
    protected function cloneWpContent()
    {
        // Look for the existence of the .git directory.
        if (file_exists("{$this->vm_dir}/htdocs/wp-content/.git")) {
            return;
        }

        echo "Cloning [{$this->site['wp-content']}] into wp-content...\n";
        $current = getcwd();
        chdir("{$this->vm_dir}/htdocs");
        echo $this->getCmd(
            array('git', 'clone', $this->site['wp-content'], 'wp-content'),
            array(
                'recursive' => null,
            )
        )->mustRun()->getOutput();
        chdir($current);
    }
}
