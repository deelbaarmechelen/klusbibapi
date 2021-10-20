<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddMembershipTriggers extends AbstractCapsuleMigration
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

        $this->query('DROP VIEW IF EXISTS `le_membership`');
        $sql = "CREATE VIEW le_membership AS SELECT * FROM lendengine.membership";
        $db->exec($sql);

        $this->query('DROP TRIGGER IF EXISTS `le_membership_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_membership_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_membership_ad`');

        $sql = "
CREATE TRIGGER `le_membership_ai` AFTER INSERT ON `membership` FOR EACH ROW 
BEGIN
IF NOT EXISTS (SELECT 1 FROM `le_membership` WHERE id = NEW.id) 
 AND EXISTS (SELECT 1 FROM `contact` WHERE id = NEW.contact_id) THEN
    INSERT INTO `le_membership` 
    (id, contact_id, created_at, starts_at, expires_at, `status`, subscription_id, price)
    SELECT NEW.id, NEW.contact_id, NEW.created_at, NEW.start_at, NEW.expires_at, NEW.`status`, NEW.subscription_id, '0.00';
    
    IF NEW.status = 'ACTIVE' THEN
        UPDATE `contact` c
        SET c.`active_membership` = 
        (select m.id FROM le_membership m WHERE m.contact_id = c.id AND m.status = 'ACTIVE');
    END IF;
END IF;
END";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER `le_membership_au` AFTER UPDATE ON `membership` FOR EACH ROW 
BEGIN
IF EXISTS (SELECT 1 FROM `contact` WHERE id = NEW.contact_id) THEN

    IF EXISTS (SELECT 1 FROM `le_membership` WHERE id = NEW.id) THEN
        UPDATE `le_membership` 
        SET contact_id = NEW.contact_id,
            starts_at = NEW.start_at,
            expires_at = NEW.expires_at,
            status = NEW.`status`,
            subscription_id = NEW.subscription_id
        WHERE id = NEW.id;
    ELSE 
        INSERT INTO `le_membership` 
        (id, contact_id, created_at, starts_at, expires_at, `status`, subscription_id, price)
        SELECT NEW.id, NEW.contact_id, NEW.created_at, NEW.start_at, NEW.expires_at, NEW.`status`, NEW.subscription_id, '0.00';
    END IF;
    IF NEW.status = 'ACTIVE' THEN
        UPDATE `contact` c
        SET c.`active_membership` = 
        (select m.id FROM le_membership m WHERE m.contact_id = c.id AND m.status = 'ACTIVE');
    END IF;
END IF;

END
";
        $db->exec($sql);

        $sql = "
CREATE TRIGGER `le_membership_ad` AFTER DELETE ON `membership` FOR EACH ROW 
BEGIN
    UPDATE `contact` c
    SET c.`active_membership` = NULL
    WHERE id = OLD.contact_id;
 
    delete from le_membership where id = OLD.id; 
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
        $this->query('DROP TRIGGER IF EXISTS `le_membership_ai`');
        $this->query('DROP TRIGGER IF EXISTS `le_membership_au`');
        $this->query('DROP TRIGGER IF EXISTS `le_membership_ad`');
        $this->query('DROP VIEW IF EXISTS `le_membership`');
    }
}