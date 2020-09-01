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
class UpdateUsersSyncRelations extends AbstractMigration
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
            $table->string('last_sync_date', 255)->nullable()->default(null);
            $table->unsignedInteger('active_membership')->nullable()->default(null);
            $table->foreign('active_membership')->references('id')->on('membership');;
            $table->string('company', 50)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);
            $table->timestamp('last_login')->nullable()->default(null);
            $table->softDeletes();
        });
        Capsule::update("UPDATE users SET active_membership = user_id WHERE role = 'member' OR role = 'admin'");

    }
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
    public function down()
    {
        Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
            $table->dropColumn('last_sync_date'); // for sync with snipe IT, not foreseen in Lend Engine
            $table->dropForeign(['active_membership']);
            $table->dropColumn('active_membership');
            $table->dropColumn('company');
            $table->dropColumn('comment');
            $table->dropColumn('last_login');
            $table->dropSoftDeletes();
        });
    }
}