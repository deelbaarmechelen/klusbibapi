<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class UpdateUsersSizes extends AbstractCapsuleMigration
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
	        Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
            $table->string('firstname', 255)->change();
            $table->string('lastname', 255)->change();
            $table->string('address', 255)->change();
            $table->string('email', 255)->change();
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
	        Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
            $table->string('firstname', 50)->change();
            $table->string('lastname', 50)->change();
            $table->string('address', 50)->change();
            $table->string('email', 50)->change();
        });
    }
}