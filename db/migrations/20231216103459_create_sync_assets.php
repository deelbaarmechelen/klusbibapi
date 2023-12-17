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
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bu`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bd`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bi`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bu`');
        $this->query('DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bd`');

        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkout`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkin`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_extend`');

        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        Capsule::schema()->dropIfExists('kb_log');
          $sql = "
          CREATE TABLE  kb_log (
            id int(11) NOT NULL auto_increment,
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
        $sql = " 
CREATE TRIGGER klusbibdb.`kb_sync_assets_bi` BEFORE INSERT ON klusbibdb.`kb_sync_assets` FOR EACH ROW
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
    IF EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = OLD.id) THEN
        IF NOT OLD.name <=> NEW.name THEN
            UPDATE klusbibdb.`inventory_item`
            SET name = NEW.name,
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
        NEW.`id`, NEW.`assigned_to`, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
        NEW.`name`, NEW.`asset_tag`, 'loan';
    END IF;

    IF (NOT NEW.model_id <=> OLD.model_id) THEN
        call kb_log_msg(concat('Warning: kb_sync_assets model_id update not reported to inventory_item: ', OLD.model_id, ' -> ', NEW.model_id));
    END IF;

    IF (NOT NEW.image <=> OLD.image) THEN
        call kb_log_msg(concat('Warning: kb_sync_assets image update not reported to inventory_item: ', OLD.image, ' -> ', NEW.image));
    END IF;

    IF (NOT NEW.status_id <=> OLD.status_id) THEN
        call kb_log_msg(concat('Warning: kb_sync_assets status_id update not reported to inventory_item: ', OLD.status_id, ' -> ', NEW.status_id));
    END IF;

    IF (NOT NEW.last_checkout <=> OLD.last_checkout
        AND NOT NEW.last_checkout IS NULL
        AND NOT NEW.assigned_to IS NULL
        AND NEW.assigned_type = 'App\Models\User')  THEN
        CALL kb_checkout (NEW.id, NEW.assigned_to, NEW.last_checkout, NEW.expected_checkin, 'Checkout from inventory' );
    END IF;

    IF (NOT NEW.last_checkin <=> OLD.last_checkin) THEN
        IF NOT NEW.last_checkin IS NULL
        AND NEW.assigned_to IS NULL) THEN
        CALL kb_checkin (NEW.id, NEW.last_checkin, 'Checkin from inventory' );
        ELSE
            call kb_log_msg(concat('Warning: kb_sync_assets last_checkin (assinged to ', NEW.assigned_to, ') update not reported to inventory_item: ', OLD.last_checkin, ' -> ', NEW.last_checkin));
        END IF;
    END IF;

    IF (NOT NEW.expected_checkin <=> OLD.expected_checkin) THEN
        CALL kb_extend (NEW.id, NEW.expected_checkin);
    END IF;

    IF (NOT NEW.assigned_to <=> OLD.assigned_to) THEN
        call kb_log_msg(concat('Warning: kb_sync_assets assigned_to update not reported to inventory_item: ', OLD.assigned_to, ' -> ', NEW.assigned_to));
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

    IF NOT EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
    INSERT INTO inventory.assets  (
    id, name, asset_tag, model_id, created_at, updated_at)
    SELECT 
    NEW.`id`, NEW.name, NEW.sku, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
    ELSE
        call kb_log_msg(concat('Warning: inventory asset already exists - inventory_item insert not reported to inventory.assets for id: ', NEW.id));
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

    IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
    IF NOT OLD.name <=> NEW.name THEN
        UPDATE inventory.assets 
        SET name = NEW.name,
        updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
        WHERE id = OLD.id;
    END IF;
    IF NOT OLD.sku <=> NEW.sku THEN
        UPDATE inventory.assets 
        SET asset_tag = NEW.sku,
        updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
        WHERE id = OLD.id;
    END IF;
    ELSE
    call kb_log_msg(concat('Warning: inventory asset missing - created on the fly upon inventory_item update for id: ', NEW.id));
    INSERT INTO inventory.assets  (
        id, name, asset_tag, model_id, created_at, updated_at)
        SELECT 
        NEW.`id`, NEW.name, NEW.sku, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
    END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`inventory_item_bd` BEFORE DELETE ON klusbibdb.`inventory_item` FOR EACH ROW
BEGIN
    DELETE FROM inventory.assets WHERE id = OLD.id;
END
";
        $db->exec($sql);

        $sql = "
CREATE PROCEDURE klusbibdb.`kb_checkout` 
        (IN inventory_item_id INT, IN loan_contact_id INT, IN datetime_out DATETIME, IN datetime_in DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE new_loan_id INT DEFAULT 0;
 IF EXISTS (SELECT 1 FROM inventory_item WHERE id = inventory_item_id) 
  AND EXISTS (SELECT 1 FROM contact WHERE id = loan_contact_id) THEN
         
    INSERT INTO loan (
        contact_id, datetime_out, datetime_in, status, total_fee, created_at)
    SELECT
    loan_contact_id, ifnull(datetime_out, CURRENT_TIMESTAMP), ifnull(datetime_in, CURRENT_TIMESTAMP), 'ACTIVE', 0, CURRENT_TIMESTAMP;
    SET new_loan_id = LAST_INSERT_ID();
    
    INSERT INTO loan_row (
        loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
        SELECT new_loan_id, inventory_item_id, 1, datetime_out, ifnull(datetime_in, ifnull(datetime_out, CURRENT_TIMESTAMP)), 
        datetime_out, null, 0, 1, 1;

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
  IF EXISTS (SELECT 1 FROM inventory_item WHERE id = item_id) 
    AND EXISTS (SELECT 1 FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status = 'ACTIVE') THEN
    
    SELECT loan_id INTO existing_loan_id FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status = 'ACTIVE';
    SELECT contact_id INTO loan_contact_id FROM loan WHERE id = existing_loan_id;

    IF EXISTS (SELECT 1 FROM loan_row WHERE inventory_item_id = item_id AND loan_id = existing_loan_id) THEN
    
        UPDATE loan_row SET checked_in_at = checkin_datetime
        WHERE loan_id = existing_loan_id AND inventory_item_id = item_id;

        IF NOT EXISTS (SELECT 1 FROM loan_row WHERE loan_id = exisiting_loan_id AND datetime_in IS NULL) THEN
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
    AND EXISTS (SELECT 1 FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status = 'ACTIVE') THEN
    
    SELECT loan_id INTO existing_loan_id FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status = 'ACTIVE';

    UPDATE loan_row SET due_in_at = expected_checkin_datetime
     WHERE loan_id = existing_loan_id AND inventory_item_id = item_id;
        
  ELSE
    call kb_log_msg(concat('Warning: inventory_item or loan missing in kb_extend - loan_row update skipped for inventory item with id: ', item_id));
  END IF;
END
";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER klusbibdb.`loan_row_bi` BEFORE INSERT ON klusbibdb.`loan_row` FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) 
    AND EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE') ) 
    AND NOT NEW.checked_out_at IS NULL
    AND NEW.checked_in_at IS NULL THEN
    
    UPDATE inventory.assets
      SET last_checkout = NEW.checked_out_at,
          expected_checkin = NEW.due_in_at
      WHERE id = NEW.inventory_item_id;
  ELSE
    call kb_log_msg(concat('Warning: inventory asset or loan missing upon loan_row insert - inventory asset update skipped for inventory item with id: ', NEW.inventory_item_id));
  END IF;
END
";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER klusbibdb.`loan_row_bu` BEFORE UPDATE ON klusbibdb.`loan_row` FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) 
    AND EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE') ) 
    AND NOT NEW.checked_out_at IS NULL
    AND NEW.checked_in_at IS NULL THEN
    
    UPDATE inventory.assets
      SET last_checkout = NEW.checked_out_at,
          expected_checkin = NEW.due_in_at
      WHERE id = NEW.inventory_item_id;
    ELSE
      call kb_log_msg(concat('Warning: inventory asset or loan missing upon loan_row update - inventory asset last_checkout update skipped for inventory item with id: ', NEW.inventory_item_id));
    END IF;
  IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) 
    AND NOT NEW.checked_in_at IS NULL THEN
    
    UPDATE inventory.assets
      SET last_checkin = NEW.checked_in_at
      WHERE id = NEW.inventory_item_id;
  ELSE
      call kb_log_msg(concat('Warning: inventory asset or loan missing upon loan_row update - inventory asset last_checkin update skipped for inventory item with id: ', NEW.inventory_item_id));
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

        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkout`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkin`');
        $this->query('DROP PROCEDURE IF EXISTS klusbibdb.`kb_extend`');
    }
}