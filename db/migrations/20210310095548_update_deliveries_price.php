<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateDeliveriesPrice extends AbstractCapsuleMigration
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
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
            $table->string('consumers',255)->nullable()->default(null);	           // free text field to request extra consumers or hand tools
            $table->unsignedDecimal('price', 10, 2)->nullable()->default(null); // total cost price for this delivery
            $table->unsignedInteger('payment_id')->nullable()->default(null);
            $table->foreign('payment_id')->references('payment_id')->on('payments');
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
        Capsule::update('UPDATE deliveries SET payment_id = null');
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
            $table->dropColumn('consumers');
            $table->dropColumn('price');
        });
	}
}