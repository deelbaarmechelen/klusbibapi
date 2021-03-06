<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class CreatePayments extends AbstractCapsuleMigration
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
	        Capsule::schema()->create('payments', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
            $table->increments('payment_id');
            $table->integer('user_id')->unsigned;
            $table->string('mode', 20); // CASH, TRANSFER, MOLLIE
            $table->timestamp('payment_date')->nullable()->default(null);
            $table->string('state',20)->default('NEW'); // NEW, OPEN, SUCCESS, FAILED
            $table->string('order_id',50)->nullable()->default(null);
            $table->decimal('amount')->nullable()->default(null);
            $table->string('currency', 20)->nullable()->default(null); // EUR, blusser, koekoek
            $table->string('comment', 50)->nullable()->default(null);

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
		$this->initCapsule();
	        Capsule::schema()->drop('payments');
	}
}