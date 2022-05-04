<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddLoanViews extends AbstractCapsuleMigration
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

        //note: to avoid conflicts with autoincrement id, increase it to a safe value on lend engine
        //      e.g. ALTER TABLE loan AUTO_INCREMENT=100001;
        $this->query('DROP VIEW IF EXISTS `loan`');
        $this->query('DROP VIEW IF EXISTS `loan_row`');
        $sql = "CREATE VIEW loan AS SELECT * FROM lendengine.loan";
        $db->exec($sql);
        $sql = "CREATE VIEW loan_row AS SELECT * FROM lendengine.loan_row";
        $db->exec($sql);
        $sql = "CREATE VIEW item_movement AS SELECT * FROM lendengine.item_movement";
        $db->exec($sql);
        $sql = "CREATE VIEW note AS SELECT * FROM lendengine.note";
        $db->exec($sql);
        $sql = "CREATE VIEW contact AS SELECT * FROM lendengine.contact";
        $db->exec($sql);
    }
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
    public function down()
    {
        $this->query('DROP VIEW IF EXISTS `loan`');
        $this->query('DROP VIEW IF EXISTS `loan_row`');
        $this->query('DROP VIEW IF EXISTS `item_movement`');
        $this->query('DROP VIEW IF EXISTS `note`');
        $this->query('DROP VIEW IF EXISTS `contact`');
    }

}