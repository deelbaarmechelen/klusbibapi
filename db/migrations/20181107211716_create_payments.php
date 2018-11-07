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
class CreatePayments extends AbstractMigration
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
		Capsule::schema()->create('payments', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
            $table->increments('payment_id');
            $table->integer('user_id')->unsigned;
            $table->string('mode', 20); // CASH, TRANSFER, MOLLIE
            $table->timestamp('payment_date')->nullable()->default(null);
            $table->decimal('amount')->nullable()->default(null);
            $table->string('currency', 20)->nullable()->default(null); // euro, blusser, koekoek

			// Required for Eloquent's created_at and updated_at columns
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
		Capsule::schema()->drop('payments');
	}
}