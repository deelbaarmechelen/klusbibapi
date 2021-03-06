<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class UsersExtraData extends AbstractCapsuleMigration
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
			$table->renameColumn('user_id', 'id');
			
			$table->date('birth_date')->nullable()->default(null);
			$table->string('address', 20)->nullable()->default(null);
			$table->string('postal_code', 5)->nullable()->default(null);
			$table->string('city', 50)->nullable()->default(null);
			$table->string('phone', 15)->nullable()->default(null);
			$table->string('mobile', 15)->nullable()->default(null);

		});
		Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
			$table->integer('user_id')->nullable()->default(null)->unsigned;
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
			$table->dropColumn('birth_date');
			$table->dropColumn('address');
			$table->dropColumn('postal_code');
			$table->dropColumn('city');
			$table->dropColumn('phone');
			$table->dropColumn('mobile');
			$table->dropColumn('user_id');
		});

		Capsule::schema()->table('users', function(Illuminate\Database\Schema\Blueprint $table){
				$table->renameColumn('id', 'user_id');
		});
	}
}