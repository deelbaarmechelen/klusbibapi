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
            $table->text('serial', 191)->nullable()->default(null);
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
            $table->timestamp('last_sync_timestamp')->nullable()->useCurrent();
        });
        // create functions and procedures
        $sql = file_get_contents(__DIR__ . "/20240214223000_create_procedures.sql");
        $sqlStmts = explode("$$", $sql);
        foreach($sqlStmts as $sqlStmt) {
            if (strlen(trim($sqlStmt)) == 0) {
                continue;
            }
            $this->multiQueryOnPDO($sqlStmt);
        }

        // populate table with content of inventory.assets table
        Capsule::update("INSERT INTO klusbibdb.kb_sync_assets (id, name, asset_tag, model_id, serial, image, status_id, assigned_to, kb_assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at)"
        . " SELECT inventory.assets.id, inventory.assets.name, asset_tag, model_id, inventory.assets.serial, inventory.assets.image, status_id, assigned_to, employee_num, assigned_type, last_checkout, last_checkin, expected_checkin, inventory.assets.created_at, inventory.assets.updated_at, inventory.assets.deleted_at "
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

        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        $sql = "
        CREATE TRIGGER inventory.`assets_ai` AFTER INSERT ON inventory.`assets` FOR EACH ROW 
        BEGIN
        DECLARE disable_sync_result TINYINT(1);
        IF klusbibdb.enable_sync_inventory2le() THEN
           INSERT INTO klusbibdb.kb_sync_assets (
            id, name, asset_tag, model_id, image, status_id, assigned_to, kb_assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at, last_sync_timestamp)
           VALUES (
            NEW.id, NEW.name, NEW.asset_tag, NEW.model_id, NEW.image, NEW.status_id, NEW.assigned_to, 
            (SELECT employee_num FROM inventory.users where id = NEW.assigned_type),
            NEW.assigned_type, NEW.last_checkout, NEW.last_checkin, NEW.expected_checkin, NEW.created_at, NEW.updated_at, NEW.deleted_at, NEW.created_at);
           SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        END";
        $db->exec($sql);

        $sql = "
        CREATE TRIGGER inventory.`assets_au` AFTER UPDATE ON inventory.`assets` FOR EACH ROW
        BEGIN 
        DECLARE disable_sync_result TINYINT(1);
        IF klusbibdb.enable_sync_inventory2le() THEN
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
            deleted_at = NEW.deleted_at,
            last_sync_timestamp = NEW.updated_at
            WHERE id = NEW.id;

            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        END";
        $db->exec($sql);

        $sql = "
        CREATE TRIGGER inventory.`assets_ad` AFTER DELETE ON inventory.`assets` FOR EACH ROW 
        BEGIN
        DECLARE disable_sync_result TINYINT(1);
        IF klusbibdb.enable_sync_inventory2le() THEN
            DELETE FROM klusbibdb.kb_sync_assets WHERE id = OLD.id;
            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        END";
        $db->exec($sql);

        // Keep assets/kb_sync_assets and inventory_item in sync

        // Add kb_sync_assets triggers to update inventory_item        
        $sql = " 
CREATE TRIGGER klusbibdb.`kb_sync_assets_bi` BEFORE INSERT ON klusbibdb.`kb_sync_assets` FOR EACH ROW
BEGIN
    DECLARE default_item_name varchar(255) DEFAULT ' ';
    -- Set location to 2 = 'In stock'
    DECLARE location_id INT DEFAULT 2;
    IF NOT EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = NEW.id) THEN
        SET default_item_name := (SELECT concat(ifnull(name, 'unknown'), '-', ifnull(model_number, 'none')) FROM inventory.models WHERE id = NEW.model_id);
        INSERT INTO klusbibdb.inventory_item (
        id, created_by, assigned_to, current_location_id, item_condition, created_at, updated_at,
        name, sku, description, keywords, brand, care_information, component_information, 
        loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, 
        item_sector, is_reservable, deposit_amount, item_type, donated_by, owned_by)
        SELECT 
        NEW.`id`, null, NEW.`kb_assigned_to`, location_id, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
        ifnull(NEW.`name`, default_item_name), NEW.`asset_tag`, null, null, null, null, null,
        null, null, 1, 1, LEFT(NEW.serial, 64), null, null, null, null, 
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
    DECLARE inventory_item_name varchar(255) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE inventory_item_sku varchar(255) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE inventory_item_serial varchar(64) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE default_item_name varchar(255) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE item_checked_out_at datetime;
    SET default_item_name := (SELECT concat(ifnull(name, 'unknown'), '-', ifnull(model_number, 'none')) FROM inventory.models WHERE id = NEW.model_id);
    IF EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = OLD.id) THEN
        -- (also?) compare new.name with inventory_item name
        SET inventory_item_name := (SELECT ifnull(name, 'unknown') FROM klusbibdb.inventory_item WHERE id = OLD.id);
        IF ((NOT OLD.name <=> NEW.name) OR (NEW.name <> inventory_item_name)) THEN
            UPDATE klusbibdb.`inventory_item`
            SET name = ifnull(NEW.`name`, default_item_name),
            updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
            WHERE id = OLD.id
            AND name <> NEW.`name`;
        END IF;
        -- (also?) compare new.asset_tag with inventory_item sku
        -- => always update sku if different of new.asset_tag
        SET inventory_item_sku := (SELECT sku FROM klusbibdb.inventory_item WHERE id = OLD.id);
        IF ((NOT OLD.asset_tag <=> NEW.asset_tag) OR (NEW.asset_tag <> inventory_item_sku)) THEN
            UPDATE klusbibdb.`inventory_item`
            SET sku = NEW.asset_tag,
            updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
            WHERE id = OLD.id
            AND sku <> NEW.asset_tag;
        END IF;
        SELECT serial INTO inventory_item_serial FROM klusbibdb.inventory_item WHERE id = OLD.id;
        IF ( (NOT OLD.serial <=> NEW.serial) OR (LEFT(NEW.serial, 64) <> inventory_item_serial ) ) THEN
            UPDATE klusbibdb.`inventory_item`
            SET serial = NEW.serial,
            updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
            WHERE id = OLD.id
            AND serial <> LEFT(NEW.serial, 64);
        END IF;
    ELSE
        INSERT INTO klusbibdb.inventory_item (
        id, assigned_to, created_at, updated_at,
        name, sku, item_type, serial)
        SELECT 
        NEW.`id`, NEW.`kb_assigned_to`, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
        ifnull(NEW.`name`, default_item_name), NEW.`asset_tag`, 'loan', LEFT(NEW.serial, 64);
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

    IF ((NOT NEW.last_checkout <=> OLD.last_checkout) 
       AND (NOT NEW.last_checkout IS NULL)) THEN
        IF ((NOT NEW.kb_assigned_to IS NULL)
        AND (NEW.assigned_type = 'App\\\\Models\\\\User'))  THEN
            CALL kb_checkout (NEW.id, NEW.kb_assigned_to, NEW.last_checkout, NEW.expected_checkin, 'Checkout from inventory' );
        ELSE
            call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,') last_checkout (assigned to ', ifnull(NEW.kb_assigned_to, 'null'), ', assigned type ', ifnull(NEW.assigned_type, 'null'),') update not reported to inventory_item: ', ifnull(OLD.last_checkout, 'null'), ' -> ', ifnull(NEW.last_checkout, 'null')));
        END IF;
    END IF;

    IF ((NOT NEW.last_checkin <=> OLD.last_checkin)
        AND (NOT NEW.last_checkin IS NULL)) THEN
        IF (NEW.kb_assigned_to IS NULL) THEN
            CALL kb_checkin (NEW.id, NEW.last_checkin, 'Checkin from inventory' );
        ELSE
            call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  last_checkin (assigned to ', ifnull(NEW.kb_assigned_to, 'null'), ') update not reported to inventory_item: ', ifnull(OLD.last_checkin, 'null'), ' -> ', ifnull(NEW.last_checkin, 'null')));
        END IF;
    END IF;

    IF ((NOT NEW.expected_checkin <=> OLD.expected_checkin)
      AND (NOT OLD.expected_checkin IS NULL)
      AND (NOT NEW.expected_checkin IS NULL)) THEN
        CALL kb_extend (NEW.id, NEW.expected_checkin);
    END IF;

    -- IF (NOT NEW.assigned_to <=> OLD.assigned_to) THEN
    --     call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  assigned_to update not reported to inventory_item (inventory.asset values): ', ifnull(OLD.assigned_to, 'null'), ' -> ', ifnull(NEW.assigned_to, 'null')));
    -- END IF;

    -- Extra checks for recovery from inconsistent situations (only when triggering extra sync)
    IF NEW.last_sync_timestamp > ifnull(NEW.`updated_at`, NEW.created_at) THEN
        -- if asset is assigned to a user, a matching ACTIVE/OVERDUE loan should exist
        IF ((inventory.is_on_loan(NEW.id)) AND (NOT klusbibdb.is_on_loan(NEW.id) )) THEN
            -- create a new loan on klusbibdb
            CALL kb_checkout (NEW.id, NEW.kb_assigned_to, NEW.last_checkout, NEW.expected_checkin, 'Checkout from inventory' );
        END IF;
        
        -- if asset is not assigned to a user, no matching ACTIVE/OVERDUE loan may exist
        IF ((NOT inventory.is_on_loan(NEW.id)) AND (klusbibdb.is_on_loan(NEW.id))) THEN
            -- check if loan exists in inventory activity, if it does then it has already been checked in
            -- create a new loan on klusbibdb
            SET item_checked_out_at := (SELECT MAX(checked_out_at) FROM loan_row WHERE inventory_item_id = NEW.id AND NOT checked_out_at IS NULL AND checked_in_at IS NULL);
            IF EXISTS (SELECT 1 FROM inventory.action_logs WHERE action_type = 'checkout' 
                    AND target_id = NEW.id 
                    AND target_type = 'App\\\\Models\\\\User'
                    AND created_at >= item_checked_out_at) THEN

                CALL kb_checkin (NEW.id, ifnull(NEW.last_checkin, CURRENT_TIMESTAMP), 'Checkin from inventory' );
            ELSE
                call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,') outdated - a more recent loan exists on lend engine (checked out on ', ifnull(item_checked_out_at, 'null'), ')' ));
            END IF;
        END IF;
    END IF;
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

        // TODO: For data consistency, force failure upon error in sync from lendengine to inventory
        // -> inventory data remains consistent at all time (=master)

        // Triggers on inventory_item to sync with assets
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bi` BEFORE INSERT ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in inventory_item_bi: inventory asset sync skipped for inventory item with id: ', ifnull(NEW.id, 'null'), ' sku: ', ifnull(NEW.sku, 'null') ));
    RESIGNAL;
END;
    IF NEW.created_at IS NULL THEN
      SET NEW.created_at = CURRENT_TIMESTAMP;
    END IF;
    IF NEW.updated_at IS NULL THEN
      SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
    SET NEW.short_url = substring(NEW.short_url,0,64);

    -- do not sync accessories
    IF (NEW.id < 100000 AND NOT klusbibdb.is_sync_inventory2le_enabled() ) THEN
        IF klusbibdb.enable_sync_le2inventory() THEN
            IF NOT EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
            INSERT INTO inventory.assets  (
            id, name, asset_tag, model_id, serial, created_at, updated_at)
            SELECT 
            NEW.`id`, NEW.name, NEW.sku, null, NEW.serial, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
            ELSE
                call kb_log_msg(concat('Warning: inventory asset already exists - inventory_item insert not reported to inventory.assets for id: ', NEW.id));
            END IF;
            SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
        ELSE
            call kb_log_msg(concat('Warning: inventory asset insert failed - ongoing inventory to api sync upon inventory_item insert for id: ', NEW.id));
            signal sqlstate '45000' set message_text = 'Unable to create inventory asset: sync (inventory -> api) ongoing (check @sync_inventory2le value if this is an error).';
        END IF;
    END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bu` BEFORE UPDATE ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in inventory_item_bu: inventory asset sync skipped for inventory item with id: ', ifnull(OLD.id, 'null') ));
    RESIGNAL;
