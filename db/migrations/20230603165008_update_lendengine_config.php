<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class UpdateLendengineConfig extends AbstractCapsuleMigration
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

        // Update account
        Capsule::update("update _core.account set db_schema = 'klusbibdb' where db_schema = 'lendengine'");
        // Update product_tag to limit the categories shown on website
        Capsule::update("update product_tag set show_on_website = 0 where not section_id is null");
        Capsule::update("INSERT INTO product_tag (id, name, show_on_website, sort, section_id) "
          . " select id * 10, name, 1, sort, null from product_section");
        Capsule::update("update product_tag set section_id = null");
        Capsule::update("delete from product_section");

        // update created_by values
        Capsule::update("update attendee set created_by = 1 where created_by = 1002");
        Capsule::update("update deposit set created_by = 1 where created_by = 1002");
        Capsule::update("update event set created_by = 1 where created_by = 1002");
        Capsule::update("update item_movement set created_by = 1 where created_by = 1002");
        Capsule::update("update loan set created_by = 1 where created_by = 1002");
        Capsule::update("update note set created_by = 1 where created_by = 1002");
        Capsule::update("update page set created_by = 1 where created_by = 1002");
        Capsule::update("update page set updated_by = 1 where updated_by = 1002");
        Capsule::update("update payment set created_by = 1 where created_by = 1002");

        // update item_type
        Capsule::update("UPDATE `inventory_item` SET item_type='loan' WHERE item_type = 'TOOL'");
        Capsule::update("UPDATE `inventory_item` SET item_type='loan' WHERE item_type = 'ACCESSORY'");

        // update foreign keys
        $sql = file_get_contents(__DIR__ . "/20230607184900_update_foreign_keys.sql");
        $this->multiQueryOnPDO($sql);
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        
        // update item_type
        Capsule::update("UPDATE `inventory_item` SET item_type='TOOL' WHERE item_type = 'loan' AND id < 100000");
        Capsule::update("UPDATE `inventory_item` SET item_type='ACCESSORY' WHERE item_type = 'loan' AND id >= 100000");

        // Restore created_by
        Capsule::update("update attendee set created_by = 1002 where created_by = 1");
        Capsule::update("update deposit set created_by = 1002 where created_by = 1");
        Capsule::update("update event set created_by = 1002 where created_by = 1");
        Capsule::update("update item_movement set created_by = 1002 where created_by = 1");
        Capsule::update("update loan set created_by = 1002 where created_by = 1");
        Capsule::update("update note set created_by = 1002 where created_by = 1");
        Capsule::update("update page set created_by = 1002 where created_by = 1");
        Capsule::update("update page set updated_by = 1002 where updated_by = 1");
        Capsule::update("update payment set created_by = 1002 where created_by = 1");


        // Restore product_tag to limit the categories shown on website
        Capsule::update("INSERT INTO product_section (id, name, show_on_website, sort) "
           . " select id / 10, name, 1, 0 from product_tag where id >= 10 and id < 100");
        Capsule::update("delete from product_tag where id >= 10 and id < 100");
        Capsule::update("update product_tag set show_on_website = 1 where not section_id is null");
        
        // Restore account
        Capsule::update("update _core.account set db_schema = 'lendengine' where db_schema = 'klusbibdb'");
  }

  private function multiQueryOnPDO($sql)
  {
    $db = Capsule::connection()->getPdo();

    // works regardless of statements emulation
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    $db->exec($sql);    
  }
}