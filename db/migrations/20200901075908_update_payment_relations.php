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
class UpdatePaymentRelations extends AbstractMigration
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
        Capsule::schema()->table('payments', function(Illuminate\Database\Schema\Blueprint $table){
            $table->unsignedInteger('membership_id')->nullable()->default(null);
            $table->foreign('membership_id')->references('id')->on('membership');
            $table->unsignedInteger('loan_id')->nullable()->default(null);
            $table->unsignedInteger('user_id')->nullable()->default(null)->change();
        });
        Capsule::update('UPDATE payments SET membership_id = user_id where user_id in (select id from membership)');
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        Capsule::update('UPDATE payments SET membership_id = null');
        Capsule::schema()->table('payments', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropForeign(['membership_id']);
            $table->dropColumn('membership_id');
            $table->dropColumn('loan_id');
        });
	}
}