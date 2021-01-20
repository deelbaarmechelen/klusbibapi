<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class CreateEvents extends AbstractCapsuleMigration
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
	        Capsule::schema()->create('events', function(Illuminate\Database\Schema\Blueprint $table){
            // Auto-increment id
            $table->increments('event_id');
            $table->string('name', 50); // enrolment, donation, loan, return, reservation, cash withdrawal, cash deposit, free gift, consumers sale
            $table->integer('version');
            $table->decimal('amount')->nullable()->default(null);
            $table->string('currency', 50)->nullable()->default('euro'); // euro, lets, ovam, ...
            $table->json('data')->nullable()->default(null);

            // Required for Eloquent's created_at and updated_at columns
            $table->timestamps();
        });
    }
    public function down()
	{
		$this->initCapsule();
	        Capsule::schema()->drop('events');
    }
}