<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

require __DIR__ . '/test_env.php';
$capsule = new Capsule;

$capsule->addConnection([
			'driver'    => 'mysql',
			'host' => $hosttst, //getenv('DB_HOST'),
			'database' => $databasetst, //getenv('DB_NAME'),
			'username' => $usertst, //getenv('DB_USER'),
			'password' => $passtst, //getenv('DB_PASS'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
]);
// $capsule->addConnection([
// 		'driver'   => 'sqlite',
// // 		'database' => __DIR__.'/database.sqlite',
// 		'database' => ':memory:?cache=shared',
// 		'prefix'   => '',
// ], 'default');

// Set the event dispatcher used by Eloquent models... (optional)
$capsule->setEventDispatcher(new Dispatcher(new Container));

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
$capsule->bootEloquent();

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => PROJECT_ROOT . '/templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'klusbibapi',
            'path' => __DIR__ . '/logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    
    	// Database settings
    	'db' => [
    		'url' => $urltst,
    		'host' => $hosttst,
    		'user' => $usertst,
    		'pass' => $passtst,
    		'dbname' => $databasetst,
    		'port' => $porttst
    	],
    	'version'        => '0.0.0',
    	'debug'          => true,
    	'mode'           => 'testing',
    ],
];
