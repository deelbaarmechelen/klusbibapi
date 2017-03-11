<?php
namespace Tests;

use Tests\DbUnitArrayDataSet;
use Tests\DbUnit\PHPUnit_Extensions_Database_PostgresTester;
use PDO;

/**
 * Description of Tests_DatabaseTestCase
 * See also https://phpunit.de/manual/current/en/database.html#database.the-four-stages-of-a-database-test
 * 
 * @author bernard
 */
abstract class TestsDatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase 
//PHPUnit_Framework_TestCase
{
    // only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;
 
    private $dbhost = 'localhost';
    private $dbport = '5432';
    private $dbname;
    private $dbuser;
    private $dbpass;
   
    public function __construct() {
        $this->loadTestSettings();
        parent::__construct();
    }
    
    private function loadTestSettings() {
        $dotenv = new \Dotenv\Dotenv(__DIR__ . '/..');
        $dotenv->load();
        $dotenv->required(['DBNAME', 'DBUSER', 'DBPASS']);
        $host = getenv('DBHOST');
        if (isset($host) && !empty($host)) {
            $this->dbhost = $host;
        }
        $port = getenv('DBPORT');
        if (isset($port) && !empty($port)) {
            $this->dbport = $port;
        }
        $this->dbname = getenv('DBNAME');
        $this->dbuser = getenv('DBUSER');
        $this->dbpass = getenv('DBPASS');
    }
    
    protected function setUp()
    {
        $this->loadTestSettings();
        parent::setUp();
    }

    /**
     * Creates a IDatabaseTester for this testCase.
     *
     * @return PHPUnit_Extensions_Database_ITester
     */
    protected function newDatabaseTester()
    {
        return new PHPUnit_Extensions_Database_PostgresTester($this->getConnection());
    }

    /**
     * Returns the database operation executed in test setup.
     *
     * @return PHPUnit_Extensions_Database_Operation_DatabaseOperation
     */
    protected function getSetUpOperation()
    {
        // TRUE param activates cascading
        return PHPUnit_Extensions_Database_PostgresTester::CLEAN_RESTART_INSERT(TRUE);
    }
    
    final public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO('pgsql:host=' . $this->dbhost . ';port='. $this->dbport . ';dbname=' . $this->dbname, $this->dbuser, $this->dbpass); 
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, 'public');
        }

        return $this->conn;
    }
    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    protected function getDataSet()
    {
        return new DbUnitArrayDataSet(array());
    }
    
    protected function getDefaultUserDataSet() {
        return array(
                array('id' => 1, 'letscode' => '100', 'login' => 'admin', 
                    'status' => 1, 'name' => 'Jules the admin',
                    'password' => 'a028dd95866a4e56cca1c08290ead1c75da788e68460faf597bd6d364677d8338e682df2ba3addbe937174df040aa98ab222626f224cbccbed6f33c93422406b',
                    'accountrole' => 'admin', 'hobbies' => 'admin', 'lang' => 'nl', 'pictureid' => '1'), 
                array('id' => 2, 'letscode' => '101', 'login' => 'tester', 
                    'status' => 1, 'name' => 'Jeff the tester',
                    'password' => 'a028dd95866a4e56cca1c08290ead1c75da788e68460faf597bd6d364677d8338e682df2ba3addbe937174df040aa98ab222626f224cbccbed6f33c93422406b',
                    'accountrole' => 'user', 'hobbies' => 'test', 'lang' => 'nl', 'pictureid' => '1')
        );
    }
    
    public function __sleep()
    {
        $this->conn->close();
        $this->conn = NULL;
        return array();
    }
    
    public function __wakeup()
    {
        $this->getConnection();
    }
  
}
