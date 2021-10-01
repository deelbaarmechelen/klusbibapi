<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddLendingTriggers extends AbstractCapsuleMigration
{
    const STATUS_PENDING  = 'PENDING';
    const STATUS_RESERVED = 'RESERVED';
    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_CLOSED   = 'CLOSED';
    const STATUS_OVERDUE  = 'OVERDUE';
    const STATUS_CANCELLED  = 'CANCELLED';

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

        $this->query('DROP TRIGGER IF EXISTS `le_lending_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_lending_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_lending_ad`');
        $sql = " 
CREATE TRIGGER `le_lending_ai` AFTER INSERT ON `lendings`
 FOR EACH ROW 
BEGIN
INSERT INTO loan (
        id, datetime_out, datetime_in, status, total_fee, reference, created_at)
SELECT
NEW.`lending_id`, NEW.`start_date`, NEW.`due_date`, 
CASE WHEN (NEW.`returned_date` IS NULL) THEN 'ACTIVE' ELSE 'CLOSED' END,
0, NULL, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);

INSERT INTO loan_row (
        id, loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
SELECT
NEW.`lending_id`, NEW.`lending_id`, NEW.`tool_id`, 1, NEW.`start_date`, NEW.`due_date`, NEW.`start_date`, NEW.`returned_date`,0, 1, 1;
END";
        $db->exec($sql);
        $sql = " 
CREATE TRIGGER `le_lending_au` AFTER UPDATE ON `lendings`
 FOR EACH ROW 
BEGIN
update loan_row 
set inventory_item_id = NEW.`tool_id`, 
    due_out_at = NEW.`start_date`, 
    due_in_at = NEW.`due_date`, 
    checked_out_at = NEW.`start_date`, 
    checked_in_at = NEW.`returned_date` 
where id = NEW.lending_id;
update loan 
set datetime_out = NEW.`start_date`, 
    datetime_in = NEW.`due_date`, 
    status = CASE WHEN (NEW.`returned_date` IS NULL) THEN 'ACTIVE' ELSE 'CLOSED' END
where id = NEW.lending_id;
END";
        $db->exec($sql);
        $sql = " 
CREATE TRIGGER `le_lending_ad` AFTER DELETE ON `lendings`
 FOR EACH ROW 
BEGIN
delete from loan_row where id = OLD.lending_id;
delete from loan where id = OLD.lending_id;
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
        $this->query('DROP TRIGGER IF EXISTS `le_lending_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_lending_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_lending_ad`');
    }
}