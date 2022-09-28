<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class CreateContact extends AbstractCapsuleMigration
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
        $this->query('DROP VIEW IF EXISTS `contact`');
		Capsule::schema()->create('contact', function(Illuminate\Database\Schema\Blueprint $table){
            // Auto-increment id
            $table->increments('id');
            $table->date('created_by')->nullable()->default(null);
            $table->unsignedInteger('active_membership')->nullable()->default(null);
            $table->foreign('active_membership')->references('id')->on('membership');;
            $table->timestamp('last_login')->nullable()->default(null);
            $table->boolean('enabled');
            $table->string('salt', 255)->nullable()->default(null);
            $table->string('confirmation_token', 180)->nullable()->default(null);
            $table->string('password', 255)->nullable()->default(null); // should replace hash?
            $table->timestamp('password_requested_at')->nullable()->default(null);
            // 10	roles	longtext	utf8mb4_unicode_ci		Nee	Geen	(DC2Type:array)
            $table->string('roles', 255)->nullable()->default(null); // should replace hash?
            $table->string('first_name', 32)->nullable()->default(null);
            $table->string('last_name', 32)->nullable()->default(null);
            $table->string('telephone', 64)->nullable()->default(null);
            $table->string('address_line_1', 255)->nullable()->default(null);
            $table->string('address_line_2', 255)->nullable()->default(null);
            $table->string('address_line_3', 255)->nullable()->default(null);
            $table->string('address_line_4', 255)->nullable()->default(null);
            $table->string('country_iso_code', 3)->nullable()->default(null);
            $table->string('latitude', 32)->nullable()->default(null);
            $table->string('longitude', 32)->nullable()->default(null);
            $table->string('gender', 1)->nullable()->default(null);
            $table->string('postal_code', 5)->nullable()->default(null);
            $table->string('city', 50)->nullable()->default(null);
            $table->decimal('balance',10,2);
            $table->string('stripe_customer_id', 255)->nullable()->default(null);
            $table->boolean('subscriber');
            $table->string('email', 255)->nullable()->default(null); // also used as login
            $table->string('email_canonical', 255)->nullable()->default(null);
            $table->string('username', 255)->nullable()->default(null); // also used as login
            $table->string('username_canonical', 255)->nullable()->default(null);
            $table->integer('active_site')->nullable()->default(null);
            $table->integer('created_at_site')->nullable()->default(null);
            $table->string('locale', 255)->nullable()->default(null);
            $table->boolean('is_active');
            $table->string('membership_number', 64)->nullable()->default(null);
            $table->string('secure_access_token', 255)->nullable()->default(null);
            // Klusbib API specific
            $table->string('role', 20)->nullable()->default(null); // admin, member, ...
            $table->string('hash', 255)->nullable()->default(null); // password hash
            $table->date('membership_start_date')->nullable()->default(null);
            $table->date('membership_end_date')->nullable()->default(null);
            $table->date('birth_date')->nullable()->default(null);
            $table->string('mobile', 15)->nullable()->default(null);
            $table->string('state', 20)->default('DISABLED');
            $table->string('registration_number', 15)->nullable()->default(null);
            $table->string('payment_mode', 20)->nullable()->default(null);
            $table->date('accept_terms_date')->nullable()->default(null);
            $table->string('email_state', 20)->nullable()->default(null);
            $table->string('user_ext_id', 20)->nullable()->default(null);
            $table->string('last_sync_date', 255)->nullable()->default(null);
            $table->string('company', 50)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);

            // Required for Eloquent's created_at and updated_at columns
            $table->softDeletes();
            $table->timestamps();
		});
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        Capsule::schema()->drop('contact');

        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $sql = "CREATE VIEW contact AS SELECT * FROM lendengine.contact";
        $db->exec($sql);
	}
}
