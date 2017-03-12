<?php

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../src/env.php';
require __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateTools extends AbstractMigration
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