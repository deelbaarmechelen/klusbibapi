<?php

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
$displayErrorDetails = APP_ENV != 'production' ? true : false;
return [
    'settings' => [
        'displayErrorDetails' => $displayErrorDetails,
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'klusbibapi',
            'path' => __DIR__ . '/../logs/app.log',
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
