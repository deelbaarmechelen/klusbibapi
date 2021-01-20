<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class CreateActivityReport extends AbstractCapsuleMigration
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
	        Capsule::schema()->create('activity_report', function(Illuminate\Database\Schema\Blueprint $table){
			// Auto-increment id
			$table->increments('id');
            $table->date('day')->nullable()->default(null);
            $table->datetime('start_time')->nullable()->default(null);
            $table->datetime('end_time')->nullable()->default(null);
			$table->decimal('start_balance')->nullable()->default(null);
            $table->decimal('end_balance')->nullable()->default(null);
            $table->integer('enrolment_count')->nullable()->default(null);
            $table->integer('loan_count')->nullable()->default(null);
            $table->integer('return_count')->nullable()->default(null);
            $table->integer('donation_count')->nullable()->default(null);
			$table->string('volunteer_name', 255)->nullable()->default(null);
            $table->string('comments', 255)->nullable()->default(null);

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
	        Capsule::schema()->drop('activity_report');
	}
}