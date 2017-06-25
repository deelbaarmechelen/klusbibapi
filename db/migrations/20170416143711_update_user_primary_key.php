<?php

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../src/env.php';
require __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateUserPrimaryKey extends AbstractMigration
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
		Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
			$table->dropColumn('id');
			$table->primary('user_id');
		});
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
		Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->dropPrimary('users_user_id_primary');
		});
		Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('id');
		});
	}
}