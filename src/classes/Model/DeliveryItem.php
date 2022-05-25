<?php

namespace Api\Model;
use Database\Factories\DeliveryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

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

    protected $table = 'delivery_item';
    public function delivery()
    {
        return $this->belongsTo('Api\Model\Delivery', 'delivery_id', 'id');
    }
    public function reservation()
    {
        return $this->hasOne('Api\Model\Reservation', 'reservation_id', 'reservation_id');
    }

    public function lending()
    {
        return $this->hasOne('Api\Model\Lending', 'lending_id', 'lending_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo('Api\Model\InventoryItem', 'inventory_item_id', 'id');
    }
}