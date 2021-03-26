<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateDeliveryItem extends AbstractCapsuleMigration
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
            $table->integer('reservation_id')->unsigned()->nullable()->default(null); // link with reservation is optional
            $table->foreign('reservation_id')
                    ->references('reservation_id')
                    ->on('reservations')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            $table->decimal('fee', 10,2)->nullable()->default(null);
            $table->string('size', 50)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);
            $table->timestamps();
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
            $table->dropForeign('delivery_item_reservation_id_foreign');
            $table->dropColumn('reservation_id');
            $table->dropColumn('fee');
            $table->dropColumn('size');
            $table->dropColumn('comment');

            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');
        });
	}
}