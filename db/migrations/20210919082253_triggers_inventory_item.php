<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class TriggersInventoryItem extends AbstractCapsuleMigration
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
        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        $this->query('DROP TRIGGER IF EXISTS `le_inventory_item_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_inventory_item_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_inventory_item_ad`');
        $sql = " 
CREATE TRIGGER `le_inventory_item_ai` AFTER INSERT ON `inventory_item`
 FOR EACH ROW 
 BEGIN
    INSERT INTO lendengine.inventory_item (
    id, name, item_type, created_by, assigned_to, current_location_id, item_condition, sku, description, keywords, brand, care_information, component_information, 
    loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, 
    item_sector, is_reservable, deposit_amount, donated_by, owned_by, created_at, updated_at)
    SELECT 
    NEW.`id`, NEW.`name`, 'loan', NEW.`created_by`, NEW.`assigned_to`, NEW.`current_location_id`, NEW.`item_condition`, NEW.`sku`, NEW.`description`, NEW.`keywords`, NEW.`brand`, NEW.`care_information`, NEW.`component_information`, 
    NEW.`loan_fee`, NEW.`max_loan_days`, NEW.`is_active`, NEW.`show_on_website`, NEW.`serial`, NEW.`note`, NEW.`price_cost`, NEW.`price_sell`, substring(NEW.`short_url`,0,64), 
    NEW.`item_sector`, NEW.`is_reservable`, NEW.`deposit_amount`, NEW.`donated_by`, NEW.`owned_by`, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);

    IF NOT NEW.`experience_level` IS NULL  
    AND EXISTS (SELECT 1 FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)) THEN
       INSERT into product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (1, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)));
    END IF;
    IF NOT NEW.`safety_risk` IS NULL 
    AND EXISTS (SELECT 1 FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)) THEN
       INSERT into product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (2, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)));
    END IF;
    IF NOT NEW.`deliverable` IS NULL THEN
       INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (3, NEW.`id`, CASE WHEN (NEW.`deliverable` > 0) THEN 1 ELSE 0 END);
    END IF;
    IF NOT NEW.`size` IS NULL THEN
       INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (4, NEW.`id`, NEW.`size`);
    END IF;

END
";
        $db->exec($sql);

        $sql = " 
CREATE TRIGGER `le_inventory_item_au` AFTER UPDATE ON `inventory_item` FOR EACH ROW 
BEGIN
IF EXISTS (SELECT 1 FROM lendengine.inventory_item WHERE id = OLD.id) THEN
    UPDATE lendengine.`inventory_item`
    SET name = NEW.`name`,
    item_type = 'loan',
    created_by = NEW.`created_by`,
    assigned_to =NEW.`assigned_to`,
    current_location_id = NEW.current_location_id,
    item_condition = NEW.item_condition,
    sku = NEW.sku,
    description = NEW.description,
    keywords = NEW.keywords,
    brand = NEW.brand,
    care_information = NEW.care_information,
    component_information = NEW.component_information,
    loan_fee = NEW.loan_fee,
    max_loan_days = NEW.max_loan_days,
    is_active = NEW.is_active,
    show_on_website = NEW.show_on_website,
    serial = NEW.serial,
    note = NEW.note,
    price_cost = NEW.price_cost,
    price_sell = NEW.price_sell,
    short_url = substring(NEW.`short_url`,0,64),
    item_sector = NEW.item_sector,
    is_reservable = NEW.is_reservable,
    deposit_amount = NEW.deposit_amount,
    donated_by = NEW.donated_by,
    owned_by = NEW.owned_by,
    created_at = ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), 
    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
    where id = OLD.id;

    IF NOT NEW.`experience_level` IS NULL  
    AND EXISTS (SELECT 1 FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)) THEN
       IF EXISTS (SELECT 1 FROM product_field_value WHERE product_field_id = 1 AND inventory_item_id = OLD.id) THEN
           UPDATE product_field_value 
           SET field_value = (SELECT id FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`))
           WHERE product_field_id = 1 AND inventory_item_id = OLD.id;
       ELSE
           INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
            VALUES (1, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)));
       END IF;
    ELSE
      DELETE FROM product_field_value WHERE product_field_id = 1 AND inventory_item_id = OLD.id;
    END IF;
    IF NOT NEW.`safety_risk` IS NULL 
    AND EXISTS (SELECT 1 FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)) THEN
       IF EXISTS (SELECT 1 FROM product_field_value WHERE product_field_id = 2 AND inventory_item_id = OLD.id) THEN
           UPDATE product_field_value 
           SET field_value = (SELECT id FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`))
           WHERE product_field_id = 2 AND inventory_item_id = OLD.id;
       ELSE
           INSERT into product_field_value (product_field_id, inventory_item_id, field_value)
            VALUES (2, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)));
       END IF;
    ELSE
      DELETE FROM product_field_value WHERE product_field_id = 2 AND inventory_item_id = OLD.id;
    END IF;
    IF NOT NEW.`deliverable` IS NULL THEN
       IF EXISTS (SELECT 1 FROM product_field_value WHERE product_field_id = 3 AND inventory_item_id = OLD.id) THEN
           UPDATE product_field_value 
           SET field_value = CASE WHEN (NEW.`deliverable` > 0) THEN 1 ELSE 0 END
           WHERE product_field_id = 3 AND inventory_item_id = OLD.id;
       ELSE
           INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
            VALUES (3, NEW.`id`, CASE WHEN (NEW.`deliverable` > 0) THEN 1 ELSE 0 END);
       END IF;
    ELSE
      DELETE FROM product_field_value WHERE product_field_id = 3 AND inventory_item_id = OLD.id;
    END IF;
    IF NOT NEW.`size` IS NULL THEN
       IF EXISTS (SELECT 1 FROM product_field_value WHERE product_field_id = 4 AND inventory_item_id = OLD.id) THEN
           UPDATE product_field_value 
           SET field_value = NEW.`size`
           WHERE product_field_id = 4 AND inventory_item_id = OLD.id;
       ELSE
           INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
            VALUES (4, NEW.`id`, NEW.`size`);
       END IF;
    ELSE
      DELETE FROM product_field_value WHERE product_field_id = 4 AND inventory_item_id = OLD.id;
    END IF;

