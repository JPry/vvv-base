<?php

namespace JPry\VVVBase;

use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Database Provisioner.
 *
 * @package JPry\VVVBase
 */
class DBProvisioner implements ProvisionerInterface
{
    /**
     * The database name to provision.
     *
     * @var string
     */
    protected $dbName;

    /**
     * The database connection.
     *
     * @var \mysqli
     */
    protected $db;

    /**
     * Filesystem object.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * DBProvisioner constructor.
     *
     * @param string     $dbName     The database name to provision.
     * @param \mysqli    $mysqli     The MySQL connection.
     * @param Logger     $logger     The Logger instance.
     * @param Filesystem $filesystem Filesystem object.
     */
    public function __construct($dbName, \mysqli $mysqli, Logger $logger, Filesystem $filesystem)
    {
        $this->db         = $mysqli;
        $this->dbName     = $this->db->real_escape_string($dbName);
        $this->filesystem = $filesystem;
        $this->logger     = $logger;
    }

    /**
     * Run all DB provision processes.
     *
     * @author Jeremy Pry
     */
    public function provision()
    {
        $this->createDB();
        $this->initCustom();
    }

    /**
     * Create the database.
     *
     * @todo: Don't hard-code the DB user and host.
     */
    protected function createDB()
    {
        $this->logger->info('Checking database for site...');
        $result = $this->db->query("SHOW DATABASES LIKE '{$this->dbName}'");
        if (empty($result) || 0 === $result->num_rows) {
            $this->logger->info("Setting up DB for {$this->dbName}");
            $this->db->multi_query($this->dbCommands());
            $this->logger->info("DB setup complete.");
        }
    }

    /**
     * Update (and maybe create) the init-custom.sql file.
     *
     * @author Jeremy Pry
     */
    protected function initCustom()
    {
        $customFile = '/srv/database/init-custom.sql';

        // Make sure it exists
        if (!$this->filesystem->exists($customFile)) {
            $this->logger->info("Creating the {$customFile} file.");
            $this->filesystem->touch($customFile);
        }

        // Read contents
        $contents = file_get_contents($customFile);

        // Determine if the DB is already present in contents
        $exists = strpos($contents, $this->dbName);

        // Add line if needed, and update file
        if (false === $exists) {
            $this->logger->info("Appending DB commands to {$customFile} file.");
            $this->filesystem->appendToFile($customFile, "\n\n" . $this->dbCommands());
        }
    }

    /**
     * Get the DB Commands to run.
     *
     * @author Jeremy Pry
     * @return string
     */
    protected function dbCommands()
    {
        return <<< SQL
CREATE DATABASE `{$this->dbName}`;
GRANT ALL PRIVILEGES ON `{$this->dbName}`.* TO wp@localhost IDENTIFIED BY 'wp';
SQL;
    }
}
