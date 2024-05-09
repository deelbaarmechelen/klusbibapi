<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddPaymentServiceProvider extends AbstractCapsuleMigration
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
        /**
         * TODO:
         * membership renewal should start on membership end date
         * * even if membership type is different
         * * even if previous membership already expired
         * * renewal may have a different amount
         * * block membership renewal in LE until this is fixed
         * * renewal as a conditional membership type: only available if member previously already enrolled to a regular membership (may not be current active membership!)
         */
		Capsule::schema()->create('kb_payment_services', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('id');
			$table->string('name', 255);
			$table->string('api_key', 255)->nullable()->default(null);
			$table->string('webhook_url', 255)->nullable()->default(null);
		});
		Capsule::schema()->table('payment_method', function(Illuminate\Database\Schema\Blueprint $table){
			$table->unsignedInteger('kb_psp_id')->nullable()->default(null);
			$table->decimal('kb_minimum_payment',10,2)->nullable()->default(null);
			$table->decimal('kb_payment_fee',10,2)->nullable()->default(null);
		});
        Capsule::update('INSERT INTO setting (setup_key , setup_value) VALUES ("self_serve_payment_method", "2")');
        Capsule::update('INSERT INTO kb_payment_services (name , api_key) VALUES ("mollie", "TODO:UpdateApiKey")');
        Capsule::update('UPDATE payment_method SET kb_psp_id = 1 WHERE UPPER(name) LIKE \'%MOLLIE%\'');
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        Capsule::update('DELETE FROM setting WHERE setup_key = \'self_serve_payment_method\'');
        Capsule::update('UPDATE payment_method SET kb_psp_id = null');
        Capsule::schema()->table('payment_method', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('kb_psp_id');
            $table->dropColumn('kb_minimum_payment');
            $table->dropColumn('kb_payment_fee');
        });
		Capsule::schema()->drop('kb_payment_services');
	}
}