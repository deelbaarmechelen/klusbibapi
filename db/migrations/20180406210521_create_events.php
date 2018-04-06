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
class CreateEvents extends AbstractMigration
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
        Capsule::schema()->drop('events');
    }
}