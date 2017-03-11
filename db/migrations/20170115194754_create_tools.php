<?php

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

// uncomment to force access to test database
// $host = 'localhost';
// $database = 'klusbibapi_test';
// $user = 'klusbib';
// $pass = 'klusbib';
// $port = 3306;
$settings = require __DIR__ . '/../../src/settings.php';

class CreateTools extends AbstractMigration
{
	public function up()
	{
		Capsule::schema()->create('tools', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('tool_id');
			$table->string('name', 50);
			$table->string('description', 255)->nullable()->default(null);
			$table->string('link', 255)->nullable()->default(null);
			$table->string('category', 20)->nullable()->default(null);
			$table->string('img', 255)->nullable()->default(null);
			
			// Required for Eloquent's created_at and updated_at columns
			$table->timestamps();
		});
	}
	public function down()
	{
		Capsule::schema()->drop('tools');
	}
}
