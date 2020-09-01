<?php

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;
//use Illuminate\Support\Facades\DB;
//use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../../src/env.php';
require_once __DIR__ . '/../../src/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class CreateMemberships extends AbstractMigration
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
//        Capsule::schema()->drop('membership');
//        Capsule::schema()->drop('membership_type');
	    // Tables aligned to Lend Engine data model in preparation to eventual transition
        Capsule::schema()->create('membership', function(Illuminate\Database\Schema\Blueprint $table){
            // Auto-increment id
            $table->increments('id');
            $table->timestamp('start_at')->nullable()->default(null);
            $table->timestamp('expires_at')->nullable()->default(null);
            $table->string('status',20)->default('DISABLED');
            $table->unsignedInteger('subscription_id')->nullable()->default(null); // FK to membership_type
            $table->unsignedInteger('contact_id')->nullable()->default(null); // FK to users - contact person for membership
            $table->string('last_payment_mode',20)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);

            // Required for Eloquent's created_at and updated_at columns
            $table->timestamps();
            $table->softDeletes();
		});

        Capsule::schema()->create('membership_type', function(Illuminate\Database\Schema\Blueprint $table){
            // Auto-increment id
            $table->increments('id');
            $table->string('name',64);
            $table->unsignedDecimal('price',10,2)->nullable()->default(null);
            $table->integer('duration')->nullable()->default(null);
            $table->unsignedDecimal('discount',10,2)->nullable()->default(null);
            $table->string('description', 1024)->nullable()->default(null);
            $table->integer('self_serve');
            $table->unsignedDecimal('credit_limit',10,2)->nullable()->default(null);
            $table->integer('max_items')->nullable()->default(null);
            $table->boolean('is_active')->default(true);
            // convert membership to this 'next subscription id' when renewed after expiration (not yet supported in Lend Engine)
            $table->unsignedInteger('next_subscription_id')->nullable()->default(null);

            // Required for Eloquent's created_at and updated_at columns
            $table->timestamps();
        });

        // Change the auto_increment value
        $maxValue = \Api\Model\User::max('user_id');
        Capsule::update('ALTER TABLE users AUTO_INCREMENT = ' . strval($maxValue + 1));
        Capsule::update('INSERT INTO membership_type (name, price, duration, created_at, self_serve, description, max_items, next_subscription_id) '
            . 'VALUES ("Regular", 30, 365, null, 1, "", 5, null)');
        Capsule::update('INSERT INTO membership_type (name, price, duration, created_at, self_serve, description, max_items, next_subscription_id) '
            . 'VALUES ("Temporary", 0, 60, null, 0, "", 5, null)');
        Capsule::update('INSERT INTO membership_type (name, price, duration, created_at, self_serve, description, max_items, next_subscription_id) '
            . 'VALUES ("Renewal", 20, 365, null, 0, "", 5, null)');
        Capsule::update('INSERT INTO membership_type (name, price, duration, created_at, self_serve, description, max_items, next_subscription_id) '
            . 'VALUES ("Stroom", 0, 365, null, 0, "", 5, null)');
        Capsule::update("INSERT INTO membership (id, start_at, expires_at, last_payment_mode, contact_id, status, created_at, updated_at, subscription_id) "
            . "select user_id, membership_start_date, membership_end_date, payment_mode, user_id, state, created_at, updated_at, 1 FROM users "
            . "WHERE role = 'member'  OR role = 'admin'");
        Capsule::update("UPDATE membership SET status = 'PENDING' WHERE status = 'CHECK_PAYMENT' ");
        Capsule::update("UPDATE membership SET status = 'CANCELLED' WHERE status = 'DELETED' OR status = 'DISABLED' ");
        Capsule::update("UPDATE membership_type SET next_subscription_id = 1 WHERE name = 'Temporary'"); // switch to regular membership
        Capsule::update("UPDATE membership_type SET next_subscription_id = 3 WHERE name = 'Regular'"); // regular -> renewal
        Capsule::update("UPDATE membership_type SET next_subscription_id = 3 WHERE name = 'Stroom'");  // stroom -> renewal
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
		Capsule::schema()->drop('membership');
        Capsule::schema()->drop('membership_type');
	}
}