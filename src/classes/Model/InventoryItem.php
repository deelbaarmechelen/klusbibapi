<?php

namespace Api\Model;


use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'inventory_item';
    protected $primaryKey = "id";
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

    public function deliveries() {
        return $this->belongsToMany('Api\Model\Delivery', 'delivery_item',
            'inventory_item_id', 'delivery_id');
    }

    public function scopeOutOfSync($query, $lastSyncDate)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereDate('last_sync_date', '<', $lastSyncDate);
    }
}