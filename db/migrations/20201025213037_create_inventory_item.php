<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;


class CreateInventoryItem extends AbstractCapsuleMigration
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
	        Capsule::schema()->create('inventory_item', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('id')->unsigned(); // geen auto-increment, want waarde komt vanuit inventory
            $table->string('name', 255);
            $table->string('item_type', 16);
            $table->integer('created_by')->unsigned()->nullable()->default(null);
            $table->integer('assigned_to')->unsigned()->nullable()->default(null);
            $table->integer('current_location_id')->unsigned()->nullable()->default(null);
            $table->integer('item_condition')->unsigned()->nullable()->default(null);
            $table->string('sku', 255)->nullable()->default(null);
            $table->string('description', 1024)->nullable()->default(null);
            $table->string('keywords', 1024)->nullable()->default(null);
            $table->string('brand', 1024)->nullable()->default(null);
            $table->string('care_information', 1024)->nullable()->default(null);
            $table->string('component_information', 1024)->nullable()->default(null);
            $table->decimal('loan_fee', 10, 2)->nullable()->default(null);
            $table->integer('max_loan_days')->unsigned()->nullable()->default(null);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_website')->default(true);
            $table->string('serial', 64)->nullable()->default(null);
            $table->string('note', 128)->nullable()->default(null);
            $table->decimal('price_cost', 10, 2)->nullable()->default(null);
            $table->decimal('price_sell', 10, 2)->nullable()->default(null);
            $table->string('image_name', 255)->nullable()->default(null);
            $table->string('short_url', 64)->nullable()->default(null);
            $table->integer('item_sector')->unsigned()->nullable()->default(null);
            $table->boolean('is_reservable')->default(true);
            $table->decimal('deposit_amount', 10, 2)->nullable()->default(null);
            $table->integer('donated_by')->unsigned()->nullable()->default(null);
            $table->integer('owned_by')->unsigned()->nullable()->default(null);

            $table->timestamp('last_sync_date')->nullable()->default(null); // extra field for sync with inventory

            // Required for Eloquent's created_at and updated_at columns
            $table->timestamps();
        });
        Capsule::schema()->table('inventory_item', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->primary('id');
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
	        Capsule::schema()->drop('inventory_item');
	}
}