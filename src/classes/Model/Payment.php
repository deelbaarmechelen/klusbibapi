<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $primaryKey = "payment_id";
    static protected $fieldArray = ['payment_id', 'user_id', 'state', 'mode', 'payment_date', 'order_id',
        'amount', 'currency', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Payment::$fieldArray);
    }
    public function user() {
        return $this->belongsTo('Api\Model\User', 'user_id', 'user_id');
    }
    public function membership() {
        return $this->belongsTo('Api\Model\Membership', 'membership_id', 'id');
    }

}