ELSE
    INSERT INTO lendengine.inventory_item (
    id, name, item_type, created_by, assigned_to, current_location_id, item_condition, sku, description, keywords, brand, care_information, 
    component_information, loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, item_sector, 
    is_reservable, deposit_amount, donated_by, owned_by, created_at, updated_at)
    SELECT 
    NEW.`id`, NEW.`name`, 'loan', NEW.`created_by`, NEW.`assigned_to`, NEW.`current_location_id`, NEW.`item_condition`, NEW.`sku`, NEW.`description`, NEW.`keywords`, NEW.`brand`, NEW.`care_information`, 
    NEW.`component_information`, NEW.`loan_fee`, NEW.`max_loan_days`, NEW.`is_active`, NEW.`show_on_website`, NEW.`serial`, NEW.`note`, NEW.`price_cost`, NEW.`price_sell`, substring(NEW.`short_url`,0,64), NEW.`item_sector`, 
    NEW.`is_reservable`, NEW.`deposit_amount`, NEW.`donated_by`, NEW.`owned_by`, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);

    IF NOT NEW.`experience_level` IS NULL THEN
       INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (1, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 1 AND option_name = UPPER(NEW.`experience_level`)));
    END IF;
    IF NOT NEW.`safety_risk` IS NULL THEN
       INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (2, NEW.`id`, (SELECT id FROM product_field_select_option WHERE product_field_id = 2 AND option_name = UPPER(NEW.`safety_risk`)));
    END IF;
    IF NOT NEW.`deliverable` IS NULL THEN
       INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (3, NEW.`id`, CASE WHEN (NEW.`deliverable` > 0) THEN 1 ELSE 0 END);
    END IF;
    IF NOT NEW.`size` IS NULL THEN
       INSERT into  product_field_value (product_field_id, inventory_item_id, field_value)
        VALUES (4, NEW.`id`, NEW.`size`);
    END IF;

END IF;
END
";
        $db->exec($sql);
        $sql = " 
CREATE TRIGGER `le_inventory_item_ad` AFTER DELETE ON `inventory_item` FOR EACH ROW 
BEGIN 
    delete from lendengine.inventory_item where id = OLD.id;
    delete from product_field_value where inventory_item_id = OLD.id; 
END
";
        $db->exec($sql);
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->query('DROP TRIGGER IF EXISTS `le_inventory_item_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_inventory_item_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_inventory_item_ad`');
	}
}