<?php
// Settings to make all errors more obvious during testing
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('UTC');
use There4\Slim\Test\WebTestCase;
use There4\Slim\Test\WebDbTestCase;
use Tests\DbUnitArrayDataSet;
use Api\Token\Token;
use Tuupola\Base62;
use Api\User\UserManager;
use Api\User\UserController;
use Tests\SlimTestCaseTrait;

define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

$settings = require __DIR__ . '/test_settings.php';
$GLOBALS['DB_DSN']='mysql:host=' . $settings["settings"]["db"]["host"]
    . ';dbname=' . $settings["settings"]["db"]["dbname"];
$GLOBALS['DB_USER']=$settings["settings"]["db"]["user"];
$GLOBALS['DB_PASSWD']=$settings["settings"]["db"]["pass"];
$GLOBALS['DB_DBNAME']=$settings["settings"]["db"]["dbname"];

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once __DIR__ . '/test_env.php';

// Initialize our own copy of the slim application
class LocalWebTestCase extends WebTestCase {
	public function getSlimInstance() {
//		$settings = require __DIR__ . '/test_settings.php';
//		$app = new \Slim\App($settings);
        $container = new \DI\Container();

        \Slim\Factory\AppFactory::setContainer($container);
        $app = \Slim\Factory\AppFactory::create();

		// Include our core application file
		// Set up dependencies
		require __DIR__ . '/dependencies.php';
		
		// Register middleware
		require PROJECT_ROOT . '/app/middleware.php';
		
		// Register routes
		require PROJECT_ROOT . '/app/routes.php';
		
		return $app;
	}
	public function getLogger() {
		return $this->app->logger;
	}
};

class LocalDbWebTestCase extends WebDbTestCase {

    use SlimTestCaseTrait;

	/**
	 * @var type \PDO
	 */
	static private $pdo = null;
	public $settings;
	private $dependencies;
	private $useMiddleware = false;
	
	// Run for each unit test to setup our slim app environment
	public function setup($dependencies = null, WebTestClient $client = NULL, $useMiddleware = false) : void
	{
	    $this->settings = require __DIR__ . '/test_settings.php';
		$this->dependencies = $dependencies;
		$this->useMiddleware = $useMiddleware;

		\Tests\Mock\InventoryMock::clearUsers();
//        $app = $this->getAppInstance();
		$app = $this->getSlimInstance();
		parent::setUp($app);
	
		if (isset($client)) {
			$this->client = $client;
		}
	}
	
	/**
	 * Initializes the in-memory database.
	 */
	public static function initDatabase()
	{
		$queryUsers = "CREATE TABLE `contact` (
				`id` INTEGER PRIMARY KEY,
				`first_name` varchar(50) DEFAULT NULL,
				`last_name` varchar(50) DEFAULT NULL,
				`role` varchar(20) DEFAULT NULL,
				`email` varchar(50) DEFAULT NULL,
				`password` varchar(255) DEFAULT NULL,
				`membership_start_date` date DEFAULT NULL,
				`membership_end_date` date DEFAULT NULL,
				`created_at` timestamp NULL DEFAULT NULL,
				`updated_at` timestamp NULL DEFAULT NULL
				)";

		self::$pdo->query($queryUsers);
		
