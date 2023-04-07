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
class MoveMemberships extends AbstractCapsuleMigration
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
        // rename existing membership tables
        $this->query('ALTER TABLE klusbibdb.membership_type RENAME klusbibdb.kb_membership_type');
        $this->query('ALTER TABLE klusbibdb.membership RENAME klusbibdb.kb_membership');
        // clone lendengine membership tables to klusbibdb
        Capsule::update('CREATE TABLE klusbibdb.membership LIKE lendengine.membership');
        Capsule::update('CREATE TABLE klusbibdb.membership_type LIKE lendengine.membership_type');
        Capsule::schema()->table('klusbibdb.membership_type', function(Illuminate\Database\Schema\Blueprint $table){
            $table->integer('next_subscription_id')->unsigned()->nullable()->default(null);
            $table->timestamp('updated_at')->nullable()->default(null);
		});
        Capsule::schema()->table('klusbibdb.membership', function(Illuminate\Database\Schema\Blueprint $table){
            $table->string('last_payment_mode', 20)->nullable()->default(null);
            $table->string('comment',255)->nullable()->default(null);
            $table->timestamp('updated_at')->nullable()->default(null);
            $table->timestamp('deleted_at')->nullable()->default(null);
            // last_sync_date no longer needed
		});
        $this->query('DROP TRIGGER IF EXISTS `membership_bi`');
        $this->query('DROP TRIGGER IF EXISTS `membership_bu`');

        $db = Capsule::Connection()->getPdo();
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $sql = "
CREATE TRIGGER `membership_bi` BEFORE INSERT ON `membership` FOR EACH ROW 
BEGIN 
IF NEW.created_at IS NULL THEN
  SET NEW.created_at = CURRENT_TIMESTAMP;
END IF;
END";
        $db->exec($sql);
        $sql = "
CREATE TRIGGER `membership_bu` BEFORE UPDATE ON `membership` FOR EACH ROW 
BEGIN 
IF NEW.updated_at IS NULL THEN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END IF;
END";
        $db->exec($sql);
        
        // copy data
        Capsule::update("INSERT INTO klusbibdb.membership_type"
        . " (id, created_by, name, price, duration, discount, created_at, self_serve,"
        . " description, credit_limit, max_items, is_active, next_subscription_id, updated_at) "
        . " SELECT id, null, name, price, duration, discount, IFNULL (created_at, CURRENT_TIMESTAMP), self_serve,"
        . " description, credit_limit, max_items, is_active, next_subscription_id, updated_at"
        . " FROM klusbibdb.kb_membership_type");

        Capsule::update("INSERT INTO klusbibdb.membership"
        . " (id, subscription_id, contact_id, created_by, price, created_at, starts_at, expires_at, status,"
        . " last_payment_mode, comment, updated_at, deleted_at) "
        . " SELECT id, subscription_id, contact_id, null, 0, created_at, start_at, expires_at, status,"
        . " last_payment_mode, comment, updated_at, deleted_at"
        . " FROM klusbibdb.kb_membership");

        $builder = $this->getQueryBuilder();
        $statement = $builder->select(['max' => $builder->func()->max('id')])->from('klusbibdb.membership')->execute();
        $maxId=0;
        // FIXME: should find out how to access first row directly
        foreach ($statement as $row) {
            $maxId = $row['max'];
        }
        $maxId++;
        var_dump('ALTER TABLE klusbibdb.membership auto_increment=' . $maxId);
        Capsule::update('ALTER TABLE klusbibdb.membership auto_increment=' . $maxId);
	}
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        $this->query('DROP TRIGGER IF EXISTS `membership_bi`');
        $this->query('DROP TRIGGER IF EXISTS `membership_bu`');
		Capsule::schema()->drop('klusbibdb.membership_type');
		Capsule::schema()->drop('klusbibdb.membership');
        // rename existing membership tables
        $this->query('ALTER TABLE klusbibdb.kb_membership_type RENAME klusbibdb.membership_type');
        $this->query('ALTER TABLE klusbibdb.kb_membership RENAME klusbibdb.membership');
	}
}