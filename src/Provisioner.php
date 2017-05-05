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
     * @param ProcessBuilder $builder
     * @param \mysqli        $db
     * @param array          $site_config
     */
    public function __construct(ProcessBuilder $builder, \mysqli $db, array $site_config)
    {
        $this->builder = $builder;
        $this->db = $db;
        $this->config = $site_config;
        $this->setupSite();
    }

    /**
     * Provision the site.
     */
    public function provision()
    {
        $this->createDB();
        $this->createLogs();
        $this->downloadWordPress();
        $this->createWpConfig();
        $this->installWordPress();
        $this->createNginxConfig();
    }


    protected function setupSite()
    {
        $this->validateOptions();
        $this->validateSiteConfig();
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

    }


    protected function createNginxConfig()
    {

    }


    protected function createWpConfig()
    {

    }


    protected function downloadWordPress()
    {

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


    protected function installWordPress()
    {

    }


    protected function validateOptions()
    {

    }


    protected function validateSiteConfig()
    {

    }
}

