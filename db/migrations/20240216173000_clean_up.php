<?php

use \AbstractCapsuleMigration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class CleanUp extends AbstractCapsuleMigration
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
        Capsule::schema()->dropIfExists('kb_contact');
        Capsule::schema()->table('kb_delivery_item', function (Blueprint $table) {
            $table->dropForeign('delivery_item_reservation_id_foreign');
            $table->dropForeign('delivery_item_lending_id_foreign');
        });
        Capsule::schema()->dropIfExists('kb_delivery_item');
        Capsule::schema()->table('kb_deliveries', function (Blueprint $table) {
            $table->dropForeign('deliveries_payment_id_foreign');
        });
        Capsule::schema()->dropIfExists('kb_deliveries');
        Capsule::schema()->table('kb_reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_tool_id_index');
            $table->dropIndex('reservations_user_id_index');
        });
        Capsule::schema()->dropIfExists('kb_reservations');
        Capsule::schema()->dropIfExists('kb_lendings');
        Capsule::schema()->dropIfExists('kb_membership');
        Capsule::schema()->dropIfExists('kb_membership_type');
        Capsule::schema()->dropIfExists('kb_tools');
        Capsule::schema()->table('kb_users', function (Blueprint $table) {
            $table->dropForeign('users_active_membership_foreign');
        });
        Capsule::schema()->dropIfExists('kb_users');
        Capsule::schema()->dropIfExists('kb_inventory_item');

	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
	}
}