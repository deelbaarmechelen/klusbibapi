<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'kb_payments';
    protected $primaryKey = "payment_id";
    static protected $fieldArray = ['payment_id', 'user_id', 'state', 'mode', 'payment_date', 'order_id',
        'amount', 'currency', 'comment', 'expiration_date', 'membership_id', 'loan_id', 'created_at', 'updated_at'
    ];
//    protected $casts = [
//        'payment_date'  => 'date:Y-m-d',
//        'expiration_date' => 'date:Y-m-d',
//    ];
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'payment_date','expiration_date', 'created_at', 'updated_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Payment::$fieldArray);
    }
    public function user() {
        return $this->belongsTo('Api\Model\Contact', 'user_id', 'id');
    }
    public function membership() {
        return $this->belongsTo('Api\Model\Membership', 'membership_id', 'id');
    }
    public function scopeAny($query)
    {
        return $query;
    }
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }
    public function scopeForMembership($query)
    {
        return $query->whereNotNull('membership_id');
    }
    public function scopeForLoan($query)
    {
        return $query->whereNotNull('loan_id');
    }
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }
}