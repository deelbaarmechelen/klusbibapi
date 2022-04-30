<?php

namespace Api\Model;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return InventoryItemFactory::new();
    }

    protected $table = 'inventory_item';
    protected $primaryKey = "id";
    public $incrementing = false;
    static protected $fieldArray = ['id',
        'name', 'item_type', 'created_by', 'assigned_to', 'current_location_id', 'item_condition', 'sku', 'description',
        'keywords', 'brand', 'care_information', 'component_information', 'loan_fee', 'max_loan_days', 'is_active',
        'show_on_website', 'serial', 'note', 'price_cost', 'price_sell', 'image_name', 'short_url', 'item_sector',
        'is_reservable', 'deposit_amount', 'donated_by', 'owned_by', 'last_sync_date', 'experience_level', 'safety_risk',
        'deliverable', 'size', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Delivery::$fieldArray);
    }

    public function tool()
    {
        return $this->hasOne('Api\Model\Tool', 'tool_id');
    }
    public function deliveryItems() {
        return $this->hasMany('Api\Model\DeliveryItem', 'inventory_item_id', 'id');
    }

    public function tags() {
        return $this->belongsToMany('Api\Model\ProductTag', 'inventory_item_product_tag',
            'inventory_item_id', 'product_tag_id');
    }

    public function scopeOutOfSync($query, $lastSyncDate)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereDate('last_sync_date', '<', $lastSyncDate);
    }
}