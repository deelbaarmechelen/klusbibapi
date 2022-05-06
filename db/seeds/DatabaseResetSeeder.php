<?php

require_once __DIR__ . '/../AbstractCapsuleSeeder.php';
use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseResetSeeder extends AbstractCapsuleSeeder
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $this->initCapsule();
//        $this->truncateTable('users');
//        $this->truncateTable('payments');
        $this->truncateTable('activity_report');
        $this->truncateTable('consumers');
        $this->truncateTable('deliveries');
        \Api\Model\DeliveryItem::query()->delete();
//        $this->truncateTable('delivery_item');
        $this->truncateTable('events');

        $this->query("DELETE FROM payment");
        $this->query("DELETE FROM inventory_item_product_tag");
        $this->query("DELETE FROM product_field_value");
        $this->query("DELETE FROM product_field_value");
        $this->query("DELETE FROM note");
        $this->query("DELETE FROM item_movement");
        $this->query("DELETE FROM loan_row");
        $this->query("DELETE FROM loan");
//        $this->truncateTable('inventory_item');
        \Api\Model\InventoryItem::query()->delete();
//        $this->truncateTable('lendings');
        \Api\Model\Lending::query()->delete();
        $this->truncateTable('project_user');
        \Api\Model\User::query()->delete();
        \Api\Model\Payment::query()->delete();
        \Api\Model\Membership::query()->delete();
        \Api\Model\Reservation::query()->delete();
        \Api\Model\Tool::query()->delete();

    }

    private function truncateTable($tableName): void
    {
        $table = $this->table($tableName);
        $table->truncate();
    }
}
