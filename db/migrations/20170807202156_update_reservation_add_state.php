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
class UpdateReservationAddState extends AbstractMigration
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
		Capsule::schema()->table('reservations', function(Illuminate\Database\Schema\Blueprint $table){
			$table->string('state', 20)->default("REQUESTED");
		});
	}
	/**
	 * Down Method.
	 *
	 * Called when invoking rollback
	 */
	public function down()
	{
		Capsule::schema()->table('reservations', function(Illuminate\Database\Schema\Blueprint $table){
			$table->dropColumn('state');
		});
	}
}