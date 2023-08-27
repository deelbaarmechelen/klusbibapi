<?php

use \AbstractCapsuleMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class CreateSyncTable extends AbstractCapsuleMigration
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
		Capsule::schema()->create('kb_sync', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->integer('last_inventory_action_id')->unsigned()->nullable()->default(null);
		});
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
		Capsule::schema()->drop('kb_sync');
	}
}