<?php

namespace JPry\VVVBase;

use Monolog\Logger;

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
     * Logger instance.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * DBProvisioner constructor.
     *
     * @param string  $dbName The database name.
     * @param \mysqli $mysqli The MySQL connection.
     * @param Logger  $logger The Logger instance.
     */
    public function __construct($dbName, \mysqli $mysqli, Logger $logger)
    {
        $this->db     = $mysqli;
        $this->dbName = $dbName;
        $this->logger = $logger;
    }

    /**
     * Run all DB provision processes.
     *
     * @author Jeremy Pry
     */
    public function provision()
    {
        $this->createDB();
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
            $this->logger->info("Creating DB for {$this->dbName}");
            $this->db->query("CREATE DATABASE `{$this->dbName}`;");
            $this->logger->info("Granting privileges on DB...");
            $this->db->query("GRANT ALL PRIVILEGES ON `{$this->dbName}`.* TO wp@localhost IDENTIFIED BY 'wp'");
            $this->logger->info("DB setup complete.");
        }
    }
}
