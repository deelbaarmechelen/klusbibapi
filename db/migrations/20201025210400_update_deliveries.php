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
class UpdateDeliveries extends AbstractMigration
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
        if (Capsule::schema()->hasColumn('deliveries', 'reservation_id')) {
            Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
                $table->dropColumn('reservation_id');
            });
        }
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
            $table->integer('reservation_id')->unsigned()->nullable()->default(null); // link with reservation is optional
            $table->integer('contact_id')->unsigned()->nullable()->default(null);	    // link to person in charge of delivery

		});
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        if (Capsule::schema()->hasColumn('deliveries', 'reservation_id')) {
            Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
                $table->dropColumn('reservation_id');
            });
        }
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('reservation_id')->unsigned();
            $table->dropColumn('contact_id');
        });
	}
}