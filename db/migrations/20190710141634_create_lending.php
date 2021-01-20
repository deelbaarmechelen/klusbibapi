<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class CreateLending extends AbstractCapsuleMigration
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
	        Capsule::schema()->create('lendings', function(Illuminate\Database\Schema\Blueprint $table){
            // Auto-increment id
            $table->increments('lending_id');
            $table->timestamp('start_date')->nullable()->default(null);
            $table->timestamp('due_date')->nullable()->default(null);
            $table->timestamp('returned_date')->nullable()->default(null);
            $table->integer('tool_id')->unsigned;
            $table->integer('user_id')->unsigned;
            $table->string('created_by', 50)->nullable()->default(null);
            $table->string('comments', 255)->nullable()->default(null);
            $table->boolean('active')->nullable()->default(null);

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
	        Capsule::schema()->drop('lendings');
	}
}