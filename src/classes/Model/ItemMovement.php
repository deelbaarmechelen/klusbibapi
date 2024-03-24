<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 * Laravel/Eloquent methods
 * @method static Builder whereDate($column, $compare, $date)
 * @method static Lending find($id)
 * @method static Builder where($arr)
 */
class ItemMovement extends Model
{
    protected $table = 'item_movement';
    protected $primaryKey = "id";

    public const UPDATED_AT = null;
    public $timestamps = true;

    static protected $fieldArray = ['id', 'created_by', 'inventory_item_id',
        'inventory_location_id', 'loan_row_id', 'assigned_to_contact_id', 'created_at', 'quantity'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, ItemMovement::$fieldArray);
    }

    /**
     * Get the user that created the item movement.
     */
    public function createdBy()
    {
        return $this->belongsTo(\Api\Model\Contact::class, 'created_by');
    }

    /**
     * Get the moved inventory item.
     */
    public function inventoryItem()
    {
        return $this->belongsTo(\Api\Model\InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Get the associated loan row.
     */
    public function loanRow()
    {
        return $this->belongsTo(\Api\Model\LoanRow::class, 'loan_row_id');
    }
}