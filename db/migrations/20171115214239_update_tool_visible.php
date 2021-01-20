<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class UpdateToolVisible extends AbstractCapsuleMigration
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
	        Capsule::schema()->table('tools', function(Illuminate\Database\Schema\Blueprint $table){
			$table->boolean('visible')->default(true);
			$table->string('state', 20)->default("NEW");
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
	        Capsule::schema()->table('tools', function(Illuminate\Database\Schema\Blueprint $table){
			$table->dropColumn('visible');
			$table->dropColumn('state');
		});
	}
}