<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class AlignLendenginePayment extends AbstractCapsuleMigration
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
        Capsule::schema()->table('payment', function(Illuminate\Database\Schema\Blueprint $table){
            $table->timestamp('kb_payment_timestamp')->nullable()->default(null);
            $table->string('kb_mode',20)->nullable()->default(null);
            $table->string('kb_state',20)->nullable()->default(null);
            $table->string('kb_order_id',50)->nullable()->default(null); // to be replaced by psp_code?
            $table->timestamp('kb_expiration_date')->nullable()->default(null); // for gift vouchers with validity limited in time (a few values filled in)
            // to check: payment date can drop timestamp?
            // note: currency, updated_at, last_sync_date to be dropped
        });
        Capsule::update('DELETE FROM payment_method WHERE id > 3');
        Capsule::update('UPDATE payment_method SET name = \'Credit/debit card (Mollie)\' WHERE id = 2 AND name LIKE \'%debit card%\'');
        Capsule::update('INSERT INTO payment_method (id, name, is_active) VALUES (4, "Payconiq", 1),(5, "LETS", 1),(6, "Mechelen Bon (MBON)", 1),(7, "Kdo Bon (KDOBON)", 1),(8, "Other", 1)');
        Capsule::update('INSERT INTO payment (id, created_at, type, payment_date, amount, psp_code, note, contact_id, membership_id, loan_id, kb_payment_timestamp, kb_mode, kb_state, kb_order_id, kb_expiration_date) '
            . 'SELECT (payment_id *2) -1, created_at, \'PAYMENT\', DATE(payment_date), amount, order_id, IFNULL(comment,\'Payment received.\'), user_id, membership_id, loan_id, payment_date, mode, state, order_id, expiration_date FROM kb_payments WHERE state = \'SUCCESS\' AND NOT user_id IS NULL');
        Capsule::update('INSERT INTO payment (id, created_at, type, payment_date, amount, psp_code, note, contact_id, membership_id, loan_id, kb_payment_timestamp, kb_mode, kb_state, kb_order_id, kb_expiration_date) '
            . 'SELECT (payment_id *2) -1, created_at, \'PAYMENT\', DATE(payment_date), 0, order_id, comment, user_id, membership_id, loan_id, payment_date, mode, state, order_id, expiration_date FROM kb_payments WHERE state IN (\'CANCELED\', \'FAILED\', \'EXPIRED\', \'REFUND\', \'CHARGEBACK\') AND NOT user_id IS NULL');
        Capsule::update('INSERT INTO payment (id, created_at, type, payment_date, amount, psp_code, note, contact_id, membership_id, loan_id, kb_payment_timestamp, kb_mode, kb_state, kb_order_id, kb_expiration_date) '
            . 'SELECT (payment_id *2) -1, created_at, \'PAYMENT\', DATE(payment_date), amount, order_id, comment, user_id, membership_id, loan_id, payment_date, mode, state, order_id, expiration_date FROM kb_payments WHERE state IN (\'OPEN\', \'PENDING\') AND NOT user_id IS NULL');
        Capsule::update('UPDATE payment SET payment_method_id = 1 WHERE UPPER(kb_mode) = \'CASH\'');
        Capsule::update('UPDATE payment SET payment_method_id = 2 WHERE UPPER(kb_mode) = \'MOLLIE\'');
        Capsule::update('UPDATE payment SET payment_method_id = 3 WHERE UPPER(kb_mode) = \'TRANSFER\'');
        Capsule::update('UPDATE payment SET payment_method_id = 4 WHERE UPPER(kb_mode) = \'PAYCONIQ\'');
        Capsule::update('UPDATE payment SET payment_method_id = 5 WHERE UPPER(kb_mode) = \'LETS\'');
        Capsule::update('UPDATE payment SET payment_method_id = 6 WHERE UPPER(kb_mode) = \'MBON\'');
        Capsule::update('UPDATE payment SET payment_method_id = 7 WHERE UPPER(kb_mode) = \'KDOBON\'');
        Capsule::update('UPDATE payment SET payment_method_id = 8 WHERE UPPER(kb_mode) IN (\'STROOM\', \'SPONSORING\', \'UNKNOWN\', \'OVAM\', \'OTHER\')');
        //Capsule::update('ALTER TABLE payment AUTO_INCREMENT = your_desired_value');
        // insert membership fees
        Capsule::update('INSERT INTO payment (id, created_at, type, payment_date, amount, note, contact_id, membership_id, kb_payment_timestamp) '
            . 'SELECT payment_id * 2, created_at, \'FEE\', DATE(payment_date), amount, \'Membership fee.\', user_id, membership_id, payment_date' 
            . ' FROM kb_payments WHERE NOT membership_id IS NULL AND state = \'SUCCESS\' AND NOT user_id IS NULL');
    }
    
    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
        $this->initCapsule();
        Capsule::update('DELETE FROM payment WHERE NOT kb_state IS NULL');
        Capsule::update('UPDATE payment SET payment_method_id = NULL WHERE payment_method_id > 3');
        Capsule::update('DELETE FROM payment_method WHERE id > 3');
        Capsule::schema()->table('payment', function(Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('kb_payment_timestamp');
            $table->dropColumn('kb_mode');
            $table->dropColumn('kb_state');
            $table->dropColumn('kb_order_id');
            $table->dropColumn('kb_expiration_date');
        });
	}
}