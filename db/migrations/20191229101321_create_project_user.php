<?php

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class CreateProjectUser extends AbstractMigration
{
    /**
     * Up Method.
     *
     * Called when invoking migrate
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
	public function up()
	{
		Capsule::schema()->create('project_user', function(Illuminate\Database\Schema\Blueprint $table){
			$table->integer('project_id')->unsigned()->nullable()->default(null);
            $table->integer('user_id')->unsigned()->nullable()->default(null);
			$table->string('info', 255)->nullable()->default(null);

			// Required for Eloquent's created_at and updated_at columns
			$table->timestamps();
		});
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
		Capsule::schema()->drop('project_user');
	}
}