		$queryTools = "CREATE TABLE `tools` (
  				`tool_id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
 				`name` varchar(50) NOT NULL,
  				`description` varchar(255) DEFAULT NULL,
 				`category` varchar(20) DEFAULT NULL,
  				`img` varchar(255) DEFAULT NULL,
  				`created_at` timestamp NULL DEFAULT NULL,
  				`updated_at` timestamp NULL DEFAULT NULL,
  				`brand` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  				`type` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  				`serial` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  				`manufacturing_year` date DEFAULT NULL,
 				`manufacturer_url` varchar(255) DEFAULT NULL,
 				`doc_url` varchar(255) DEFAULT NULL,
				`replacement_value` int(11) DEFAULT NULL
				)";
		self::$pdo->query($queryTools);
		
		$queryReservations = "CREATE TABLE `reservations` (
				`reservation_id` INTEGER PRIMARY KEY,
				`tool_id` int(11) NOT NULL,
				`user_id` int(11) NOT NULL,
				`title` varchar(50) DEFAULT NULL,
				`startsAt` date DEFAULT NULL,
				`endsAt` date DEFAULT NULL,
				`type` varchar(20) DEFAULT NULL,
				`created_at` timestamp NULL DEFAULT NULL,
				`updated_at` timestamp NULL DEFAULT NULL
				)";
		self::$pdo->query($queryReservations);
	}

	public static function initDatabasePhinx() {
		// see also https://github.com/robmorgan/phinx/issues/364
		
		$phinxApp = new \Phinx\Console\PhinxApplication();
		$phinxTextWrapper = new \Phinx\Wrapper\TextWrapper($phinxApp);
		
		$phinxTextWrapper->setOption('configuration', __DIR__ . '/../phinx.php');
		$phinxTextWrapper->setOption('parser', 'php');
		$phinxTextWrapper->setOption('environment', 'ut');
		
		$log = $phinxTextWrapper->getMigrate();
		echo $log;
	}
	
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
				self::$pdo = new \PDO('mysql:host=' . $this->settings["settings"]["db"]["host"]
						. ';dbname=' . $this->settings["settings"]["db"]["dbname"],
						$this->settings["settings"]["db"]["user"], $this->settings["settings"]["db"]["pass"]); //'sqlite::memory:?cache=shared'
				//self::initDatabase();
			}
			$this->conn = $this->createDefaultDBConnection(self::$pdo, ':memory:');
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
//     	return $this->createFlatXMLDataSet(
//             dirname(__FILE__) . DIRECTORY_SEPARATOR . 'fixture.xml'
//         );
    }

    public function setUser($user) {
    	$container = $this->app->getContainer();
    	$container->set("user", $user);
    }
    public function setToken($sub, $scopes) {
    	if (!isset($sub)) {
    		$sub = 'test';
    	}
    	if (!isset($scopes)) {
 	    	$scopes = [
     				"tools.create",
     				"tools.read",
     				"tools.update",
     				"tools.delete",
     				"tools.list",
     				"tools.all",
     				"reservations.create",
     				"reservations.read",
     				"reservations.update",
     				"reservations.delete",
     				"reservations.list",
     				"reservations.all",
     				"consumers.create",
     				"consumers.read",
     				"consumers.update",
     				"consumers.delete",
     				"consumers.list",
     				"consumers.all",
     				"users.create",
     				"users.read",
     				"users.update",
     				"users.delete",
     				"users.list",
     				"users.all"
     		];
    	}
    	$container = $this->app->getContainer();
    	$decoded = json_decode(json_encode($this->generatePayload($sub, $scopes)));
        $container->get("token")->hydrate($decoded);
    }

    /**
     * @param $sub
     * @param $scopes
     * @return array
     */
    private function generatePayload($sub, $scopes): array
    {
        $now = new \DateTime();
        $future = new \DateTime("now +2 hours");
        $base62 = new Base62;
        $jti = $base62->encode(random_bytes(16));

        $payload = [
            "iat" => $now->getTimeStamp(),        // issued at
            "exp" => $future->getTimeStamp(),    // expiration
            "jti" => $jti,                        // JWT ID
            "sub" => $sub,
            "scope" => $scopes
        ];
        return $payload;
    }

    public function getSlimInstance() {
    	//$app = new \Slim\App($this->settings);
        $container = new \DI\Container();

        \Slim\Factory\AppFactory::setContainer($container);
    	$app = \Slim\Factory\AppFactory::create();
    
    	// Include our core application file
    	// Set up dependencies
    	require __DIR__ . '/dependencies.php';
    	 
    	// Register middleware
        if ($this->useMiddleware) {
            require PROJECT_ROOT . '/app/middleware.php';
        }

    	// Register routes
    	require PROJECT_ROOT . '/app/routes.php';
    	 
    	return $app;
    }


}
/* End of file bootstrap.php */
