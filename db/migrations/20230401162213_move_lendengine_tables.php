<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class MoveLendengineTables extends AbstractCapsuleMigration
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
        $this->query('DROP VIEW IF EXISTS contact_field');
        $this->query('DROP VIEW IF EXISTS contact_field_select_option');
        $this->query('DROP VIEW IF EXISTS contact_field_value');
        $this->query('DROP VIEW IF EXISTS inventory_item_product_tag');
        $this->query('DROP VIEW IF EXISTS item_movement');
        $this->query('DROP VIEW IF EXISTS le_membership');
        $this->query('DROP VIEW IF EXISTS loan');
        $this->query('DROP VIEW IF EXISTS loan_row');
        $this->query('DROP VIEW IF EXISTS note');
        $this->query('DROP VIEW IF EXISTS payment');
        $this->query('DROP VIEW IF EXISTS product_field');
        $this->query('DROP VIEW IF EXISTS product_field_select_option');
        $this->query('DROP VIEW IF EXISTS product_field_value');
        $this->query('DROP VIEW IF EXISTS product_tag');

        $this->query('ALTER TABLE lendengine.app RENAME klusbibdb.app');
        $this->query('ALTER TABLE lendengine.app_setting RENAME klusbibdb.app_setting');
        $this->query('ALTER TABLE lendengine.attendee RENAME klusbibdb.attendee');
        $this->query('ALTER TABLE lendengine.check_in_prompt RENAME klusbibdb.check_in_prompt');
        $this->query('ALTER TABLE lendengine.check_out_prompt RENAME klusbibdb.check_out_prompt');
        $this->query('ALTER TABLE lendengine.child RENAME klusbibdb.child');
        $this->query('ALTER TABLE lendengine.contact_field RENAME klusbibdb.contact_field');
        $this->query('ALTER TABLE lendengine.contact_field_select_option rename klusbibdb.contact_field_select_option');
        $this->query('ALTER TABLE lendengine.contact_field_value RENAME klusbibdb.contact_field_value');
        $this->query('ALTER TABLE lendengine.deposit RENAME klusbibdb.deposit');
        $this->query('ALTER TABLE lendengine.event RENAME klusbibdb.event');
        $this->query('ALTER TABLE lendengine.ext_translations RENAME klusbibdb.ext_translations');
        $this->query('ALTER TABLE lendengine.file_attachment RENAME klusbibdb.file_attachment');
        $this->query('ALTER TABLE lendengine.image RENAME klusbibdb.image');
        $this->query('ALTER TABLE lendengine.inventory_item_check_in_prompt RENAME klusbibdb.inventory_item_check_in_prompt');
        $this->query('ALTER TABLE lendengine.inventory_item_check_out_prompt RENAME klusbibdb.inventory_item_check_out_prompt');
        $this->query('ALTER TABLE lendengine.inventory_item_maintenance_plan RENAME klusbibdb.inventory_item_maintenance_plan');
        $this->query('ALTER TABLE lendengine.inventory_item_product_tag RENAME klusbibdb.inventory_item_product_tag');
        $this->query('ALTER TABLE lendengine.inventory_item_site RENAME klusbibdb.inventory_item_site');
        $this->query('ALTER TABLE lendengine.inventory_location RENAME klusbibdb.inventory_location');
        $this->query('ALTER TABLE lendengine.item_condition RENAME klusbibdb.item_condition');
        $this->query('ALTER TABLE lendengine.item_movement RENAME klusbibdb.item_movement');
        $this->query('ALTER TABLE lendengine.kit_component RENAME klusbibdb.kit_component');
        $this->query('ALTER TABLE lendengine.loan RENAME klusbibdb.loan');
        $this->query('ALTER TABLE lendengine.loan_row RENAME klusbibdb.loan_row');
        $this->query('ALTER TABLE lendengine.maintenance RENAME klusbibdb.maintenance');
        $this->query('ALTER TABLE lendengine.maintenance_plan RENAME klusbibdb.maintenance_plan');
        $this->query('ALTER TABLE lendengine.migration_versions RENAME klusbibdb.migration_versions');
        $this->query('ALTER TABLE lendengine.note RENAME klusbibdb.note');
        $this->query('ALTER TABLE lendengine.page RENAME klusbibdb.page');
        $this->query('ALTER TABLE lendengine.payment RENAME klusbibdb.payment');
        $this->query('ALTER TABLE lendengine.payment_method RENAME klusbibdb.payment_method');
        $this->query('ALTER TABLE lendengine.product_field RENAME klusbibdb.product_field');
        $this->query('ALTER TABLE lendengine.product_field_select_option RENAME klusbibdb.product_field_select_option');
        $this->query('ALTER TABLE lendengine.product_field_value RENAME klusbibdb.product_field_value');
        $this->query('ALTER TABLE lendengine.product_section RENAME klusbibdb.product_section');
        $this->query('ALTER TABLE lendengine.product_tag RENAME klusbibdb.product_tag');
        $this->query('ALTER TABLE lendengine.setting RENAME klusbibdb.setting');
        $this->query('ALTER TABLE lendengine.site RENAME klusbibdb.site');
        $this->query('ALTER TABLE lendengine.site_opening RENAME klusbibdb.site_opening');
        $this->query('ALTER TABLE lendengine.theme RENAME klusbibdb.theme');
        $this->query('ALTER TABLE lendengine.waiting_list_item RENAME klusbibdb.waiting_list_item');

        // Update constraints
        // Make membership price nullable, as not yet provided on API
        $this->query('ALTER TABLE `membership` CHANGE `price` `price` DECIMAL(10,2) NULL DEFAULT NULL');
        // move kb_payments membership_id constraint from kb_membership to membership table
        $this->query('ALTER TABLE `kb_payments` DROP  FOREIGN KEY `payments_membership_id_foreign`');
        $this->query('ALTER TABLE `kb_payments` CHANGE `membership_id` `membership_id` INT(11) NULL DEFAULT NULL'); // column format should be identical
        $this->query('ALTER TABLE `kb_payments` ADD  CONSTRAINT `payments_membership_id_foreign` FOREIGN KEY (`membership_id`) REFERENCES `membership`(`id`)');

	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        
        $this->query('ALTER TABLE klusbibdb.app RENAME lendengine.app');
        $this->query('ALTER TABLE klusbibdb.app_setting RENAME lendengine.app_setting');
        $this->query('ALTER TABLE klusbibdb.attendee RENAME lendengine.attendee');
        $this->query('ALTER TABLE klusbibdb.check_in_prompt RENAME lendengine.check_in_prompt');
        $this->query('ALTER TABLE klusbibdb.check_out_prompt RENAME lendengine.check_out_prompt');
        $this->query('ALTER TABLE klusbibdb.child RENAME lendengine.child');
        $this->query('ALTER TABLE klusbibdb.contact_field RENAME lendengine.contact_field');
        $this->query('ALTER TABLE klusbibdb.contact_field_select_option rename lendengine.contact_field_select_option');
        $this->query('ALTER TABLE klusbibdb.contact_field_value RENAME lendengine.contact_field_value');
        $this->query('ALTER TABLE klusbibdb.deposit RENAME lendengine.deposit');
        $this->query('ALTER TABLE klusbibdb.event RENAME lendengine.event');
        $this->query('ALTER TABLE klusbibdb.ext_translations RENAME lendengine.ext_translations');
        $this->query('ALTER TABLE klusbibdb.file_attachment RENAME lendengine.file_attachment');
        $this->query('ALTER TABLE klusbibdb.image RENAME lendengine.image');
        $this->query('ALTER TABLE klusbibdb.inventory_item_check_in_prompt RENAME lendengine.inventory_item_check_in_prompt');
        $this->query('ALTER TABLE klusbibdb.inventory_item_check_out_prompt RENAME lendengine.inventory_item_check_out_prompt');
        $this->query('ALTER TABLE klusbibdb.inventory_item_maintenance_plan RENAME lendengine.inventory_item_maintenance_plan');
        $this->query('ALTER TABLE klusbibdb.inventory_item_product_tag RENAME lendengine.inventory_item_product_tag');
        $this->query('ALTER TABLE klusbibdb.inventory_item_site RENAME lendengine.inventory_item_site');
        $this->query('ALTER TABLE klusbibdb.inventory_location RENAME lendengine.inventory_location');
        $this->query('ALTER TABLE klusbibdb.item_condition RENAME lendengine.item_condition');
        $this->query('ALTER TABLE klusbibdb.item_movement RENAME lendengine.item_movement');
        $this->query('ALTER TABLE klusbibdb.kit_component RENAME lendengine.kit_component');
        $this->query('ALTER TABLE klusbibdb.loan RENAME lendengine.loan');
        $this->query('ALTER TABLE klusbibdb.loan_row RENAME lendengine.loan_row');
        $this->query('ALTER TABLE klusbibdb.maintenance RENAME lendengine.maintenance');
        $this->query('ALTER TABLE klusbibdb.maintenance_plan RENAME lendengine.maintenance_plan');
        $this->query('ALTER TABLE klusbibdb.migration_versions RENAME lendengine.migration_versions');
        $this->query('ALTER TABLE klusbibdb.note RENAME lendengine.note');
        $this->query('ALTER TABLE klusbibdb.page RENAME lendengine.page');
        $this->query('ALTER TABLE klusbibdb.payment RENAME lendengine.payment');
        $this->query('ALTER TABLE klusbibdb.payment_method RENAME lendengine.payment_method');
        $this->query('ALTER TABLE klusbibdb.product_field RENAME lendengine.product_field');
        $this->query('ALTER TABLE klusbibdb.product_field_select_option RENAME lendengine.product_field_select_option');
        $this->query('ALTER TABLE klusbibdb.product_field_value RENAME lendengine.product_field_value');
        $this->query('ALTER TABLE klusbibdb.product_section RENAME lendengine.product_section');
        $this->query('ALTER TABLE klusbibdb.product_tag RENAME lendengine.product_tag');
        $this->query('ALTER TABLE klusbibdb.setting RENAME lendengine.setting');
        $this->query('ALTER TABLE klusbibdb.site RENAME lendengine.site');
        $this->query('ALTER TABLE klusbibdb.site_opening RENAME lendengine.site_opening');
        $this->query('ALTER TABLE klusbibdb.theme RENAME lendengine.theme');
        $this->query('ALTER TABLE klusbibdb.waiting_list_item RENAME lendengine.waiting_list_item');

        $this->query('DROP VIEW IF EXISTS `contact_field`');
        $this->query('DROP VIEW IF EXISTS `contact_field_select_option`');
        $this->query('DROP VIEW IF EXISTS `contact_field_value`');
        $sql = "CREATE VIEW contact_field AS SELECT * FROM lendengine.contact_field";
        $db->exec($sql);
        $sql = "CREATE VIEW contact_field_select_option AS SELECT * FROM lendengine.contact_field_select_option";
        $db->exec($sql);
        $sql = "CREATE VIEW contact_field_value AS SELECT * FROM lendengine.contact_field_value";
        $db->exec($sql);

        $this->query('DROP VIEW IF EXISTS `inventory_item_product_tag`');
        $this->query('DROP VIEW IF EXISTS `item_movement`');
        $sql = "CREATE VIEW inventory_item_product_tag AS SELECT * FROM lendengine.inventory_item_product_tag";
        $db->exec($sql);
        $sql = "CREATE VIEW item_movement AS SELECT * FROM lendengine.item_movement";
        $db->exec($sql);
        $this->query('DROP VIEW IF EXISTS `le_membership`');
        $sql = "CREATE VIEW le_membership AS SELECT * FROM lendengine.membership";
        $db->exec($sql);
        $this->query('DROP VIEW IF EXISTS `loan`');
        $this->query('DROP VIEW IF EXISTS `loan_row`');
        $sql = "CREATE VIEW loan AS SELECT * FROM lendengine.loan";
        $db->exec($sql);
        $sql = "CREATE VIEW loan_row AS SELECT * FROM lendengine.loan_row";
        $db->exec($sql);
        $this->query('DROP VIEW IF EXISTS `note`');
        $this->query('DROP VIEW IF EXISTS `payment`');
        $sql = "CREATE VIEW note AS SELECT * FROM lendengine.note";
        $db->exec($sql);
        $sql = "CREATE VIEW payment AS SELECT * FROM lendengine.payment";
        $db->exec($sql);
        $this->query('DROP VIEW IF EXISTS `product_field`');
        $this->query('DROP VIEW IF EXISTS `product_field_select_option`');
        $this->query('DROP VIEW IF EXISTS `product_field_value`');
        $this->query('DROP VIEW IF EXISTS `product_tag`');
        $sql = "CREATE VIEW product_field AS SELECT * FROM lendengine.product_field";
        $db->exec($sql);
        $sql = "CREATE VIEW product_field_select_option AS SELECT * FROM lendengine.product_field_select_option";
        $db->exec($sql);
        $sql = "CREATE VIEW product_field_value AS SELECT * FROM lendengine.product_field_value";
        $db->exec($sql);
        $sql = "CREATE VIEW product_tag AS SELECT * FROM lendengine.product_tag";
        $db->exec($sql);
    
    }
}