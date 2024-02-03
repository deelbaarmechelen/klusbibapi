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
        Capsule::schema()->dropIfExists('kb_sync_assets');
        // create a table containing all the data to be synced from inventory.assets to klubsibapi/lendengine
        Capsule::schema()->create('kb_sync_assets', function(Illuminate\Database\Schema\Blueprint $table){
            $table->integer('id')->unsigned()->default(1);
            $table->string('name', 191)->nullable()->default(null);
            $table->string('asset_tag', 191)->nullable()->default(null);
            $table->integer('model_id')->nullable()->default(null);
            $table->text('image')->nullable()->default(null);
            $table->integer('status_id')->nullable()->default(null);
            $table->integer('assigned_to')->nullable()->default(null);
            $table->integer('kb_assigned_to')->nullable()->default(null);
            $table->string('assigned_type', 191)->nullable()->default(null);
            $table->dateTime('last_checkout')->nullable()->default(null);
            $table->dateTime('last_checkin')->nullable()->default(null);
            $table->date('expected_checkin')->nullable()->default(null);
            $table->timestamp('created_at')->nullable()->default(null);
            $table->timestamp('updated_at')->nullable()->default(null);
            $table->timestamp('deleted_at')->nullable()->default(null);
        });
        // populate table with content of inventory.assets table
        Capsule::update("INSERT INTO klusbibdb.kb_sync_assets (id, name, asset_tag, model_id, image, status_id, assigned_to, kb_assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at)"
        . " SELECT inventory.assets.id, inventory.assets.name, asset_tag, model_id, inventory.assets.image, status_id, assigned_to, employee_num, assigned_type, last_checkout, last_checkin, expected_checkin, inventory.assets.created_at, inventory.assets.updated_at, inventory.assets.deleted_at "
        . " FROM inventory.assets LEFT JOIN inventory.users ON inventory.assets.assigned_to = inventory.users.id");

        // remove obsolete triggers (were syncing with lendengine schema, but now merged into klusbibdb)
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`le_inventory_item_ai`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`le_inventory_item_au`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`le_inventory_item_ad`');

        // make sure new triggers do not exist
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ai`');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_au`');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ad`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bu`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bd`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bu`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bd`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bu`');

        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkout`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkin`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_extend`');
        $this->query('DROP PROCEDURE IF EXISTS inventory.`kb_checkout`');
        $this->query('DROP PROCEDURE IF EXISTS inventory.`kb_checkin`');
        $this->query('DROP PROCEDURE IF EXISTS inventory.`kb_extend`');

        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        Capsule::schema()->dropIfExists('kb_log');
          $sql = "
          CREATE TABLE  kb_log (
            id int(11) NOT NULL auto_increment,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log_msg text,
            PRIMARY KEY  (id)
          ) ENGINE=MYISAM";
          $db->exec($sql);
          $sql = "
CREATE PROCEDURE `kb_log_msg`(msg TEXT)
BEGIN
    insert into kb_log (log_msg) select msg;
END
";
          $db->exec($sql);
  
        $sql = "
CREATE PROCEDURE klusbibdb.`kb_checkout` 
        (IN inventory_item_id INT, IN loan_contact_id INT, IN datetime_out DATETIME, IN datetime_in DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE new_loan_id INT DEFAULT 0;
DECLARE new_loan_row_id INT DEFAULT 0;
-- Set location to 1 = 'On loan'
DECLARE location_id INT DEFAULT 1;
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = inventory_item_id) 
AND EXISTS (SELECT 1 FROM contact WHERE id = loan_contact_id) THEN
        
    -- Set location to 1 = 'On loan'
    UPDATE inventory_item 
    SET current_location_id = location_id
    WHERE id = inventory_item_id;

    INSERT INTO loan (
        contact_id, datetime_out, datetime_in, status, total_fee, created_at)
    SELECT
    loan_contact_id, ifnull(datetime_out, CURRENT_TIMESTAMP), ifnull(datetime_in, CURRENT_TIMESTAMP), 'ACTIVE', 0, CURRENT_TIMESTAMP;
    SET new_loan_id = LAST_INSERT_ID();
    
    INSERT INTO loan_row (
        loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
        SELECT new_loan_id, inventory_item_id, 1, datetime_out, ifnull(datetime_in, ifnull(datetime_out, CURRENT_TIMESTAMP)), 
        datetime_out, null, 0, 1, 1;
    SET new_loan_row_id = LAST_INSERT_ID();

    INSERT INTO item_movement(
        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
        SELECT inventory_item_id, location_id, new_loan_row_id, loan_contact_id, CURRENT_TIMESTAMP, 1;

    IF NOT comment IS NULL THEN
        INSERT INTO note (contact_id, loan_id, inventory_item_id, `text`, admin_only, created_at)
        SELECT loan_contact_id, new_loan_id, inventory_item_id, comment, 1, CURRENT_TIMESTAMP;
    END IF;  
ELSE
    call kb_log_msg(concat('Warning: inventory_item or contact missing in kb_checkout - loan creation skipped for inventory item with id: ', inventory_item_id));
END IF;
END
";
        $db->exec($sql);
        
        $sql = "
CREATE PROCEDURE klusbibdb.`kb_checkin` 
            (IN item_id INT, IN checkin_datetime DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE existing_loan_id INT DEFAULT 0;
DECLARE loan_contact_id INT DEFAULT 0;
-- Set location to 2 = 'In stock'
DECLARE location_id INT DEFAULT 2;
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = item_id) 
    AND EXISTS (SELECT 1 FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status = 'ACTIVE') THEN
    
    SET existing_loan_id := (SELECT loan_id FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status = 'ACTIVE');
    SET loan_contact_id := (SELECT contact_id FROM loan WHERE id = existing_loan_id);

    UPDATE inventory_item 
    SET current_location_id = location_id
    WHERE id = item_id;

    INSERT INTO item_movement(
        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
        SELECT item_id, location_id, NULL, NULL, CURRENT_TIMESTAMP, NULL;
        
    IF EXISTS (SELECT 1 FROM loan_row WHERE inventory_item_id = item_id AND loan_id = existing_loan_id) THEN
    
        UPDATE loan_row SET checked_in_at = checkin_datetime
        WHERE loan_id = existing_loan_id AND inventory_item_id = item_id;

        IF NOT EXISTS (SELECT 1 FROM loan_row WHERE loan_id = existing_loan_id AND checked_in_at IS NULL) THEN
            -- all items have been checked in
            UPDATE loan SET status = 'CLOSED', datetime_in = checkin_datetime 
            WHERE id = existing_loan_id;
        END IF;

    END IF;

    IF NOT comment IS NULL THEN
        INSERT INTO note (contact_id, loan_id, inventory_item_id, `text`, admin_only, created_at)
        SELECT loan_contact_id, existing_loan_id, item_id, comment, 1, CURRENT_TIMESTAMP;
    END IF;  
ELSE
    call kb_log_msg(concat('Warning: inventory_item or loan missing in kb_checkin - loan_row update skipped for inventory item with id: ', item_id));
END IF;
END
";
        $db->exec($sql);

        $sql = "
CREATE PROCEDURE klusbibdb.`kb_extend` 
            (IN item_id INT, IN expected_checkin_datetime DATETIME) 
BEGIN 
DECLARE existing_loan_id INT DEFAULT 0;
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = item_id) 
    AND EXISTS (SELECT 1 FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND (loan.status = 'ACTIVE' OR loan.status = 'OVERDUE')) THEN
    
    SELECT loan_id INTO existing_loan_id FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id 
    WHERE inventory_item_id = item_id AND (loan.status = 'ACTIVE' OR loan.status = 'OVERDUE');

    UPDATE loan_row SET due_in_at = expected_checkin_datetime
    WHERE loan_id = existing_loan_id AND inventory_item_id = item_id;
    UPDATE loan SET datetime_in = expected_checkin_datetime
    WHERE id = existing_loan_id AND datetime_in < expected_checkin_datetime;
        
ELSE
    call kb_log_msg(concat('Warning: inventory_item or loan missing in kb_extend - loan_row update skipped for inventory item with id: ', item_id));
END IF;
END
";
        $db->exec($sql);

        $sql = "
        CREATE TRIGGER inventory.`assets_ai` AFTER INSERT ON inventory.`assets` FOR EACH ROW 
        BEGIN
        IF @sync_api_to_inventory IS NULL THEN
   
           SET @sync_inventory_to_api = 1;
           INSERT INTO klusbibdb.kb_sync_assets (
            id, name, asset_tag, model_id, image, status_id, assigned_to, kb_assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at)
           VALUES (
            NEW.id, NEW.name, NEW.asset_tag, NEW.model_id, NEW.image, NEW.status_id, NEW.assigned_to, 
            (SELECT employee_num FROM inventory.users where id = NEW.assigned_type),
            NEW.assigned_type, NEW.last_checkout, NEW.last_checkin, NEW.expected_checkin, NEW.created_at, NEW.updated_at, NEW.deleted_at);
           SET @sync_inventory_to_api = null;
        END IF;
        END";
        $db->exec($sql);

        $sql = "
        CREATE TRIGGER inventory.`assets_au` AFTER UPDATE ON inventory.`assets` FOR EACH ROW
        BEGIN 
        IF @sync_api_to_inventory IS NULL THEN
            SET @sync_inventory_to_api = 1;
            UPDATE klusbibdb.kb_sync_assets 
            SET name = NEW.name,
            asset_tag = NEW.asset_tag,
            model_id = NEW.model_id,
            image = NEW.image,
            status_id = NEW.status_id,
            assigned_to = NEW.assigned_to,
            kb_assigned_to = (SELECT employee_num FROM inventory.users where id = NEW.assigned_to),
            assigned_type = NEW.assigned_type, 
            last_checkout = NEW.last_checkout,
            last_checkin = NEW.last_checkin, 
            expected_checkin = NEW.expected_checkin, 
            created_at = NEW.created_at, 
            updated_at = NEW.updated_at, 
            deleted_at = NEW.deleted_at 
            WHERE id = NEW.id;

            SET @sync_inventory_to_api = NULL;
        END IF;
        END";
        $db->exec($sql);

        $sql = "
        CREATE TRIGGER inventory.`assets_ad` AFTER DELETE ON inventory.`assets` FOR EACH ROW 
        BEGIN
        IF @sync_api_to_inventory IS NULL THEN
            SET @sync_inventory_to_api = 1;
            DELETE FROM klusbibdb.kb_sync_assets WHERE id = OLD.id;
            SET @sync_inventory_to_api = NULL;
        END IF;
        END";
        $db->exec($sql);

        // Keep assets/kb_sync_assets and inventory_item in sync

        // Add kb_sync_assets triggers to update inventory_item        
        $sql = " 
CREATE TRIGGER klusbibdb.`kb_sync_assets_bi` BEFORE INSERT ON klusbibdb.`kb_sync_assets` FOR EACH ROW
BEGIN
    DECLARE default_item_name varchar(255) DEFAULT ' ';
    IF NOT EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = NEW.id) THEN
        SET default_item_name := (SELECT concat(ifnull(name, 'unknown'), '-', ifnull(model_number, 'none')) FROM inventory.models WHERE id = NEW.model_id);
        INSERT INTO klusbibdb.inventory_item (
        id, created_by, assigned_to, current_location_id, item_condition, created_at, updated_at,
        name, sku, description, keywords, brand, care_information, component_information, 
        loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, 
        item_sector, is_reservable, deposit_amount, item_type, donated_by, owned_by)
        SELECT 
        NEW.`id`, null, NEW.`kb_assigned_to`, null, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
        ifnull(NEW.`name`, default_item_name), NEW.`asset_tag`, null, null, null, null, null,
        null, null, 1, 1, null, null, null, null, null, 
        null, 1, null, 'loan', null, null;

    ELSE
        call kb_log_msg(concat('Detected missing inventory_item with id: ', NEW.id, ' upon insert in kb_sync_assets'));
    END IF;
END
";
       $db->exec($sql);

        $sql = "
CREATE TRIGGER klusbibdb.`kb_sync_assets_bu` BEFORE UPDATE ON klusbibdb.`kb_sync_assets` FOR EACH ROW
BEGIN
    DECLARE dummy_ INT(11);
    DECLARE default_item_name varchar(255) DEFAULT ' ';
    IF EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = OLD.id) THEN
        IF NOT OLD.name <=> NEW.name THEN
            SET default_item_name := (SELECT concat(ifnull(name, 'unknown'), '-', ifnull(model_number, 'none')) FROM inventory.models WHERE id = NEW.model_id);
            UPDATE klusbibdb.`inventory_item`
            SET name = ifnull(NEW.`name`, default_item_name),
            updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
            WHERE id = OLD.id;
        END IF;
        IF NOT OLD.asset_tag <=> NEW.asset_tag THEN
            UPDATE klusbibdb.`inventory_item`
            SET sku = NEW.asset_tag,
            updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
            WHERE id = OLD.id;
        END IF;
    ELSE
        INSERT INTO klusbibdb.inventory_item (
        id, assigned_to, created_at, updated_at,
        name, sku, item_type)
        SELECT 
        NEW.`id`, NEW.`kb_assigned_to`, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
        NEW.`name`, NEW.`asset_tag`, 'loan';
    END IF;

    IF (NOT NEW.model_id <=> OLD.model_id) THEN
        call kb_log_msg(concat('Warning: kb_sync_assets model_id update not reported to inventory_item: ', ifnull(OLD.model_id, 'null'), ' -> ', ifnull(NEW.model_id, 'null')));
    END IF;

    -- image sync handled by sync_inventory for tools (requires creation of large and thumb image)
    -- IF (NOT NEW.image <=> OLD.image) THEN
    --     call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,') image update not reported to inventory_item: ', ifnull(OLD.image, 'null'), ' -> ', ifnull(NEW.image, 'null')));
    -- END IF;

    IF (NOT NEW.status_id <=> OLD.status_id) THEN
        call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  status_id update not reported to inventory_item: ', ifnull(OLD.status_id, 'null'), ' -> ', ifnull(NEW.status_id, 'null')));
    END IF;

    IF (NOT NEW.last_checkout <=> OLD.last_checkout 
       AND NOT NEW.last_checkout IS NULL) THEN
        IF ((NOT NEW.kb_assigned_to IS NULL)
        AND (NEW.assigned_type = 'App\\\\Models\\\\User'))  THEN
            CALL kb_checkout (NEW.id, NEW.kb_assigned_to, NEW.last_checkout, NEW.expected_checkin, 'Checkout from inventory' );
        ELSE
            call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  last_checkout (assigned to ', ifnull(NEW.kb_assigned_to, 'null'), ', assigned type ', ifnull(NEW.assigned_type, 'null'),') update not reported to inventory_item: ', ifnull(OLD.last_checkout, 'null'), ' -> ', ifnull(NEW.last_checkout, 'null')));
        END IF;
    END IF;

    IF (NOT NEW.last_checkin <=> OLD.last_checkin
        AND NOT NEW.last_checkin IS NULL) THEN
        IF (NEW.kb_assigned_to IS NULL) THEN
            CALL kb_checkin (NEW.id, NEW.last_checkin, 'Checkin from inventory' );
        ELSE
            call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  last_checkin (assigned to ', ifnull(NEW.kb_assigned_to, 'null'), ') update not reported to inventory_item: ', ifnull(OLD.last_checkin, 'null'), ' -> ', ifnull(NEW.last_checkin, 'null')));
        END IF;
    END IF;

    IF (NOT NEW.expected_checkin <=> OLD.expected_checkin
      AND NOT OLD.expected_checkin IS NULL
      AND NOT NEW.expected_checkin IS NULL) THEN
        CALL kb_extend (NEW.id, NEW.expected_checkin);
    END IF;

    -- IF (NOT NEW.assigned_to <=> OLD.assigned_to) THEN
    --     call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  assigned_to update not reported to inventory_item (inventory.asset values): ', ifnull(OLD.assigned_to, 'null'), ' -> ', ifnull(NEW.assigned_to, 'null')));
    -- END IF;

END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`kb_sync_assets_bd` BEFORE DELETE ON klusbibdb.`kb_sync_assets` FOR EACH ROW
BEGIN
    DELETE FROM klusbibdb.inventory_item WHERE id = OLD.id;
END
";
        $db->exec($sql);


        // Procedures on inventory
        $sql = "
CREATE PROCEDURE inventory.`kb_checkout` 
        (IN inventory_item_id INT, IN loan_contact_id INT, IN datetime_out DATETIME, IN datetime_in DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE user_id INT DEFAULT 0;
DECLARE log_meta_json text;

    call klusbibdb.kb_log_msg(concat('Info: Checkout - Updating assets.last_checkout and expected_checkin for inventory item with id: ', ifnull(inventory_item_id, 'null')));
    SET user_id := (SELECT id FROM inventory.users where employee_num = loan_contact_id AND deleted_at IS NULL);
    UPDATE inventory.assets
    SET last_checkout = datetime_out,
        expected_checkin = datetime_in,
        checkout_counter = checkout_counter + 1,
        assigned_to = user_id,
        assigned_type = 'App\\\\Models\\\\User',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = inventory_item_id;

    -- Insert action log with comment
    -- TODO: update log_meta if old.expected_checkin is not null (requires old_checkin_datetime as input parameter)
    -- TODO: update log_meta if old.location_id is not null (requires old_location_id as input parameter)
    -- SET log_meta_json := concat('{\"expected_checkin\":{\"old\":\"null\",\"new\":\"',DATE_FORMAT(datetime_in, '%Y-%m-%d'),'\"},\"location_id\":{\"old\":2,\"new\":null}}}');
    INSERT INTO action_logs (user_id, action_type, target_id, target_type, note, item_type, item_id, expected_checkin, created_at, updated_at, company_id, action_date)
    SELECT 1, 'checkout', user_id, 'App\\\\Models\\\\User', comment, 'App\\\\Models\\\\Asset', inventory_item_id, datetime_in, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP;

END
";
        $db->exec($sql);
        
        $sql = "
CREATE PROCEDURE inventory.`kb_checkin` 
            (IN item_id INT, IN checkin_datetime DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE user_id INT DEFAULT 0;
DECLARE log_meta_json text;
    call klusbibdb.kb_log_msg(concat('Info: Checkin - Updating assets.last_checkin for inventory item with id: ', ifnull(item_id, 'null')));
    SET user_id := (SELECT assigned_to FROM inventory.assets where id = item_id);
    UPDATE inventory.assets
    SET last_checkin = checkin_datetime,
        last_checkout = NULL,
        expected_checkin = NULL,
        checkin_counter = checkin_counter + 1,
        assigned_to = NULL,
        assigned_type = NULL,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = item_id
    AND last_checkin < checkin_datetime;

    -- Insert action log with comment
    -- TODO: update log_meta if old.expected_checkin is not null (requires old_checkin_datetime as input parameter
    -- SET log_meta_json := concat('{\"expected_checkin\":{\"old\":\"', DATE_FORMAT(old_checkin_datetime, '%Y-%m-%d'), '\",\"new\":\"null\"}}');
    INSERT INTO action_logs (user_id, action_type, target_id, target_type, note, item_type, item_id, created_at, updated_at, company_id, action_date)
    SELECT 1, 'checkin from', user_id, 'App\\\\Models\\\\User', comment, 'App\\\\Models\\\\Asset', item_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP;

END
";
        $db->exec($sql);

        $sql = "
CREATE PROCEDURE inventory.`kb_extend` 
            (IN item_id INT, IN old_checkin_datetime DATETIME, IN new_checkin_datetime DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE user_id INT DEFAULT 0;
DECLARE log_meta_json text;
    call klusbibdb.kb_log_msg(concat('Info: Extend - Updating assets.expected_checkin for inventory item with id: ', ifnull(item_id, 'null')));
    SET user_id := (SELECT assigned_to FROM inventory.assets where id = item_id);
    UPDATE inventory.assets
    SET expected_checkin = new_checkin_datetime,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = item_id;

    -- Insert action log
    SET log_meta_json := concat('{\"expected_checkin\":{\"old\":\"', DATE_FORMAT(old_checkin_datetime, '%Y-%m-%d'), '\",\"new\":\"', DATE_FORMAT(new_checkin_datetime, '%Y-%m-%d 00:00:00'), '\"}}');
    INSERT INTO action_logs (user_id, action_type, note, item_type, item_id, created_at, updated_at, company_id, log_meta)
    SELECT 1, 'update', comment, 'App\\\\Models\\\\Asset', item_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, log_meta_json;

END
";
        $db->exec($sql);

        // Triggers on inventory_item to sync with assets
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bi` BEFORE INSERT ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
    IF NEW.created_at IS NULL THEN
      SET NEW.created_at = CURRENT_TIMESTAMP;
    END IF;
    IF NEW.updated_at IS NULL THEN
      SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
    SET NEW.short_url = substring(NEW.short_url,0,64);

    IF @sync_inventory_to_api IS NULL THEN
        SET @sync_api_to_inventory = 1;
        IF NOT EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
        INSERT INTO inventory.assets  (
        id, name, asset_tag, model_id, created_at, updated_at)
        SELECT 
        NEW.`id`, NEW.name, NEW.sku, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
        ELSE
            call kb_log_msg(concat('Warning: inventory asset already exists - inventory_item insert not reported to inventory.assets for id: ', NEW.id));
        END IF;
        SET @sync_api_to_inventory = NULL;
    END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bu` BEFORE UPDATE ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
    IF NEW.updated_at IS NULL THEN
      SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
    SET NEW.short_url = substring(NEW.short_url,0,64);

    IF @sync_inventory_to_api IS NULL THEN
        SET @sync_api_to_inventory = 1;
        IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
            IF NOT OLD.name <=> NEW.name THEN
                UPDATE inventory.assets 
                SET name = NEW.name,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id
                AND NOT name <=> NEW.name;
            END IF;
            IF NOT OLD.sku <=> NEW.sku THEN
                UPDATE inventory.assets 
                SET asset_tag = NEW.sku,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id
                AND asset_tag <> NEW.sku;
            END IF;
        ELSE
            call kb_log_msg(concat('Warning: inventory asset missing - created on the fly upon inventory_item update for id: ', NEW.id));
            INSERT INTO inventory.assets  (
                id, name, asset_tag, model_id, created_at, updated_at)
                SELECT 
                NEW.`id`, NEW.name, NEW.sku, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
        END IF;
        SET @sync_api_to_inventory = NULL;
    ELSE
        call kb_log_msg(concat('Warning: inventory asset update skipped - ongoing inventory to api sync upon inventory_item update for id: ', NEW.id));
    END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bd` BEFORE DELETE ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
