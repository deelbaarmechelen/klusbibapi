<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class UpdateDeliveries extends AbstractCapsuleMigration
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
        if (Capsule::schema()->hasColumn('deliveries', 'reservation_id')) {
            Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
                $table->dropColumn('reservation_id');
            });
        }
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table){
            $table->integer('reservation_id')->unsigned()->nullable()->default(null); // link with reservation is optional
            $table->integer('contact_id')->unsigned()->nullable()->default(null);	    // link to person in charge of delivery

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
        if (Capsule::schema()->hasColumn('deliveries', 'reservation_id')) {
            Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
                $table->dropColumn('reservation_id');
            });
        }
        Capsule::schema()->table('deliveries', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('reservation_id')->unsigned();
            $table->dropColumn('contact_id');
        });
	}
}