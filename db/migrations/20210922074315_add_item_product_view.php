<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AddItemProductView extends AbstractCapsuleMigration
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

        $this->query('DROP VIEW IF EXISTS `product_tag`');
        $this->query('DROP VIEW IF EXISTS `inventory_item_product_tag`');
        $sql = "CREATE VIEW product_tag AS SELECT * FROM lendengine.product_tag";
        $db->exec($sql);
        $sql = "CREATE VIEW inventory_item_product_tag AS SELECT * FROM lendengine.inventory_item_product_tag";
        $db->exec($sql);
        $sql = "CREATE VIEW product_field AS SELECT * FROM lendengine.product_field";
        $db->exec($sql);
        $sql = "CREATE VIEW product_field_select_option AS SELECT * FROM lendengine.product_field_select_option";
        $db->exec($sql);
        $sql = "CREATE VIEW product_field_value AS SELECT * FROM lendengine.product_field_value";
        $db->exec($sql);
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->query('DROP VIEW IF EXISTS `product_tag`');
        $this->query('DROP VIEW IF EXISTS `inventory_item_product_tag`');
        $this->query('DROP VIEW IF EXISTS `product_field`');
        $this->query('DROP VIEW IF EXISTS `product_field_select_option`');
        $this->query('DROP VIEW IF EXISTS `product_field_value`');
	}
}