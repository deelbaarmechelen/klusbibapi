<?php
/** @var mixed $app */
/** @var mixed $host */
/** @var mixed $database */
/** @var mixed $user */
/** @var mixed $pass */
/** @var mixed $port */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

$capsule = new Capsule;

$capsule->addConnection([
			'driver'    => 'mysql',
			'host' => $host, //getenv('DB_HOST'),
			'database' => $database, //getenv('DB_NAME'),
			'username' => $user, //getenv('DB_USER'),
			'password' => $pass, //getenv('DB_PASS'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
]);

// Set the event dispatcher used by Eloquent models... (optional)
$capsule->setEventDispatcher(new Dispatcher(new Container));

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
$capsule->bootEloquent();
return [
    'settings' => [

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'klusbibapi',
            'path' => __DIR__ . '/../logs/app.log',
            'maxFiles' => 0, // 0 means unlimited
            'level' => \Monolog\Logger::DEBUG,
        ],
    
    	// Database settings
    	'db' => [
    		'host' => $host,
    		'user' => $user,
    		'pass' => $pass,
    		'dbname' => $database,
    		'port' => $port
    	],
    ],
];
