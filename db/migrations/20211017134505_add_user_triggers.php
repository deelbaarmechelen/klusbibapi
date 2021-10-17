<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddUserTriggers extends AbstractCapsuleMigration
{
    const DEFAULT_PASSWORD = '$2y$13$JJRAiAUQgjIg1bkskpf6fuyFaGvW4DrVKXnqZ/iPjqZTHxzGbZ3Xe';
    const ADMIN_ROLE = 'a:2:{i:0;s:10:"ROLE_ADMIN";i:1;s:15:"ROLE_SUPER_USER";}';
    const MEMBER_ROLE = 'a:0:{}';
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
	    // TODO: add contact_field_values ??
        $this->initCapsule();
        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        $this->query('DROP VIEW IF EXISTS `contact_field`');
        $this->query('DROP VIEW IF EXISTS `contact_field_select_option`');
        $this->query('DROP VIEW IF EXISTS `contact_field_value`');
        $sql = "CREATE VIEW contact_field AS SELECT * FROM lendengine.contact_field";
        $db->exec($sql);
        $sql = "CREATE VIEW contact_field_select_option AS SELECT * FROM lendengine.contact_field_select_option";
        $db->exec($sql);
        $sql = "CREATE VIEW contact_field_value AS SELECT * FROM lendengine.contact_field_value";
        $db->exec($sql);

        $this->query('DROP TRIGGER IF EXISTS `le_user_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_user_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_user_ad`');
        $sql = "
CREATE TRIGGER `le_user_ai` AFTER INSERT ON `users` FOR EACH ROW 
BEGIN 
declare msg varchar(128);
IF NOT EXISTS (SELECT 1 FROM `contact` WHERE `contact`.id = NEW.user_id) THEN
    INSERT INTO `contact` 
    (`id`, `active_membership`, `enabled`, `password`, 
     `last_login`, `roles`, 
     `first_name`, `last_name`, `telephone`, 
     `address_line_1`, `address_line_2`, `address_line_3`, `address_line_4`, 
     `country_iso_code`,`created_at`, `balance`, `subscriber`,
     `email`, `email_canonical`, `username`, `username_canonical`, 
     `locale`, `is_active`)
    SELECT NEW.user_id, null, 1, ifnull(NEW.`hash`, '" . self::DEFAULT_PASSWORD . "'), 
    NEW.last_login,  IF(NEW.role = 'admin', '" . self::ADMIN_ROLE . "', '" . self::MEMBER_ROLE . "'), 
    NEW.firstname, NEW.lastname, NEW.phone, 
    NEW.address, NEW.city, null, NEW.postal_code,
    'BE', NEW.created_at, '0.00', 0,
    NEW.email, NEW.email, NEW.email, NEW.email,
    'nl', 1;
ELSE
    set msg = concat('le_user_ai trigger: Trying to insert an already existing contact with ID: ', cast(NEW.user_id as char));
    signal sqlstate '45000' set message_text = msg;
END IF;
END";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER `le_user_au` AFTER UPDATE ON `users` FOR EACH ROW 
BEGIN 
IF NOT EXISTS (SELECT 1 FROM `contact` WHERE `contact`.id = NEW.user_id) THEN
    INSERT INTO `contact` 
    (`id`, `active_membership`, `enabled`, `password`, 
     `last_login`, `roles`, 
     `first_name`, `last_name`, `telephone`, 
     `address_line_1`, `address_line_2`, `address_line_3`, `address_line_4`, 
     `country_iso_code`,`created_at`, `balance`, `subscriber`,
     `email`, `email_canonical`, `username`, `username_canonical`, 
     `locale`, `is_active`)
    SELECT NEW.user_id, null, 1, ifnull(NEW.`hash`, '" . self::DEFAULT_PASSWORD . "'), 
    NEW.last_login,  IF(NEW.role = 'admin', '" . self::ADMIN_ROLE . "', '" . self::MEMBER_ROLE . "'), 
    NEW.firstname, NEW.lastname, NEW.phone, 
    NEW.address, NEW.city, null, NEW.postal_code,
    'BE', NEW.created_at, '0.00', 0,
    NEW.email, NEW.email, NEW.email, NEW.email,
    'nl', 1;
ELSE
    UPDATE `contact`
    SET `password` = ifnull(NEW.`hash`, '" . self::DEFAULT_PASSWORD . "'), 
     `last_login` = NEW.last_login,
     `roles` = IF(NEW.role = 'admin', '" . self::ADMIN_ROLE . "', '" . self::MEMBER_ROLE . "'), 
     `first_name` = NEW.firstname, 
     `last_name` = NEW.lastname, 
     `telephone` = NEW.phone, 
     `address_line_1` = NEW.address, 
     `address_line_2` = NEW.city, 
     `address_line_4` = NEW.postal_code, 
     `email` = NEW.email, 
     `email_canonical` = NEW.email, 
     `username` = NEW.email, 
     `username_canonical` = NEW.email 
    WHERE `contact`.id = NEW.user_id;
END IF;
END";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER `le_user_ad` AFTER DELETE ON `users` FOR EACH ROW 
BEGIN
    declare msg varchar(128);
    IF (SELECT balance from contact WHERE id = OLD.user_id) > 0 THEN
        set msg = concat('le_user_ad trigger: Trying to delete a contact with positive balance - ID: ', cast(OLD.user_id as char));
        signal sqlstate '45000' set message_text = msg;
    ELSE
        update lendengine.attendee set contact_id = null where contact_id = OLD.user_id;
        update lendengine.attendee set created_by = null where created_by = OLD.user_id;
        update lendengine.child set contact_id = null where contact_id = OLD.user_id;
        update lendengine.deposit set contact_id = null where contact_id = OLD.user_id;
        update lendengine.deposit set created_by = null where created_by = OLD.user_id;
        update lendengine.loan set contact_id = null where contact_id = OLD.user_id;
        update lendengine.loan set created_by = null where created_by = OLD.user_id;
        update lendengine.membership set contact_id = null where contact_id = OLD.user_id;
        update lendengine.membership set created_by = null where created_by = OLD.user_id;
        update lendengine.note set contact_id = null where contact_id = OLD.user_id;
        update lendengine.payment set contact_id = null where contact_id = OLD.user_id;
        update lendengine.payment set created_by = null where created_by = OLD.user_id;
        update lendengine.page set created_by = null where created_by = OLD.user_id;
        update lendengine.page set updated_by = null where updated_by = OLD.user_id;
        update lendengine.maintenance set completed_by = null where completed_by = OLD.user_id;
        update lendengine.item_movement set created_by = null where created_by = OLD.user_id;
        update lendengine.inventory_item set created_by = null where created_by = OLD.user_id;
        update lendengine.event set created_by = null where created_by = OLD.user_id;
        
        delete from lendengine.waiting_list_item where contact_id = OLD.user_id;
        delete from lendengine.file_attachment where contact_id = OLD.user_id;
        delete from contact_field_value where contact_id = OLD.user_id;
        delete from contact where id = OLD.user_id; 
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
        $this->query('DROP TRIGGER IF EXISTS `le_user_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_user_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_user_ad`');
        $this->query('DROP VIEW IF EXISTS `contact_field`');
        $this->query('DROP VIEW IF EXISTS `contact_field_select_option`');
        $this->query('DROP VIEW IF EXISTS `contact_field_value`');
    }
}