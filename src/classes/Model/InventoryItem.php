<?php

namespace Api\Model;


use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'inventory_item';
    protected $primaryKey = "id";
    public $incrementing = false;
    static protected $fieldArray = ['id',
        // to be completed!
        'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Delivery::$fieldArray);
    }

    public function deliveryItems() {
        return $this->hasMany('Api\Model\DeliveryItem', 'inventory_item_id', 'id');
    }
//    public function deliveries() {
//        return $this->belongsToMany('Api\Model\Delivery', 'delivery_item',
//            'inventory_item_id', 'delivery_id')
//            ->using(DeliveryItem::class)
//            ->withPivot([
//                'reservation_id',
//                'comment',
//                'fee',
//                'size',
//                'created_at',
//                'updated_at',
//            ])
//            ->withTimestamps();
//        ;
//    }

    public function scopeOutOfSync($query, $lastSyncDate)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereDate('last_sync_date', '<', $lastSyncDate);
    }
}