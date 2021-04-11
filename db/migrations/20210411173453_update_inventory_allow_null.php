<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateInventoryAllowNull extends AbstractCapsuleMigration
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
            $table->string('experience_level', 50)->nullable()->default(null)->change();
            $table->string('safety_risk', 50)->nullable()->default(null)->change();
            $table->boolean('deliverable')->default(false)->change();
            $table->string('size', 50)->nullable()->default(null)->change();
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
	}
}