END;
    IF NEW.updated_at IS NULL THEN
      SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
    SET NEW.short_url = substring(NEW.short_url,0,64);

    IF (OLD.id < 100000 AND NOT klusbibdb.is_sync_inventory2le_enabled() ) THEN
        IF klusbibdb.enable_sync_le2inventory() THEN
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
                IF NOT OLD.serial <=> NEW.serial THEN
                    UPDATE inventory.assets 
                    SET serial = NEW.serial,
                    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND serial <> NEW.serial;
                END IF;
            ELSE
                call kb_log_msg(concat('Warning: inventory asset missing - created on the fly upon inventory_item update for id: ', NEW.id));
                INSERT INTO inventory.assets  (
                    id, name, asset_tag, model_id, serial, created_at, updated_at)
                    SELECT 
                    NEW.`id`, NEW.name, NEW.sku, null, NEW.serial, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
            END IF;
            SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
        ELSE
            call kb_log_msg(concat('Warning: inventory asset update failed - ongoing inventory to api sync upon inventory_item update for id: ', NEW.id));
            signal sqlstate '45000' set message_text = 'Unable to update inventory asset: sync (inventory -> api) ongoing (check @sync_inventory2le value if this is an error).';
        END IF;
    END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bd` BEFORE DELETE ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in inventory_item_bd: inventory asset sync skipped for inventory item with id: ', ifnull(OLD.id, 'null') ));
    RESIGNAL;
END;
IF (OLD.id < 100000 AND NOT klusbibdb.is_sync_inventory2le_enabled() ) THEN

    IF klusbibdb.enable_sync_le2inventory() THEN
        DELETE FROM inventory.assets WHERE id = OLD.id;
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    ELSE
        call kb_log_msg(concat('Warning: inventory asset delete failed - ongoing inventory to api sync upon inventory_item delete for id: ', OLD.id));
        signal sqlstate '45000' set message_text = 'Unable to delete inventory asset: sync (inventory -> api) ongoing (check @sync_inventory2le value if this is an error).';
    END IF;
END IF;
END
";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER klusbibdb.`loan_row_bi` BEFORE INSERT ON klusbibdb.`loan_row` FOR EACH ROW
BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in loan_row_bi: inventory asset sync skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
    RESIGNAL;
END;
IF klusbibdb.enable_sync_le2inventory() THEN
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
        signal sqlstate '45000' set message_text = 'Unable to insert loan row: inventory asset missing.';
    END IF;
    SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;

END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`loan_row_bu` BEFORE UPDATE ON klusbibdb.`loan_row` FOR EACH ROW
BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in loan_row_bu: inventory asset sync skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
    RESIGNAL;
END;
IF klusbibdb.enable_sync_le2inventory() THEN
    IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) THEN
        IF (EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE' OR STATUS = 'PENDING') ) 
        AND OLD.checked_out_at IS NULL AND NOT NEW.checked_out_at IS NULL
        AND NEW.checked_in_at IS NULL) THEN
        
            call kb_log_msg(concat('Info: Triggering inventory checkout upon loan_row update for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkout`(NEW.inventory_item_id, 
                (SELECT contact_id FROM loan WHERE id = NEW.loan_id), 
                NEW.checked_out_at, NEW.due_in_at, 'Checkout from lend engine');
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
        call kb_log_msg(concat('Error: inventory asset missing upon loan_row update for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
        signal sqlstate '45000' set message_text = 'Unable to update loan row: inventory asset is missing and required for inventory checkout.';
    END IF;
    SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
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
        Capsule::schema()->dropIfExists('klusbibdb.kb_log');
        if (Capsule::schema()->hasTable('inventory.assets')) {
            $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ai`');
            $this->query('DROP TRIGGER IF EXISTS inventory.`assets_au`');
            $this->query('DROP TRIGGER IF EXISTS inventory.`assets_ad`');
        }
        if (Capsule::schema()->hasTable('klusbibdb.kb_sync_assets')) {
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bi`');
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bu`');
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bd`');
            Capsule::schema()->drop('kb_sync_assets');
        }
        if (Capsule::schema()->hasTable('klusbibdb.inventory_item')) {
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bi`');
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bu`');
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bd`');
        }
        if (Capsule::schema()->hasTable('klusbibdb.loan_row')) {
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bi`');
            $this->query('DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bu`');
        }
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.enable_sync_le2inventory');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.disable_sync_le2inventory');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.enable_sync_inventory2le');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.disable_sync_inventory2le');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.is_sync_le2inventory_enabled');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.is_sync_inventory2le_enabled');
        $this->query('DROP FUNCTION IF EXISTS klusbibdb.is_on_loan');
        $this->query('DROP FUNCTION IF EXISTS inventory.is_on_loan');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.kb_checkout');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.kb_checkin');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.kb_extend');
        $this->query('DROP PROCEDURE IF EXISTS inventory.kb_checkout');
        $this->query('DROP PROCEDURE IF EXISTS inventory.kb_checkin');
        $this->query('DROP PROCEDURE IF EXISTS inventory.kb_extend');
        $this->query('DROP PROCEDURE IF EXISTS inventory.kb_register_loan_no_sync');
        $this->query('DROP PROCEDURE IF EXISTS inventory.kb_sync_assets_2le');
    }

    private function multiQueryOnPDO($sql)
    {
      $db = Capsule::connection()->getPdo();
  
      // works regardless of statements emulation
      $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
      $db->exec($sql);    
    }    
}