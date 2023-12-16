<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class CreateSyncAssets extends AbstractCapsuleMigration
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
        // create a table containing all the data to be synced from inventory.assets to klubsibapi/lendengine
        Capsule::schema()->create('kb_sync_assets', function(Illuminate\Database\Schema\Blueprint $table){
            $table->integer('id')->unsigned()->default(1);
			$table->string('name', 191)->nullable()->default(null);
			$table->string('asset_tag', 191)->nullable()->default(null);
            $table->integer('model_id')->nullable()->default(null);
			$table->text('image')->nullable()->default(null);
            $table->integer('status_id')->nullable()->default(null);
            $table->integer('assigned_to')->nullable()->default(null);
			$table->string('assigned_type', 191)->nullable()->default(null);
			$table->dateTime('last_checkout')->nullable()->default(null);
			$table->dateTime('last_checkin')->nullable()->default(null);
			$table->date('expected_checkin')->nullable()->default(null);
			$table->timestamp('created_at')->nullable()->default(null);
			$table->timestamp('updated_at')->nullable()->default(null);
			$table->timestamp('deleted_at')->nullable()->default(null);
		});
        // populate table with content of inventory.assets table
        Capsule::update("INSERT INTO klusbibdb.kb_sync_assets (id, name, asset_tag, model_id, image, status_id, assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at)"
        . " SELECT id, name, asset_tag, model_id, image, status_id, assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at FROM inventory.assets");

        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ai`');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_au`');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ad`');

        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $sql = "
        CREATE TRIGGER inventory.`assets_ai` AFTER INSERT ON inventory.`assets` FOR EACH ROW INSERT INTO klusbibdb.kb_sync_assets (
            id, name, asset_tag, model_id, image, status_id, assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at)
           VALUES (
            NEW.id, NEW.name, NEW.asset_tag, NEW.model_id, NEW.image, NEW.status_id, NEW.assigned_to, NEW.assigned_type, NEW.last_checkout, NEW.last_checkin, NEW.expected_checkin, NEW.created_at, NEW.updated_at, NEW.deleted_at)";
        $db->exec($sql);

        $sql = "
        CREATE TRIGGER inventory.`assets_au` AFTER UPDATE ON inventory.`assets` FOR EACH ROW
         UPDATE klusbibdb.kb_sync_assets 
         SET name = NEW.name,
          asset_tag = NEW.asset_tag,
          model_id = NEW.model_id,
          image = NEW.image,
          status_id = NEW.status_id,
          assigned_to = NEW.assigned_to,
          assigned_type = NEW.assigned_type, 
          last_checkout = NEW.last_checkout,
          last_checkin = NEW.last_checkin, 
          expected_checkin = NEW.expected_checkin, 
          created_at = NEW.created_at, 
          updated_at = NEW.updated_at, 
          deleted_at = NEW.deleted_at 
          WHERE id = NEW.id;";
        $db->exec($sql);

        $sql = "CREATE TRIGGER inventory.`assets_ad` AFTER DELETE ON inventory.`assets` FOR EACH ROW DELETE FROM klusbibdb.kb_sync_assets WHERE id = OLD.id;";
        $db->exec($sql);

        // Keep assets/kb_sync_assets and inventory_item in sync

        // Add kb_sync_assets triggers to update inventory_item
        $sql = "CREATE TRIGGER klusbibdb.`kb_sync_assets_bi` BEFORE INSERT ON klusbibdb.`kb_sync_assets` FOR EACH ROW
        BEGIN
        IF NOT EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = NEW.id) THEN

          INSERT INTO klusbibdb.inventory_item (
            id, created_by, assigned_to, current_location_id, item_condition, created_at, updated_at,
            name, sku, description, keywords, brand, care_information, component_information, 
            loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, 
            item_sector, is_reservable, deposit_amount, item_type, donated_by, owned_by)
            SELECT 
            NEW.`id`, null, NEW.`assigned_to`, null, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
            NEW.`name`, NEW.`asset_tag`, null, null, null, null,
            null, null, 1, 1, null, null, null, null, null, 
            null, 1, null, 'loan', null, null;

            --IF NOT NEW.`experience_level` IS NULL  
            --AND EXISTS (SELECT 1 FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)) THEN
            --   INSERT into product_field_value (product_field_id, inventory_item_id, field_value)
            --    VALUES (1, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)));
            --END IF;
            --IF NOT NEW.`safety_risk` IS NULL 
            --AND EXISTS (SELECT 1 FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)) THEN
            --   INSERT into product_field_value (product_field_id, inventory_item_id, field_value)
            --    VALUES (2, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)));
            --END IF;
            --IF NOT NEW.`deliverable` IS NULL THEN
            --   INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
            --    VALUES (3, NEW.`id`, CASE WHEN (NEW.`deliverable` > 0) THEN 1 ELSE 0 END);
            --END IF;
            --IF NOT NEW.`size` IS NULL THEN
            --   INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
            --    VALUES (4, NEW.`id`, NEW.`size`);
            --END IF;
        END IF
        END";
        $db->exec($sql);
        $sql = "CREATE TRIGGER klusbibdb.`kb_sync_assets_bu` BEFORE UPDATE ON klusbibdb.`kb_sync_assets` FOR EACH ROW
        BEGIN
        IF EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = OLD.id) THEN
            IF NOT OLD.name <=> NEW.name THEN
                UPDATE klusbibdb.`inventory_item`
                SET name = NEW.name,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id;
            END IF
            IF NOT OLD.sku <=> NEW.sku THEN
                UPDATE klusbibdb.`inventory_item`
                SET sku = NEW.asset_tag,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id;
            END IF
            IF NOT OLD.assigned_to <=> NEW.assigned_to THEN
                --UPDATE klusbibdb.`inventory_item`
                --SET sku = NEW.asset_tag,
                --updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                --WHERE id = OLD.id;
            END IF
        ELSE
          INSERT INTO klusbibdb.inventory_item (
            id, created_by, assigned_to, current_location_id, item_condition, created_at, updated_at,
            name, sku, description, keywords, brand, care_information, component_information, 
            loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, 
            item_sector, is_reservable, deposit_amount, item_type, donated_by, owned_by)
            SELECT 
            NEW.`id`, null, NEW.`assigned_to`, null, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
            NEW.`name`, NEW.`asset_tag`, null, null, null, null,
            null, null, 1, 1, null, null, null, null, null, 
            null, 1, null, 'loan', null, null;
        END IF

        -- if model_id changed
        IF (NOT NEW.model_id <=> OLD.model_id) THEN
         -- update name??
        END IF

        -- if image changed
        IF (NOT NEW.image <=> OLD.image) THEN
          -- how to download/convert image?
        END IF

        -- if status changed
        IF (NOT NEW.status <=> OLD.status) THEN
          -- item movement? item location update?
        END IF

        -- if last_checkout changed
        IF (NOT NEW.last_checkout <=> OLD.last_checkout)  THEN
        END IF

        -- if last_checkin changed
        IF (NOT NEW.last_checkin <=> OLD.last_checkin) THEN
        END IF

        -- if expected checkin changed
        IF (NOT NEW.expected_checkin <=> OLD.expected_checkin) THEN
          -- find loan and update end date
        END IF

        -- if assinged_to changed
        IF (NOT NEW.assigned_to <=> OLD.assigned_to) THEN
          -- find loan and update user
        END IF

        END";
        $db->exec($sql);
        $sql = "CREATE TRIGGER klusbibdb.`kb_sync_assets_bd` BEFORE DELETE ON klusbibdb.`kb_sync_assets` FOR EACH ROW
        BEGIN
          DELETE FROM klusbibdb.inventory_item WHERE id = OLD.id;
        END";
        $db->exec($sql);

        // Triggers on inventory_item to sync with assets
        $sql = "CREATE TRIGGER klusbibdb.`inventory_item_bi` BEFORE INSERT ON klusbibdb.`inventory_item` FOR EACH ROW
        BEGIN
          IF NOT EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
            INSERT INTO inventory.assets  (
            id, name, asset_tag, model_id, created_at, updated_at)
            SELECT 
            NEW.`id`, NEW.name, NEW.sku, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
          END IF
        END";
        $db->exec($sql);
        $sql = "CREATE TRIGGER klusbibdb.`inventory_item_bu` BEFORE UPDATE ON klusbibdb.`inventory_item` FOR EACH ROW
        BEGIN
          IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
            IF NOT OLD.name <=> NEW.name THEN
                UPDATE inventory.assets 
                SET name = NEW.name,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id;
            END IF
            IF NOT OLD.sku <=> NEW.sku THEN
                UPDATE inventory.assets 
                SET asset_tag = NEW.sku,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id;
            END IF
          ELSE
            INSERT INTO inventory.assets  (
                id, name, asset_tag, model_id, created_at, updated_at)
                SELECT 
                NEW.`id`, NEW.name, NEW.sku, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
          END IF
        END";
        $db->exec($sql);
        $sql = "CREATE TRIGGER klusbibdb.`inventory_item_bd` BEFORE DELETE ON klusbibdb.`inventory_item` FOR EACH ROW
        BEGIN
          DELETE FROM inventory.assets WHERE id = OLD.id;
        END";
        $db->exec($sql);

	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
		Capsule::schema()->drop('kb_sync_assets');
        $this->query('DROP TRIGGER IF EXISTS `assets_ai`');
        $this->query('DROP TRIGGER IF EXISTS `assets_au`');
        $this->query('DROP TRIGGER IF EXISTS `assets_ad`');
	}
}