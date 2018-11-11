<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $primaryKey = "payment_id";
    static protected $fieldArray = ['payment_id', 'user_id', 'mode', 'payment_date', 'order_id',
        'amount', 'currency', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Payment::$fieldArray);
    }

}