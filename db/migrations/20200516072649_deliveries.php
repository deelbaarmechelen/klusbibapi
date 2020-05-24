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
class Deliveries extends AbstractMigration
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
		Capsule::schema()->create('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('delivery_id');
            $table->integer('user_id')->unsigned;
            $table->integer('reservation_id')->unsigned;
            $table->integer('state_id')->unsigned;
            $table->string('pick_up_address', 255)->nullable()->default(null);
            $table->string('drop_off_address', 255)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);
            $table->date('date', 255)->nullable()->default(null);
				
			// Required for Eloquent's created_at and updated_at columns
			$table->timestamps();
        });
        
        Capsule::schema()->create('tool_deliveries', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('tool_delivery_id');
            $table->integer('tool_id')->unsigned;
            $table->integer('delivery_id')->unsigned;
				
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
        Capsule::schema()->drop('deliveries');
        Capsule::schema()->drop('tool_deliveries');
	}
}