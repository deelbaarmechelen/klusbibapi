<?php
require_once __DIR__ . '/../AbstractCapsuleMigration.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class AddMembershipTypes extends AbstractCapsuleMigration
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
        Capsule::update('INSERT INTO membership_type (name, price, duration, created_at, self_serve, description, max_items, next_subscription_id) '
            . 'VALUES ("RegularOrg", 75, 365, null, 1, "", 5, null)'); // id 5
        Capsule::update('INSERT INTO membership_type (name, price, duration, created_at, self_serve, description, max_items, next_subscription_id) '
            . 'VALUES ("RenewalOrg", 50, 365, null, 0, "", 5, null)'); // id 6
        Capsule::update("UPDATE membership_type SET duration = 62 WHERE name = 'Temporary'"); // temporary duration is 2 months
        Capsule::update("UPDATE membership_type SET next_subscription_id = 6 WHERE name = 'RegularOrg'"); // regularOrg -> renewalOrg
        Capsule::update("UPDATE membership_type SET next_subscription_id = 6 WHERE name = 'RenewalOrg'"); // renewalOrg -> renewalOrg
        Capsule::update("UPDATE membership_type SET next_subscription_id = 3 WHERE name = 'Renewal'");    // Renewal -> Renewal
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        Capsule::update("DELETE FROM membership_type WHERE name = 'RegularOrg' ");
        Capsule::update("DELETE FROM membership_type WHERE name = 'RenewalOrg' ");
	}
}