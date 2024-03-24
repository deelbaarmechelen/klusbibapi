<?php

namespace Api\Model;
use Database\Factories\DeliveryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder where(...$params)
 */
class DeliveryItem extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return DeliveryItemFactory::new();
    }

    protected $table = 'kb_delivery_item';
    public function delivery()
    {
        return $this->belongsTo(\Api\Model\Delivery::class, 'delivery_id', 'id');
    }
    public function reservation()
    {
        return $this->hasOne(\Api\Model\Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function lending()
    {
        return $this->hasOne(\Api\Model\Lending::class, 'lending_id', 'lending_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(\Api\Model\InventoryItem::class, 'inventory_item_id', 'id');
    }
}