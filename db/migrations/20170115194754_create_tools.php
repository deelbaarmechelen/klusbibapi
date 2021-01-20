<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateTools extends AbstractCapsuleMigration
{

	public function up()
	{
        $this->initCapsule();
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
		$this->initCapsule();
	        Capsule::schema()->drop('tools');
	}
}
