<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class CreateDeliveryItem extends AbstractCapsuleMigration
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
	    Capsule::schema()->create('delivery_item', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('delivery_id')->unsigned();
            $table->integer('inventory_item_id')->unsigned();
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
	    Capsule::schema()->drop('delivery_item');
	}
}