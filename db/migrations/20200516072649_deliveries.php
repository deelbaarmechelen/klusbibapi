<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class Deliveries extends AbstractCapsuleMigration
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
	        Capsule::schema()->create('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('id');
            $table->integer('user_id')->unsigned;
            $table->integer('reservation_id')->unsigned;
            $table->string('state', 20)->nullable()->default(null);
            $table->string('pick_up_address', 255)->nullable()->default(null);
            $table->string('drop_off_address', 255)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);
            $table->date('pick_up_date')->nullable()->default(null);
            $table->date('drop_off_date')->nullable()->default(null);
				
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
	        Capsule::schema()->drop('deliveries');
	}
}