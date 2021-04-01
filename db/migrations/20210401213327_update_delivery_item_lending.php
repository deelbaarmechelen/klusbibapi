<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateDeliveryItemLending extends AbstractCapsuleMigration
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
        Capsule::schema()->table('delivery_item', function(Illuminate\Database\Schema\Blueprint $table){
            $table->integer('lending_id')->unsigned()->nullable()->default(null); // link with reservation is optional
            $table->foreign('lending_id')
                ->references('lending_id')
                ->on('lendings')
                ->onUpdate('cascade')
                ->onDelete('cascade');
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
        Capsule::schema()->table('delivery_item', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->dropForeign('delivery_item_lending_id_foreign');
            $table->dropColumn('lending_id');
        });
	}
}