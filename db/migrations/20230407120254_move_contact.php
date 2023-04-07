<?php

use \AbstractCapsuleMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class MoveContact extends AbstractCapsuleMigration
{
    const DEFAULT_PASSWORD = '$2y$13$JJRAiAUQgjIg1bkskpf6fuyFaGvW4DrVKXnqZ/iPjqZTHxzGbZ3Xe';
    const ADMIN_ROLE = 'a:2:{i:0;s:10:"ROLE_ADMIN";i:1;s:15:"ROLE_SUPER_USER";}';
    const MEMBER_ROLE = 'a:0:{}';

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
        // rename existing inventory_item tables
        $this->query('ALTER TABLE klusbibdb.contact RENAME klusbibdb.kb_contact');
        // clone lendengine inventory_item tables to klusbibdb
        Capsule::update('CREATE TABLE klusbibdb.contact LIKE lendengine.contact');
        Capsule::schema()->table('klusbibdb.contact', function(Illuminate\Database\Schema\Blueprint $table){
            // Klusbib API specific
            $table->string('role', 20)->nullable()->default(null); // admin, member, ...
            $table->date('membership_start_date')->nullable()->default(null);
            $table->date('membership_end_date')->nullable()->default(null);
            $table->string('state', 20)->default('DISABLED');
            $table->string('registration_number', 15)->nullable()->default(null);
            $table->string('payment_mode', 20)->nullable()->default(null);
            $table->date('accept_terms_date')->nullable()->default(null);
            $table->string('email_state', 20)->nullable()->default(null);
            $table->string('user_ext_id', 20)->nullable()->default(null);
            $table->string('last_sync_date', 255)->nullable()->default(null);
            $table->string('company', 50)->nullable()->default(null);
            $table->string('comment', 255)->nullable()->default(null);
            $table->timestamp('deleted_at')->nullable()->default(null);
            $table->timestamp('updated_at')->nullable()->default(null);
		});
        
        // TODO: Create triggers to update 'roles'
        $this->query('DROP TRIGGER IF EXISTS `contact_bi`');
        $this->query('DROP TRIGGER IF EXISTS `contact_bu`');
        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $sql = "
CREATE TRIGGER `contact_bi` BEFORE INSERT ON `contact` FOR EACH ROW 
BEGIN 
IF NEW.role = 'admin' THEN
  SET NEW.roles = '" . self::ADMIN_ROLE . "';
ELSE
  SET NEW.roles = '" . self::MEMBER_ROLE . "';
END IF;
IF NEW.country_iso_code IS NULL THEN
  SET NEW.country_iso_code = 'BE';
END IF;
IF NEW.balance IS NULL THEN
  SET NEW.balance = '0.00';
END IF;
IF NEW.subscriber IS NULL THEN
  SET NEW.subscriber = 0;
END IF;
IF NEW.locale IS NULL THEN
  SET NEW.locale = 'nl';
END IF;
IF NEW.is_active IS NULL THEN
  SET NEW.is_active = 1;
END IF;
IF NEW.email_canonical IS NULL THEN
  SET NEW.email_canonical = NEW.email;
END IF;
IF NEW.username IS NULL THEN
  SET NEW.username = NEW.email;
END IF;
IF NEW.username_canonical IS NULL THEN
  SET NEW.username_canonical = NEW.email;
END IF;
END";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER `contact_bu` BEFORE UPDATE ON `contact` FOR EACH ROW 
BEGIN 
IF NEW.role = 'admin' THEN
  SET NEW.roles = '" . self::ADMIN_ROLE . "';
ELSE
  SET NEW.roles = '" . self::MEMBER_ROLE . "';
END IF;
IF OLD.country_iso_code IS NULL AND NEW.country_iso_code IS NULL THEN
  SET NEW.country_iso_code = 'BE';
END IF;
IF OLD.balance IS NULL AND NEW.balance IS NULL THEN
  SET NEW.balance = '0.00';
END IF;
IF OLD.subscriber IS NULL AND NEW.subscriber IS NULL THEN
  SET NEW.subscriber = 0;
END IF;
IF OLD.locale IS NULL AND NEW.locale IS NULL THEN
  SET NEW.locale = 'nl';
END IF;
IF OLD.is_active IS NULL AND NEW.is_active IS NULL THEN
  SET NEW.is_active = 1;
END IF;
IF NEW.email_canonical IS NULL THEN
  SET NEW.email_canonical = NEW.email;
END IF;
IF NEW.username IS NULL THEN
  SET NEW.username = NEW.email;
END IF;
IF NEW.username_canonical IS NULL THEN
  SET NEW.username_canonical = NEW.email;
END IF;
END";
        $db->exec($sql);

        // copy data
        Capsule::update("INSERT INTO klusbibdb.contact"
        . " (`id`, `created_by`, `active_membership`, `enabled`, `salt`, `password`, `last_login`, `confirmation_token`,"
        . " `password_requested_at`, IF(role = 'admin', 'a:2:{i:0;s:10:\"ROLE_ADMIN\";i:1;s:15:\"ROLE_SUPER_USER\";}', 'a:0:{}'), `first_name`, `last_name`, `telephone`, `address_line_1`, `address_line_2`,"
        . " `address_line_3`, `address_line_4`, `country_iso_code`, `latitude`, `longitude`, `gender`, `created_at`,"
        . " `balance`, `stripe_customer_id`, `subscriber`, `email`, `email_canonical`, `username`, `username_canonical`,"
        . " `active_site`, `created_at_site`, `locale`, `is_active`, `membership_number`, `secure_access_token`) "
        . " SELECT `id`, `created_by`, `active_membership`, `enabled`, `salt`, `password`, `last_login`, `confirmation_token`,"
        . " `password_requested_at`, `roles`, `first_name`, `last_name`, `telephone`, `address_line_1`, `address_line_2`,"
        . " `address_line_3`, `address_line_4`, `country_iso_code`, `latitude`, `longitude`, `gender`, `created_at`"
        . "  `balance`, `stripe_customer_id`, `subscriber`, `email`, `email_canonical`, `username`, `username_canonical`,"
        . " `active_site`, `created_at_site`, `locale`, `is_active`, `membership_number`, `secure_access_token`,"
        . " `role`, `membership_start_date`, `membership_end_date`, `state`, `registration_number`, `payment_mode`,"
        . " `accept_terms_date`, `email_state`, `user_ext_id`, `last_sync_date`, `company`, `comment`, `deleted_at`, `updated_at`"
        . " FROM klusbibdb.kb_contact");

        // Update auto_increment
        $builder = $this->getQueryBuilder();
        $statement = $builder->select(['max' => $builder->func()->max('id')])->from('klusbibdb.contact')->execute();
        $maxId=0;
        // FIXME: should find out how to access first row directly
        foreach ($statement as $row) {
            $maxId = $row['max'];
        }
        $maxId++;
        var_dump('ALTER TABLE klusbibdb.contact auto_increment=' . $maxId);
        Capsule::update('ALTER TABLE klusbibdb.contact auto_increment=' . $maxId);      
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        $this->query('DROP TRIGGER IF EXISTS `contact_bi`');
        $this->query('DROP TRIGGER IF EXISTS `contact_bu`');
		Capsule::schema()->drop('klusbibdb.contact');
        // rename existing tables
        $this->query('ALTER TABLE klusbibdb.kb_contact RENAME klusbibdb.contact');
	}
}