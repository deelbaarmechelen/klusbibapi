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
class UpdateTriggers extends AbstractCapsuleMigration
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

        updateLendingTriggers($db);
        updateReservationTriggers($db);
        updateContactTriggers($db);
        updateMembershipTriggers($db);
    }

    public function updateContactTriggers($db) {
        $this->query('DROP TRIGGER IF EXISTS `le_user_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_user_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_user_ad`');
        $this->query('DROP TRIGGER IF EXISTS `kb_contact_bd`');

        $sql = "
CREATE TRIGGER `kb_contact_bd` BEFORE DELETE ON `contact` FOR EACH ROW 
BEGIN
    declare msg varchar(128);
    IF (SELECT balance from contact WHERE id = OLD.user_id) > 0 THEN
        set msg = concat('kb_contact_ad trigger: Trying to delete a contact with positive balance - ID: ', cast(OLD.user_id as char));
        signal sqlstate '45000' set message_text = msg;
    ELSE
        update attendee set contact_id = null where contact_id = OLD.user_id;
        update attendee set created_by = null where created_by = OLD.user_id;
        update child set contact_id = null where contact_id = OLD.user_id;
        update deposit set contact_id = null where contact_id = OLD.user_id;
        update deposit set created_by = null where created_by = OLD.user_id;
        update loan set contact_id = null where contact_id = OLD.user_id;
        update loan set created_by = null where created_by = OLD.user_id;
        update membership set contact_id = null where contact_id = OLD.user_id;
        update membership set created_by = null where created_by = OLD.user_id;
        update note set contact_id = null where contact_id = OLD.user_id;
        update payment set contact_id = null where contact_id = OLD.user_id;
        update payment set created_by = null where created_by = OLD.user_id;
        update page set created_by = null where created_by = OLD.user_id;
        update page set updated_by = null where updated_by = OLD.user_id;
        update maintenance set completed_by = null where completed_by = OLD.user_id;
        update item_movement set created_by = null where created_by = OLD.user_id;
        update inventory_item set created_by = null where created_by = OLD.user_id;
        update event set created_by = null where created_by = OLD.user_id;
        
        delete from waiting_list_item where contact_id = OLD.user_id;
        delete from file_attachment where contact_id = OLD.user_id;
        delete from contact_field_value where contact_id = OLD.user_id;
    END IF;

END
";
        $db->exec($sql);
    }

    public function updateMembershipTriggers($db)
    {
        $this->query('DROP TRIGGER IF EXISTS `le_membership_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_membership_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_membership_ad`');
        $this->query('DROP TRIGGER IF EXISTS `kb_membership_ai`');
        $this->query('DROP TRIGGER IF EXISTS `kb_membership_au`');
        $this->query('DROP TRIGGER IF EXISTS `kb_membership_ad`');

        $sql = "
CREATE TRIGGER `kb_membership_ai` AFTER INSERT ON `membership` FOR EACH ROW 
BEGIN
IF EXISTS (SELECT 1 FROM `contact` WHERE id = NEW.contact_id) THEN
     IF NEW.status = 'ACTIVE' THEN
        UPDATE `contact` c
        SET c.`active_membership` = NEW.id
        WHERE id = NEW.contact_id;
    END IF;
 END IF;
END";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER `kb_membership_au` AFTER UPDATE ON `membership` FOR EACH ROW 
BEGIN
 IF EXISTS (SELECT 1 FROM `contact` WHERE id = NEW.contact_id) THEN
    IF NEW.status = 'ACTIVE' THEN
        UPDATE `contact` c
        SET c.`active_membership` = NEW.id
        WHERE id = NEW.contact_id;
    END IF;
 END IF;
END";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER `kb_membership_ad` AFTER DELETE ON `membership` FOR EACH ROW 
 BEGIN
    UPDATE `contact` c
    SET c.`active_membership` = NULL
    WHERE id = OLD.contact_id;
 END
";
        $db->exec($sql);
    }

    public function updateReservationTriggers($db)
	{
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_ad`');
        $this->query('DROP TRIGGER IF EXISTS `kb_reservation_ai`');
        $this->query('DROP TRIGGER IF EXISTS `kb_reservation_au`');
        $this->query('DROP TRIGGER IF EXISTS `kb_reservation_ad`');
        $sql = "
CREATE TRIGGER `kb_reservation_ai` AFTER INSERT ON `kb_reservations` FOR EACH ROW 
BEGIN 
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = NEW.`tool_id`) 
 AND EXISTS (SELECT 1 FROM contact WHERE id = NEW.`user_id`)
 AND NOT NEW.`state` = 'DELETED' THEN
 
  INSERT INTO loan (
        id, contact_id, datetime_out, datetime_in, status, total_fee, reference, created_at)
  SELECT
  NEW.`reservation_id`, NEW.`user_id`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP), ifnull(NEW.`endsAt`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP)), 
  CASE
    WHEN NEW.`state` = 'REQUESTED' THEN 'PENDING'
    WHEN NEW.`state` = 'CONFIRMED' THEN 'RESERVED'
    WHEN NEW.`state` = 'CANCELLED' THEN 'CANCELLED'
    WHEN NEW.`state` = 'CLOSED' THEN 'CLOSED'
    ELSE 'PENDING'
  END
  , 0, NULL, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);

  INSERT INTO loan_row (
        id, loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
  SELECT NEW.`reservation_id`, NEW.`reservation_id`, NEW.`tool_id`, 1, NEW.`startsAt`, ifnull(NEW.`endsAt`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP)), 
         null, null, 0, 1, 1;

  IF NOT NEW.`comment` IS NULL THEN
    INSERT INTO note (contact_id, loan_id, inventory_item_id, text, admin_only, created_at)
    SELECT NEW.`user_id`, NEW.`reservation_id`, NEW.`tool_id`, NEW.`comment`, 1, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);
  END IF;  
END IF;
END
";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER `kb_reservation_au` AFTER UPDATE ON `kb_reservations` FOR EACH ROW 
BEGIN 
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = NEW.`tool_id`) 
 AND EXISTS (SELECT 1 FROM contact WHERE id = NEW.`user_id`) THEN
    IF NEW.`state` = 'DELETED' THEN
        delete from note where loan_id = OLD.reservation_id;
        delete from loan_row where id = OLD.reservation_id AND checked_out_at IS NULL;
        delete from loan where id = OLD.reservation_id AND NOT EXISTS (SELECT 1 FROM loan_row where loan_row.loan_id = loan.id);
    ELSE
        IF EXISTS (SELECT 1 FROM loan WHERE id = NEW.reservation_id) THEN
            UPDATE loan 
                SET status = 
                CASE
                    WHEN NEW.`state` = 'REQUESTED' THEN 'PENDING'
                    WHEN NEW.`state` = 'CONFIRMED' THEN 'RESERVED'
                    WHEN NEW.`state` = 'CANCELLED' THEN 'CANCELLED'
                    WHEN NEW.`state` = 'CLOSED' THEN 'CLOSED'
                    ELSE 'PENDING'
                END,
                datetime_out = ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP),
                datetime_in = ifnull(NEW.`endsAt`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP))
            WHERE id = NEW.reservation_id; 
            UPDATE loan_row 
                SET due_out_at = ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP),
                    due_in_at = ifnull(NEW.`endsAt`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP))
            WHERE id = NEW.reservation_id; 
        ELSE
            INSERT INTO loan (
            id, contact_id, datetime_out, datetime_in, status, total_fee, reference, created_at)
            SELECT
              NEW.`reservation_id`, NEW.`user_id`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP), ifnull(NEW.`endsAt`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP)), 
                CASE
                    WHEN NEW.`state` = 'REQUESTED' THEN 'PENDING'
                    WHEN NEW.`state` = 'CONFIRMED' THEN 'RESERVED'
                    WHEN NEW.`state` = 'CANCELLED' THEN 'CANCELLED'
                    WHEN NEW.`state` = 'CLOSED' THEN 'CLOSED'
                    ELSE 'PENDING'
                END,
               0, NULL, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);
            
            INSERT INTO loan_row (
                id, loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
            SELECT NEW.`reservation_id`, NEW.`reservation_id`, NEW.`tool_id`, 1, NEW.`startsAt`, ifnull(NEW.`endsAt`, ifnull(NEW.`startsAt`, CURRENT_TIMESTAMP)), 
                 null, null, 0, 1, 1;
            
        END IF;
        IF NOT NEW.`comment` IS NULL AND OLD.`comment` <> NEW.`comment` THEN
            INSERT INTO note (contact_id, loan_id, inventory_item_id, text, admin_only, created_at)
            SELECT NEW.`user_id`, NEW.`reservation_id`, NEW.`tool_id`, NEW.`comment`, 1, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);
        END IF;  
        
    END IF;
END IF;
END
";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER `kb_reservation_ad` AFTER DELETE ON `kb_reservations` FOR EACH ROW 
BEGIN
 delete from note where loan_id = OLD.reservation_id; 
 delete from loan_row where id = OLD.reservation_id AND loan_row.checked_out_at IS NULL; 
 delete from loan where id = OLD.reservation_id AND NOT EXISTS (SELECT 1 FROM loan_row where loan_row.loan_id = loan.id); 
END
";
        $db->exec($sql);
    }
    private function updateLendingTriggers($db) {

        $this->query('DROP TRIGGER IF EXISTS `le_lending_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_lending_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_lending_ad`');
        $this->query('DROP TRIGGER IF EXISTS `kb_lending_ai`');
        $this->query('DROP TRIGGER IF EXISTS `kb_lending_au`');
        $this->query('DROP TRIGGER IF EXISTS `kb_lending_ad`');
        $sql = " 
 CREATE TRIGGER `kb_lending_ai` AFTER INSERT ON `kb_lendings`
  FOR EACH ROW 
 BEGIN
 IF EXISTS (SELECT 1 FROM inventory_item WHERE id = NEW.`tool_id`) 
 AND EXISTS (SELECT 1 FROM contact WHERE id = NEW.`user_id`) THEN
 
  INSERT INTO loan (
        id, contact_id, datetime_out, datetime_in, status, total_fee, reference, created_at)
  SELECT
  NEW.`lending_id`, NEW.`user_id`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP), ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
  CASE WHEN (NEW.`returned_date` IS NULL) THEN 'ACTIVE' ELSE 'CLOSED' END,
  0, NULL, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);

  INSERT INTO loan_row (
        id, loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
  SELECT NEW.`lending_id`, NEW.`lending_id`, NEW.`tool_id`, 1, NEW.`start_date`, ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
         NEW.`start_date`, NEW.`returned_date`, 0, 1, 1;

  IF NEW.`start_date` IS NOT NULL THEN
      INSERT INTO item_movement (
            inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
      SELECT NEW.`tool_id`, 1, NEW.`lending_id`, NEW.`user_id`, CURRENT_TIMESTAMP, 1;
  END IF;
  IF NOT NEW.`returned_date` IS NULL THEN
    INSERT INTO item_movement (
        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
    SELECT NEW.`tool_id`, 2, null, null, CURRENT_TIMESTAMP, 1;
  END IF;
  IF NOT NEW.`comments` IS NULL THEN
    INSERT INTO note (contact_id, loan_id, inventory_item_id, text, admin_only, created_at)
    SELECT NEW.`user_id`, NEW.`lending_id`, NEW.`tool_id`, NEW.`comments`, 1, CURRENT_TIMESTAMP;
  END IF;  
 END IF;
 END";
        $db->exec($sql);
        $sql = " 
 CREATE TRIGGER `kb_lending_au` AFTER UPDATE ON `kb_lendings`
  FOR EACH ROW 
 BEGIN
 IF EXISTS (SELECT 1 FROM inventory_item WHERE id = NEW.`tool_id`) 
  AND EXISTS (SELECT 1 FROM contact WHERE id = NEW.`user_id`) THEN
    IF EXISTS (SELECT 1 FROM loan WHERE id = NEW.`lending_id`) THEN
      update loan_row 
      set inventory_item_id = NEW.`tool_id`,
        due_out_at = ifnull(NEW.`start_date`, CURRENT_TIMESTAMP), 
        due_in_at = ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
        checked_out_at = NEW.`start_date`, 
        checked_in_at = NEW.`returned_date` 
      where id = NEW.lending_id;
      update loan 
      set contact_id =  NEW.`user_id`, 
        datetime_out = ifnull(NEW.`start_date`, CURRENT_TIMESTAMP), 
        datetime_in = ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
        status = CASE WHEN (NEW.`returned_date` IS NULL) THEN 'ACTIVE' ELSE 'CLOSED' END
      where id = NEW.lending_id;
    ELSE
      INSERT INTO loan (
            id, contact_id, datetime_out, datetime_in, status, total_fee, reference, created_at)
      SELECT
      NEW.`lending_id`, NEW.`user_id`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP), ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
      CASE WHEN (NEW.`returned_date` IS NULL) THEN 'ACTIVE' ELSE 'CLOSED' END,
      0, NULL, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);
    
      INSERT INTO loan_row (
            id, loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
      SELECT NEW.`lending_id`, NEW.`lending_id`, NEW.`tool_id`, 1, NEW.`start_date`, ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
             NEW.`start_date`, NEW.`returned_date`, 0, 1, 1;     
    END IF;
  
    IF OLD.`start_date` IS NULL 
    AND NOT EXISTS (SELECT 1 FROM item_movement WHERE loan_row_id = NEW.`lending_id`) THEN
        INSERT INTO item_movement (
            inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
        SELECT NEW.`tool_id`, 1, NEW.`lending_id`, NEW.`user_id`, CURRENT_TIMESTAMP, 1;
    END IF;
    IF NOT NEW.`returned_date` IS NULL THEN
        INSERT INTO item_movement (
            inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
        SELECT NEW.`tool_id`, 2, null, null, CURRENT_TIMESTAMP, 1;
    END IF;
    IF NOT NEW.`comments` IS NULL AND OLD.`comments` <> NEW.`comments` THEN
        INSERT INTO note (contact_id, loan_id, inventory_item_id, text, admin_only, created_at)
        SELECT NEW.`user_id`, NEW.`lending_id`, NEW.`tool_id`, NEW.`comments`, 1, CURRENT_TIMESTAMP;
    END IF;  
 END IF;
 END";
        $db->exec($sql);
        $sql = " 
 CREATE TRIGGER `kb_lending_ad` AFTER DELETE ON `kb_lendings`
 FOR EACH ROW 
  BEGIN
    delete from item_movement where loan_row_id = OLD.lending_id;
    delete from note where loan_id = OLD.lending_id;
    delete from loan_row where id = OLD.lending_id;
    delete from loan where id = OLD.lending_id AND NOT EXISTS (SELECT 1 FROM loan_row where loan_row.loan_id = loan.id);
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
		//Capsule::schema()->drop('yourTableName');
	}
}