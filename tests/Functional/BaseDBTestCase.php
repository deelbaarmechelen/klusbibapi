<?php
namespace Tests\Functional;

use PHPUnit_Extensions_Database_TestCase;
use Tests\DbUnitArrayDataSet;

/**
 * This is an example class that shows how you could set up a method that
 * runs the application.
 */
abstract class BaseDBTestCase extends \PHPUnit\DbUnit\TestCase {

	use BaseTestCaseTrait;
	
	/** Database Connection **/
	protected $conn;
	
	/**
	 * @var type \PDO
	 */
	static private $pdo = null;
	
	private $host;
	private $dbname;
	private $dbuser;
	private $dbpassword;
	
	/**
	 * Connects to in-memory database and retuns a connection.
	 *
	 * @return \PHPUnit_Extensions_Database_DB_IDatabaseConnection
	 */
	public function getConnection()
	{
		if ($this->conn === null) {
			if (self::$pdo == null) {
				//new PDO('mysql:host=localhost;dbname=test', $user, $pass);
				self::$pdo = new \PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']); //'sqlite::memory:?cache=shared'
			}
			$this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['DB_DBNAME']); // pdo, schema
		}
	
		return $this->conn;
	}
	
	protected function getPdo() {
		return self::$pdo;
	}
	/**
	 * You must implement this method
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	public function getDataSet()
	{
		return new DbUnitArrayDataSet(array());
	}
	
}
