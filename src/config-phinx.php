<?php
$settings = require __DIR__ . '/../src/settings.php';
// require 'config.php';
$db = $settings['settings']['db'];

return [
		'paths' => [
				'migrations' => 'migrations'
		],
		'migration_base_class' => '\Api\Migration\Migration',
		'environments' => [
				'default_migration_table' => 'phinxlog',
				'default_database' => 'dev',
				'dev' => [
						'adapter' => 'mysql',
						'host' => $db['host'],
						'name' => $db['dbname'],
						'user' => $db['user'],
						'pass' => $db['pass'],
						'port' => $db['port']
				]
		]
];