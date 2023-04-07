<?php

use \AbstractCapsuleMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class MoveInventory extends AbstractCapsuleMigration
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
        // rename existing inventory_item tables
        $this->query('ALTER TABLE klusbibdb.inventory_item RENAME klusbibdb.kb_inventory_item');
        // clone lendengine inventory_item tables to klusbibdb
        Capsule::update('CREATE TABLE klusbibdb.inventory_item LIKE lendengine.inventory_item');
        Capsule::schema()->table('klusbibdb.inventory_item', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('last_sync_date')->nullable()->default(null);
            $table->string('experience_level', 50)->nullable()->default(null);
            $table->string('safety_risk',50)->nullable()->default(null);
            $table->boolean('deliverable')->nullable()->default(null);
            $table->string('size',50)->nullable()->default(null);
		});
        
        $this->query('DROP TRIGGER IF EXISTS `inventory_item_bi`');
        $this->query('DROP TRIGGER IF EXISTS `inventory_item_bu`');
        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $sql = "
CREATE TRIGGER `inventory_item_bi` BEFORE INSERT ON `inventory_item` FOR EACH ROW 
BEGIN 
IF NEW.created_at IS NULL THEN
  SET NEW.created_at = CURRENT_TIMESTAMP;
END IF;
IF NEW.updated_at IS NULL THEN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END IF;
SET NEW.short_url = substring(NEW.short_url,0,64);
END";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER `inventory_item_bu` BEFORE UPDATE ON `inventory_item` FOR EACH ROW 
BEGIN 
IF NEW.updated_at IS NULL THEN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END IF;
SET NEW.short_url = substring(NEW.short_url,0,64);
END";
        $db->exec($sql);
        // copy data
        Capsule::update("INSERT INTO klusbibdb.inventory_item"
        . " (`id`, `created_by`, `assigned_to`, `current_location_id`, `item_condition`, `created_at`, `updated_at`,"
        . " `name`, `sku`, `description`, `keywords`, `brand`, `care_information`, `component_information`, `loan_fee`,"
        . " `max_loan_days`, `is_active`, `show_on_website`, `serial`, `note`, `price_cost`, `price_sell`, `image_name`,"
        . " `short_url`, `item_sector`, `is_reservable`, `deposit_amount`, `item_type`, `donated_by`, `owned_by`"
        . " `last_sync_date`, `experience_level`, `safety_risk`, `deliverable`, `size`) "
        . " SELECT `id`, `created_by`, `assigned_to`, `current_location_id`, `item_condition`, `created_at`, `updated_at`,"
        . " `name`, `sku`, `description`, `keywords`, `brand`, `care_information`, `component_information`, `loan_fee`,"
        . " `max_loan_days`, `is_active`, `show_on_website`, `serial`, `note`, `price_cost`, `price_sell`, `image_name`,"
        . " `short_url`, `item_sector`, `is_reservable`, `deposit_amount`, `item_type`, `donated_by`, `owned_by`,"
        . " `last_sync_date`, `experience_level`, `safety_risk`, `deliverable`, `size`"
        . " FROM klusbibdb.kb_inventory_item");

        // Update auto_increment
        $builder = $this->getQueryBuilder();
        $statement = $builder->select(['max' => $builder->func()->max('id')])->from('klusbibdb.inventory_item')->execute();
        $maxId=0;
        // FIXME: should find out how to access first row directly
        foreach ($statement as $row) {
            $maxId = $row['max'];
        }
        $maxId++;
        var_dump('ALTER TABLE klusbibdb.inventory_item auto_increment=' . $maxId);
        Capsule::update('ALTER TABLE klusbibdb.inventory_item auto_increment=' . $maxId);        
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        $this->query('DROP TRIGGER IF EXISTS `inventory_item_bi`');
        $this->query('DROP TRIGGER IF EXISTS `inventory_item_bu`');
		Capsule::schema()->drop('klusbibdb.inventory_item');
        // rename existing tables
        $this->query('ALTER TABLE klusbibdb.kb_inventory_item RENAME klusbibdb.inventory_item');
	}
}