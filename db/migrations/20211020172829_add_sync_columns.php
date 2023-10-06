<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddSyncColumns extends AbstractCapsuleMigration
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
        $this->initCapsule();
        Capsule::schema()->table('reservations', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('last_sync_date')->nullable()->default(null); // extra field for sync with Lend Engine
		});
        Capsule::schema()->table('lendings', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('last_sync_date')->nullable()->default(null); // extra field for sync with Lend Engine
		});
        Capsule::schema()->table('payments', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('last_sync_date')->nullable()->default(null); // extra field for sync with Lend Engine
		});
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('last_sync_date')->nullable()->default(null); // extra field for sync with Lend Engine
		});
        Capsule::schema()->table('membership', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('last_sync_date')->nullable()->default(null); // extra field for sync with Lend Engine
		});
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        Capsule::schema()->table('reservations', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropColumn('last_sync_date'); // for sync with Lend Engine
        });
        Capsule::schema()->table('lendings', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropColumn('last_sync_date'); // for sync with Lend Engine
        });
        Capsule::schema()->table('payments', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropColumn('last_sync_date'); // for sync with Lend Engine
        });
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropColumn('last_sync_date'); // for sync with Lend Engine
        });
        Capsule::schema()->table('membership', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropColumn('last_sync_date'); // for sync with Lend Engine
        });
	}
}