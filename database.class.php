<?php

/**
 * Class MySQLDatabase
 * This class is a database class for PHP-MySQL which uses the PDO extension.
 * @author Khavish Anshudass Bhundoo( https://github.com/khavishbhundoo )
 * @Copyright MIT License - Check README.md
 */
class MySQLDatabase
{
    private $host = DB_HOST; // The hostname on which the database server resides.
    private $database = DB_NAME; // The name of the database.
    private $charset = DB_CHARSET; // Specify the character encoding
    private $user = DB_USER; // The database username
    private $pass = DB_PASS; // The password corresponding to the database username
    private $port = DB_PORT; // The port number where the database server is listening.


    private $isConnected = false; // Keep track of database connection
    private $hasExecuted = false; // To prevent double execution of same query
    private $options;
    private $dsn;
    private $stmt;


    public function __construct()
    {

        //Start building dsn string
        $this->dsn = "mysql:host=$this->host;dbname=$this->database;";

        if (empty($this->charset)) {
            $this->charset = 'UTF8';
        }

        // Set options
        $this->options = array(
            PDO::ATTR_EMULATE_PREPARES => false, //prevent false emulation
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        if (!empty($this->port)) {

            $this->dsn .= "port=$this->port;";
        }

        /*
         * Ensure charset if set (default is UTF-8)
         */
        if (version_compare(PHP_VERSION, '5.3.6') <= 0) {
            if (!array_key_exists('PDO::MYSQL_ATTR_INIT_COMMAND', $this->options)) {
                $this->options["PDO::MYSQL_ATTR_INIT_COMMAND"] = "SET NAMES '$this->charset'";
            }

        } else {
            $this->dsn .= "charset=$this->charset;";
        }

        $this->connect();


    }

    /**
     * Connect to database to database if we are not yet connected
     */
    private function connect()
    {
        if ($this->isConnected == false) {
            try {
                $this->_connection = new \PDO($this->dsn, $this->user, $this->pass, $this->options);
                $this->isConnected = true;
            } catch (PDOException $e) {
                /*
                 * Couldn't connection to database , show error message
                 */
                die('Connection failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param $query Set the database query
     */
    public function query($query)
    {

        $this->connect();
        $this->stmt = $this->_connection->prepare($query);
    }

    /**
     * @param $options Allows users to parse their own options
     * $options must be an array
     */
    public function setOptions(array $options) {
        if ($this->isConnected) {
            $this->CloseConnection();
        } else {
            $this->options = $options;
            $this->connect();
        }
    }


    /**
     *
     * Binds a value to a corresponding named or question mark placeholder in the SQL query that was used to prepare the statement.
     * http://php.net/manual/en/pdostatement.bindvalue.php
     * @param $param
     * @param $value
     * @param null $type (Optional) Allow user to hard code datatype
     */
    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }


    /**
     * Execute the SQL query
     */
    public function execute()
    {
        if ($this->hasExecuted == false) {
            $this->stmt->execute();
            if (!$this->_connection->inTransaction()) { # A transaction may execute multiple queries in a row
                $this->hasExecuted = true;
            }

        }
    }


    /**
     * @return An array containing many records
     */
    public function resultSet()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return A single record
     */
    public function single()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return The number of rows affected by the last SQL statement
     * http://php.net/manual/en/pdostatement.rowcount.php
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * @return string
     * Returns the ID of the last inserted row (Id is the primary key of that table)
     * http://php.net/manual/en/pdo.lastinsertid.php
     */
    public function lastInsertId()
    {
        $this->connect();
        return $this->_connection->lastInsertId();
    }

    private function notMyISAM()
    {
        if (strtolower($this->database_engine) == 'myisam') {
            die("You need to change to a storage engine such as InnoDB as MyISAM storage engine does not support transaction.");
        }
        return true;
    }

    /**
     * @return bool
     * Transactions allows you to run multiple changes to a database all in one batch
     * This function will start a transaction
     */
    public function beginTransaction()
    {
        if ($this->notMyISAM()) {
            $this->connect();
            return $this->_connection->beginTransaction();
        }

    }

    /**
     * @return bool
     *  Rollback a specific transaction
     */
    public function rollBack()
    {
        if ($this->notMyISAM()) {
            if ($this->isConnected && $this->_connection->inTransaction()) {
                return $this->_connection->rollBack();
            }
        }
    }

    /**
     * @return bool
     * Make a transaction permanent
     */
    public function commitTransaction()
    {
        if ($this->notMyISAM()) {
            if ($this->isConnected && $this->_connection->inTransaction()) {
                return $this->_connection->commit();
            }
        }
    }

    /**
     * @return  Dumps the the information that was contained in the Prepared Statement
     * http://php.net/manual/en/pdo.begintransaction.php
     */
    public function debugDumpParams()
    {
        return $this->stmt->debugDumpParams();
    }


    /**
     * Close database connection
     */
    public function CloseConnection()
    {
        $this->_connection = null;
        $this->isConnected = false;
    }

}
