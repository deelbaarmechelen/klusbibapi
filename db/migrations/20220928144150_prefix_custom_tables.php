<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class PrefixCustomTables extends AbstractCapsuleMigration
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
        Capsule::update("ALTER TABLE deliveries RENAME TO kb_deliveries");
        Capsule::update("ALTER TABLE delivery_item RENAME TO kb_delivery_item");
        Capsule::update("ALTER TABLE tools RENAME TO kb_tools");
        Capsule::update("ALTER TABLE lendings RENAME TO kb_lendings");
        Capsule::update("ALTER TABLE payments RENAME TO kb_payments");
        Capsule::update("ALTER TABLE projects RENAME TO kb_projects");
        Capsule::update("ALTER TABLE project_user RENAME TO kb_project_user");
        Capsule::update("ALTER TABLE reservations RENAME TO kb_reservations");
        Capsule::update("ALTER TABLE users RENAME TO kb_users");
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        Capsule::update("ALTER TABLE kb_deliveries RENAME TO deliveries");
        Capsule::update("ALTER TABLE kb_delivery_item RENAME TO delivery_item");
        Capsule::update("ALTER TABLE kb_tools RENAME TO tools");
        Capsule::update("ALTER TABLE kb_lendings RENAME TO lendings");
        Capsule::update("ALTER TABLE kb_payments RENAME TO payments");
        Capsule::update("ALTER TABLE kb_projects RENAME TO projects");
        Capsule::update("ALTER TABLE kb_project_user RENAME TO project_user");
        Capsule::update("ALTER TABLE kb_reservations RENAME TO reservations");
        Capsule::update("ALTER TABLE kb_users RENAME TO users");
	}
}