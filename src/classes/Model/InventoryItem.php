<?php

namespace Api\Model;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $item_type
 * @property mixed $created_by
 * @property mixed $assigned_to
 * @property mixed $current_location_id
 * @property mixed $item_condition
 * @property mixed $sku
 * @property mixed $description
 * @property mixed $keywords
 * @property mixed $brand
 * @property mixed $care_information
 * @property mixed $component_information
 * @property mixed $loan_fee
 * @property mixed $max_loan_days
 * @property mixed $is_active
 * @property mixed $show_on_website
 * @property mixed $serial
 * @property mixed $note
 * @property mixed $price_cost
 * @property mixed $price_sell
 * @property mixed $image_name
 * @property mixed $short_url
 * @property mixed $item_sector
 * @property mixed $is_reservable
 * @property mixed $deposit_amount
 * @property mixed $donated_by
 * @property mixed $owned_by
 * @property mixed $last_sync_date
 * @property mixed $experience_level
 * @property mixed $safety_risk
 * @property mixed $deliverable
 * @property mixed $size
 * @property mixed $created_at
 * @property mixed $updated_at
 * 
 * @method static Builder outOfSync($lastSyncDate)
 * @method static Builder archive()
 * @method static \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|null find($id, $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection findOrFail($id, $columns = ['*'])
 */
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
        return in_array($field, InventoryItem::$fieldArray);
    }

    public function tool()
    {
        return $this->hasOne(\Api\Model\Tool::class, 'tool_id');
    }
    public function deliveryItems() {
        return $this->hasMany(\Api\Model\DeliveryItem::class, 'inventory_item_id', 'id');
    }

    public function tags() {
        return $this->belongsToMany(\Api\Model\ProductTag::class, 'inventory_item_product_tag',
            'inventory_item_id', 'product_tag_id');
    }

    public function images() {
        return $this->hasMany(\Api\Model\Image::class, 'inventory_item_id', 'id');
    }

    public function scopeOutOfSync($query, $lastSyncDate)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereDate('last_sync_date', '<', $lastSyncDate);
    }

    /**
     * Archives the inventory items
     * @return count of updated items
     */
    public function scopeArchive($query)
    {
        return $query->update(['is_active' => 0, 'current_location_id' => null]);
    }
}