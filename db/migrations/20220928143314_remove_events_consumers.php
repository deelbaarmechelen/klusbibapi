<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class RemoveEventsConsumers extends AbstractCapsuleMigration
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
        Capsule::schema()->drop('consumers');
        Capsule::schema()->drop('events');
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
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
        Capsule::schema()->create('consumers', function(Illuminate\Database\Schema\Blueprint $table){
            // Auto-increment id
            $table->increments('consumer_id');
            $table->string('category', 20)->nullable()->default(null);
            $table->string('brand', 50)->nullable()->default(null);
            $table->string('reference', 20)->nullable()->default(null);
            $table->string('description', 255)->nullable()->default(null);
            $table->decimal('price')->nullable()->default(null);
            $table->string('unit', 20)->nullable()->default(null);
            $table->integer('stock')->nullable()->default(null);
            $table->integer('low_stock')->nullable()->default(null);
            $table->integer('packed_per')->nullable()->default(null);
            $table->string('provider', 50)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);
            $table->boolean('public')->nullable()->default(null);

            // Required for Eloquent's created_at and updated_at columns
            $table->timestamps();
        });

	}
}