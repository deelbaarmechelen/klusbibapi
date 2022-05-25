<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateInventoryItem extends AbstractCapsuleMigration
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
        Capsule::schema()->table('inventory_item', function(Illuminate\Database\Schema\Blueprint $table){
            $table->string('experience_level');
            $table->string('safety_risk');
            $table->string('deliverable');
            $table->string('size');
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
        Capsule::schema()->table('inventory_item', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('experience_level');
            $table->dropColumn('safety_risk');
            $table->dropColumn('deliverable');
            $table->dropColumn('size');
        });
	}
}