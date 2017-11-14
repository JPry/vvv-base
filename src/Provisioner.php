<?php
/**
 *
 */

namespace JPry\VVVBase;

use JPry\DefaultsArray;
use Monolog\Logger;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Provisioner implements ProvisionerInterface
{
    /** @var  string */
    protected $base_dir;

    /** @var ProcessBuilder */
    protected $builder;

    /** @var array */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var  array */
    protected $overrides;

    /** @var  DefaultsArray */
    protected $site;

    /** @var string */
    protected $site_name;

    /** @var string */
    protected $vm_dir;

    /** @var  string */
    protected $wp_content;

    /**
     * Provisioner constructor.
     *
     * @param ProcessBuilder $builder     ProcessBuilder for generating commands.
     * @param string         $vm_dir      Root directory for the site.
     * @param string         $site_name   The name of the site.
     * @param array          $site_config The config for the site.
     * @param Logger         $logger      A logger instance.
     * @param array          $overrides   vvvBase override settings.
     */
    public function __construct(
        ProcessBuilder $builder,
        $vm_dir,
        $site_name,
        array $site_config,
        Logger $logger,
        array $overrides
    ) {
        $this->builder   = $builder;
        $this->vm_dir    = $vm_dir;
        $this->site_name = $site_name;
        $this->config    = $site_config;
        $this->logger    = $logger;
        $this->overrides = $overrides;

        $this->setupSite();
    }

    /**
     * Provision the site.
     */
    public function provision()
    {
        $this->createLogs();
        $this->createBaseDir();
        $this->createNginxConfig();

        if (!$this->site['wp']) {
            $this->logger->info("Skipping WordPress setup.\n");

            return;
        }

        $this->downloadWordPress();
        $this->createWpConfig();
        $this->installWordPress();

        if ($this->hasHtdocs()) {
            return;
        }

        $this->provisionContent();
    }

    /**
     * Provision the content within the site.
     *
     * This will either clone the wp-content directory, or else use custom options to install/delete
     * plugins and themes.
     */
    public function provisionContent()
    {
        $this->cloneWpContent();

        // Only do plugins, themes, and default deletion if there's not custom content.
        if (!$this->hasWpContent()) {
            $this->installPlugins();
            $this->installThemes();
            $this->deleteDefaultContent();
        }
    }

    /**
     * Set up the site config.
     */
    protected function setupSite()
    {
        if (!empty($this->config['hosts'])) {
            $hosts     = (array) $this->config['hosts'];
            $main_host = $hosts[0];
        } else {
            $main_host = "{$this->site_name}.local";
            $hosts     = array($main_host);
        }

        $this->site = new DefaultsArray($this->config['custom']);
        $this->site->setDefaults([
            'main_host' => $main_host,
            'hosts'     => $hosts,
        ]);

        // Handle old or alternate option names
        if (isset($this->site['wp-content'])) {
            $this->site->setDefault('wp_content', $this->site['wp-content']);
        }
        if (isset($this->site['prefix'])) {
            $this->site->setDefault('db_prefix', $this->site['prefix']);
        }
        if (isset($this->site['dbprefix'])) {
            $this->site->setDefault('db_prefix', $this->site['dbprefix']);
        }

        $this->base_dir   = "{$this->vm_dir}/htdocs";
        $this->wp_content = "{$this->base_dir}/wp-content";
    }

    /**
     * Clone the custom repo into the htdocs/ directory.
     */
    protected function cloneHtdocs()
    {
        // Look for the existence of the .git directory.
        if (file_exists("{$this->base_dir}/.git") && is_dir("{$this->base_dir}/.git")) {
            return;
        }

        // If we already have the htdocs dir, remove it.
        $this->removeDefaultHtdocs();

        $this->logger->info("Cloning [{$this->site['htdocs']}] into {$this->base_dir}...");
        echo $this->getCmd(
            ['git', 'clone', $this->site['htdocs'], $this->base_dir],
            [
                'recursive' => null,
            ]
        )->mustRun()->getOutput();
    }

    /**
     * Clone the custom repo into the wp-content directory.
     */
    protected function cloneWpContent()
    {
        // Look for the existence of the .git directory.
        if (!$this->hasWpContent() || file_exists("{$this->wp_content}/.git")) {
            return;
        }

        // Maybe remove the default wp-content directory.
        $this->removeDefaultWpContent();

        $this->logger->info("Cloning [{$this->site['wp_content']}] into wp-content...");
        echo $this->getCmd(
            ['git', 'clone', $this->site['wp_content'], $this->wp_content],
            [
                'recursive' => null,
            ]
        )->mustRun()->getOutput();
    }

    /**
     * Create the base htdocs directory if needed.
     */
    protected function createBaseDir()
    {
        if ($this->hasHtdocs()) {
            $this->cloneHtdocs();
        } elseif (!file_exists($this->base_dir)) {
            mkdir($this->base_dir, 0775, true);
        }
    }

    /**
     * Create the log files and directory.
     */
    protected function createLogs()
    {
        if (!file_exists("{$this->vm_dir}/log")) {
            $this->logger->info("Creating {$this->vm_dir}/log directory...");
            mkdir("{$this->vm_dir}/log", 0775);
        }

        foreach (['error.log', 'access.log'] as $logfile) {
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
        $this->logger->info('Setting up Nginx config');
        $provision_dir = dirname(__DIR__) . '/provision';
        $config        = "{$provision_dir}/vvv-nginx.conf";
        $template      = "{$provision_dir}/vvv-nginx.template";
        $contents      = !file_exists($config) ? file_get_contents($template) : file_get_contents($config);

        // Build the hosts directive, maybe including xipio.
        $nginx_hosts = join(' ', $this->site['hosts']);
        if ($this->site['xipio']) {
            $xipio_host  = '~' . str_replace('.', '\\.', $this->getXipioBase());
            $xipio_host  .= '\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.xip\\\\.io$';
            $nginx_hosts .= " {$xipio_host}";
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
        if (file_exists("{$this->base_dir}/wp-config.php")) {
            $this->logger->info('wp-config.php file found');
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
            ['wp', 'config', 'create'],
            [
                'dbname'    => $this->site_name,
                'dbuser'    => 'wp',
                'dbpass'    => 'wp',
                'dbhost'    => 'localhost',
                'dbprefix'  => $this->site['db_prefix'],
                'locale'    => $this->site['locale'],
                'extra-php' => $extra_php,
            ]
        )->mustRun()->getOutput();
    }

    /**
     * Delete default plugins and themes.
     */
    protected function deleteDefaultContent()
    {
        if ($this->site['delete_default_plugins']) {
            $this->logger->info('Removing default plugins...');
            $default_plugins = [
                'akismet',
                'hello',
            ];
            foreach ($default_plugins as $plugin) {
                $cmd = $this->getCmd(['wp', 'plugin', 'delete', $plugin]);
                $cmd->run();
                $this->logger->info($cmd->getOutput());
            }
        }

        if ($this->site['delete_default_themes']) {
            $this->logger->info('Removing default themes...');
            $default_themes = [
                'twelve',
                'thirteen',
                'fourteen',
                'fifteen',
                'sixteen',
                'seventeen',
            ];
            foreach ($default_themes as $theme) {
                $cmd = $this->getCmd(['wp', 'theme', 'delete', "twenty{$theme}"]);
                $cmd->run();
                $this->logger->info($cmd->getOutput());
            }
        }
    }

    /**
     * Download WordPress files.
     */
    protected function downloadWordPress()
    {
        // todo: handle htdocs repo

        if (file_exists("{$this->base_dir}/wp-admin") || !$this->site['download_wp']) {
            return;
        }

        echo $this->getCmd(
            ['wp', 'core', 'download'],
            [
                'locale'  => $this->site['locale'],
                'version' => $this->site['version'],
            ]
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
    protected function getCmd(array $positional, $flags = [])
    {
        $this->builder->setArguments($positional);
        foreach ($flags as $flag => $value) {
            // False can be used to explicitly bypass a value
            if (false === $value) {
                continue;
            }

            // Build flag, including value if truthy
            $cmd = "--{$flag}" . ($value ? "={$value}" : '');
            $this->builder->add($cmd);
        }

        return $this->builder->getProcess();
    }

    /**
     * Get the base of the domain to use with Xip.io.
     *
     * The is formed by removing the top level domain from the host name.
     *
     * Examples:
     * - example.com will become "example"
     * - foo.bar.local will become "foo.bar"
     *
     * @return mixed
     */
    protected function getXipioBase()
    {
        return preg_replace('#(.*)\.[a-zA-Z0-9_]+$#', '$1', $this->site['main_host']);
    }

    /**
     * Determine whether the site has custom htdocs repository.
     *
     * @return bool
     */
    protected function hasHtdocs()
    {
        return (bool) $this->site['htdocs'];
    }

    /**
     * Determine whether the site has custom wp-content repository.
     *
     * @return bool
     */
    protected function hasWpContent()
    {
        return (bool) $this->site['wp_content'];
    }

    /**
     * Helper to install plugins or themes.
     *
     * @param string $type  The type of item to install.
     * @param array  $items Array of items to install.
     * @param array  $skip  Array of items to skip installing.
     *
     * @throws \Exception When an invalid type is provided.
     */
    protected function installHelper($type, $items, $skip = array())
    {
        $types = [
            'plugin' => true,
            'theme'  => true,
        ];
        if (!isset($types[$type])) {
            throw new \Exception("Invalid installer type: {$type}");
        }

        $flipped = !empty($skip) ? array_flip($skip) : array();

        // Change the prefix for the command builder.
        $this->builder->setPrefix(['wp', $type, 'install']);

        $this->logger->info("Installing {$type}s...");
        foreach ($items as $item) {

            // Grab the item name.
            $name = $item[$type];

            // Maybe skip the item.
            if (isset($flipped[$name])) {
                $this->logger->info("Found {$name} in skip list, skipping...");
                continue;
            }

            // Determine the item flags.
            $valid_flags = [
                'version'  => true,
                'force'    => true,
                'activate' => true,
            ];

            if ('plugin' === $type) {
                $valid_flags['activate-network'] = true;
            }

            $item_flags = array_intersect_key($item, $valid_flags);

            // Generate the command.
            $cmd = $this->getCmd([$name], $item_flags);

            // Now run the command.
            $cmd->run();
            $this->logger->info($cmd->getOutput());
        }

        // Restore the normal prefix.
        $this->builder->setPrefix([]);
    }

    /**
     * Install plugins for the site.
     */
    protected function installPlugins()
    {
        $plugins = array_merge($this->overrides['plugins'], $this->site['plugins']);
        if (empty($plugins)) {
            return;
        }

        $skip = $this->site['skip_plugins'];
        $this->installHelper('plugin', $plugins, $skip);
    }

    /**
     * Install themes for the site.
     */
    protected function installThemes()
    {
        $themes = array_merge($this->overrides['themes'], $this->site['themes']);
        if (empty($themes)) {
            return;
        }

        $this->installHelper('theme', $themes);
    }

    /**
     * Install WordPress in the database.
     */
    protected function installWordPress()
    {
        $is_installed = $this->getCmd(['wp', 'core', 'is-installed'])->run();
        if (0 !== $is_installed) {
            $this->logger->info('Installing WordPress...');
            $install_command = $this->site['multisite'] ? 'multisite-install' : 'install';
            $install_flags   = [
                'url'            => $this->site['main_host'],
                'title'          => $this->site['title'],
                'admin_user'     => $this->site['admin_user'],
                'admin_password' => $this->site['admin_password'],
                'admin_email'    => $this->site['admin_email'],
                'skip-plugins'   => null,
                'skip-themes'    => null,
            ];

            // Include the flag for subdomains if needed.
            if ($this->site['multisite'] && 0 === stripos($this->site['multisite'], 'subdomain')) {
                $install_flags['subdomains'] = null;
            }

            $this->logger->info(
                $this->getCmd(['wp', 'core', $install_command], $install_flags)->mustRun()->getOutput()
            );
        }
    }

    /**
     * Remove the default htdocs directory.
     */
    protected function removeDefaultHtdocs()
    {
        if (file_exists($this->base_dir)) {
            $this->logger->info('Removing default htdocs directory...');
            $this->logger->info($this->getCmd(['rm', '-rf', $this->base_dir])->mustRun()->getOutput());
        }
    }

    /**
     * Remove the default wp-content folder.
     */
    protected function removeDefaultWpContent()
    {
        if (file_exists($this->wp_content)) {
            $this->logger->info('Removing default wp-content directory...');
            $this->logger->info($this->getCmd(['rm', '-rf', $this->wp_content])->mustRun()->getOutput());
        }
    }
}