IF @sync_inventory_to_api IS NULL THEN
    SET @sync_api_to_inventory = 1;
    DELETE FROM inventory.assets WHERE id = OLD.id;
    SET @sync_api_to_inventory = NULL;
END IF;
END
";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER klusbibdb.`loan_row_bi` BEFORE INSERT ON klusbibdb.`loan_row` FOR EACH ROW
BEGIN
IF @sync_inventory_to_api IS NULL THEN
    SET @sync_api_to_inventory = 1;
    IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) THEN
        IF (EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE' OR STATUS = 'PENDING') ) 
        AND NOT NEW.checked_out_at IS NULL
        AND NEW.checked_in_at IS NULL) THEN

            call kb_log_msg(concat('Info: Triggering inventory checkout upon loan_row insert for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkout`(NEW.inventory_item_id, 
                (SELECT contact_id FROM loan WHERE id = NEW.loan_id), 
                NEW.checked_out_at, NEW.due_in_at, 'Checkout from lend engine');
        END IF;
    ELSE
        call kb_log_msg(concat('Warning: inventory asset missing upon loan_row insert - inventory asset update skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null')));
    END IF;
    SET @sync_api_to_inventory = NULL;

END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`loan_row_bu` BEFORE UPDATE ON klusbibdb.`loan_row` FOR EACH ROW
BEGIN
IF @sync_inventory_to_api IS NULL THEN
    SET @sync_api_to_inventory = 1;
    IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) THEN
        IF (EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE' OR STATUS = 'PENDING') ) 
        AND OLD.checked_out_at IS NULL AND NOT NEW.checked_out_at IS NULL
        AND NEW.checked_in_at IS NULL) THEN
        
            call kb_log_msg(concat('Info: Triggering inventory checkout upon loan_row update for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkout`(NEW.inventory_item_id, 
                (SELECT contact_id FROM loan WHERE id = NEW.loan_id), 
                NEW.checked_out_at, NEW.due_in_at, 'Checkout from lend engine');
        ELSE
            call kb_log_msg(concat('Warning: loan missing upon loan_row update - inventory asset last_checkout update skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
        END IF;
        IF (OLD.checked_in_at IS NULL AND NOT NEW.checked_in_at IS NULL) THEN
            call kb_log_msg(concat('Info: Updating assets.last_checkin for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkin`(NEW.inventory_item_id, NEW.checked_in_at, 'Checkin from lend engine');
        END IF;
        IF (OLD.checked_in_at IS NULL AND NEW.checked_in_at IS NULL
        AND NOT OLD.checked_out_at IS NULL AND NOT NEW.checked_out_at IS NULL
        AND NOT NEW.due_in_at IS NULL AND NOT OLD.due_in_at <=> NEW.due_in_at) THEN
            call kb_log_msg(concat('Info: Updating assets.expected_checkin for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_extend`(NEW.inventory_item_id, OLD.due_in_at, NEW.due_in_at, 'Extend from lend engine');
        END IF;

    ELSE
        call kb_log_msg(concat('Warning: inventory asset missing upon loan_row update - inventory asset last_checkin update skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
    END IF;
    SET @sync_api_to_inventory = NULL;
END IF;
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
        $this->initCapsule();
        Capsule::schema()->drop('kb_sync_assets');
        Capsule::schema()->drop('kb_log');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ai`');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_au`');
        $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ad`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bu`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bd`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bu`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bd`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bu`');

        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkout`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkin`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_extend`');
        $this->query('DROP PROCEDURE IF EXISTS inventory.`kb_checkout`');
        $this->query('DROP PROCEDURE IF EXISTS inventory.`kb_checkin`');
        $this->query('DROP PROCEDURE IF EXISTS inventory.`kb_extend`');
    }
}