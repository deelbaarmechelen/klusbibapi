<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 * @property mixed $created_by
 * @property mixed $contact_id
 * @property mixed $loan_id
 * @property mixed $membership_id
 * @property mixed $created_at
 * @property mixed $type
 * @property mixed $payment_date
 * @property mixed $amount
 * @property mixed $note
 * @property mixed $deposit_id
 * @property mixed $psp_code
 * @property mixed $event_id
 * @property mixed $kb_payment_timestamp
 * @property mixed $kb_mode
 * @property mixed $kb_state
 * @property mixed $kb_expiration_date
 * 
 * @method static Builder any()
 * @method static Builder forOrder($orderId)
 * @method static Builder forMembership()
 * @method static Builder forLoan()
 * 
 * @method static \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|null find($id, $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection findOrFail($id, $columns = ['*'])
 * @method static Builder where(...$params)
 */

class Payment extends Model
{
    protected $table = 'payment';
    protected $primaryKey = "id";
    static protected $fieldArray = ['id', 'contact_id', 'kb_state', 'kb_mode', 'kb_payment_timestamp', 'psp_code',
        'amount', 'note', 'kb_expiration_date', 'membership_id', 'loan_id', 'created_at'
    ];

    /**
     * The name of the "updated at" column. -> set to null to disable
     *
     * @var string
     */
    public const UPDATED_AT = null;
    
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
        'payment_date','payment_timestamp','kb_expiration_date', 'created_at'
    ];

    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, Payment::$fieldArray);
    }

    /**
     * @param $orderId
     * @return Payment
     */
    public static function createNewPayment($orderId, $mode, $amount, $contactId,
        Membership $membership = null): Payment
    {
        $payment = new Payment();
        $payment->kb_mode = $mode;
        $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
        $payment->psp_code = $orderId;
        $payment->contact_id = $contactId;
        $payment->kb_payment_timestamp = new \DateTime();
        $payment->payment_date = new \DateTime();
        $payment->amount = $amount;
        $payment->kb_state = PaymentState::OPEN;
        $payment->type = "PAYMENT";
        if (isset($membership)) {
            $membership->payment()->save($payment);
        } else {
            $payment->save();
        }
        return $payment;
    }
    
    public static function createMembershipFee(Membership $membership): Payment
    {
        $payment = new Payment();
        $payment->contact_id = $membership->contact_id;
        $payment->kb_payment_timestamp = new \DateTime();
        $payment->payment_date = new \DateTime();
        $payment->amount = $membership->subscription->price;
        $payment->type = "FEE";
        $payment->note = "Membership fee.";
        $membership->payment()->save($payment);
        return $payment;
    }

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->type = "PAYMENT";
        $this->created_at = new \DateTime();
        $this->payment_date = new \DateTime();
        $this->kb_payment_timestamp = new \DateTime();
    }

    public function user() {
        return $this->belongsTo(\Api\Model\Contact::class, 'contact_id', 'id');
    }
    public function membership() {
        return $this->belongsTo(\Api\Model\Membership::class, 'membership_id', 'id');
    }
    public function scopeAny($query)
    {
        return $query;
    }
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('psp_code', $orderId);
    }
    public function scopeForMembership($query)
    {
        return $query->whereNotNull('membership_id');
    }
    public function scopeForLoan($query)
    {
        return $query->whereNotNull('loan_id');
    }
    // public function scopeOutOfSync($query)
    // {
    //     return $query->whereNull('last_sync_date')
    //         ->orWhereColumn('last_sync_date', '<', 'updated_at');
    // }
}