<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddReservationTriggers extends AbstractCapsuleMigration
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

        $this->query('DROP TRIGGER IF EXISTS `le_reservation_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_ad`');
        $sql = "
CREATE TRIGGER `le_reservation_ai` AFTER INSERT ON `reservations` FOR EACH ROW 
BEGIN 
IF EXISTS (SELECT 1 FROM lendengine.inventory_item WHERE id = NEW.`tool_id`) 
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
CREATE TRIGGER `le_reservation_au` AFTER UPDATE ON `reservations` FOR EACH ROW 
BEGIN 
IF EXISTS (SELECT 1 FROM lendengine.inventory_item WHERE id = NEW.`tool_id`) 
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
CREATE TRIGGER `le_reservation_ad` AFTER DELETE ON `reservations` FOR EACH ROW 
BEGIN
 delete from note where loan_id = OLD.reservation_id; 
 delete from loan_row where id = OLD.reservation_id AND loan_row.checked_out_at IS NULL; 
 delete from loan where id = OLD.reservation_id AND NOT EXISTS (SELECT 1 FROM loan_row where loan_row.loan_id = loan.id); 
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
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_reservation_ad`');
	}
}