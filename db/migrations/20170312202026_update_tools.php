<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class UpdateTools extends AbstractCapsuleMigration
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
			$table->string('brand', 20)->nullable()->default(null);
			$table->string('type', 20)->nullable()->default(null);
			$table->string('serial', 50)->nullable()->default(null);
			$table->string('manufacturing_year', 4)->nullable()->default(null);
			$table->string('manufacturer_url', 255)->nullable()->default(null);
			$table->string('doc_url', 255)->nullable()->default(null);
			$table->integer('replacement_value')->nullable()->default(null);
			$table->dropColumn('link');
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
			$table->dropColumn('brand');
			$table->dropColumn('type');
			$table->dropColumn('serial');
			$table->dropColumn('manufacturing_year');
			$table->dropColumn('manufacturer_url');
			$table->dropColumn('doc_url');
			$table->dropColumn('replacement_value');
			$table->string('link', 255)->nullable()->default(null);
		});
	}
}