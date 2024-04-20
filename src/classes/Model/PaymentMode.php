<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder where(...$params)
 * @method static Builder whereRaw(...$params)
 */
class PaymentMode extends Model
{
    protected $table = 'payment_method';
    protected $primaryKey = "id";
    static protected $fieldArray = ['id', 'name', 'is_active'
    ];
    public $timestamps = false;
    
    public static function canBeSortedOn($field) {
        if (!isset($field)) {
            return false;
        }
        return in_array($field, PaymentMode::$fieldArray);
    }

    // Klusbib legacy payment mode values
    public const UNKNOWN = "UNKNOWN";
    public const NONE = "NONE";
    public const CASH = "CASH";
    public const KDOBON = "KDOBON";
    public const LETS = "LETS";
    public const MBON = "MBON";
    public const MOLLIE = "MOLLIE";
    public const OVAM = "OVAM"; // deprecated
    public const OTHER = "OTHER";
    public const PAYCONIQ = "PAYCONIQ";
    public const SPONSORING = "SPONSORING";
    public const STROOM = "STROOM"; // deprecated
    public const TRANSFER = "TRANSFER";

    static function isValidPaymentMode($paymentMode) {
        return $paymentMode == PaymentMode::UNKNOWN
            || $paymentMode == PaymentMode::NONE
            || $paymentMode == PaymentMode::CASH
            || $paymentMode == PaymentMode::KDOBON
            || $paymentMode == PaymentMode::LETS
            || $paymentMode == PaymentMode::MBON
            || $paymentMode == PaymentMode::MOLLIE
            || $paymentMode == PaymentMode::OVAM
            || $paymentMode == PaymentMode::OTHER
            || $paymentMode == PaymentMode::PAYCONIQ
            || $paymentMode == PaymentMode::SPONSORING
            || $paymentMode == PaymentMode::STROOM
            || $paymentMode == PaymentMode::TRANSFER;
    }
    static function getPaymentModes() : array {
        $paymentModes = [
            PaymentMode::UNKNOWN,
            PaymentMode::NONE,
            PaymentMode::CASH,
            PaymentMode::KDOBON,
            PaymentMode::LETS,
            PaymentMode::MBON,
            PaymentMode::MOLLIE,
            PaymentMode::OVAM,
            PaymentMode::OTHER,
            PaymentMode::PAYCONIQ,
            PaymentMode::SPONSORING,
            PaymentMode::STROOM,
            PaymentMode::TRANSFER
        ];
        return $paymentModes;
    }
    static public function none() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::NONE.'%'])->firstOrFail();
    }
    static public function cash() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::CASH.'%'])->firstOrFail();
    }
    static public function kdobon() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::KDOBON.'%'])->firstOrFail();
    }
    static public function lets() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::LETS.'%'])->firstOrFail();
    }
    static public function mbon() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::MBON.'%'])->firstOrFail();
    }
    static public function mollie() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::MOLLIE.'%'])->firstOrFail();
    }
    static public function ovam() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::OVAM.'%'])->firstOrFail();
    }
    static public function payconiq() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::PAYCONIQ.'%'])->firstOrFail();
    }
    static public function sponsoring() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::SPONSORING.'%'])->firstOrFail();
    }
    static public function stroom() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::STROOM.'%'])->firstOrFail();
    }
    static public function transfer() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::TRANSFER.'%'])->firstOrFail();
    }
    static public function unknown() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::UNKNOWN.'%'])->firstOrFail();
    }
    static public function other() {
	    return PaymentMode::whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.self::OTHER.'%'])->firstOrFail();
    }
    public static function getPaymentMethodId($mode) : int {
        return PaymentMode::getPaymentMethod($mode)->id;
    }
    public static function getPaymentMethod($mode) : PaymentMode {
        if (!PaymentMode::isValidPaymentMode($mode)) {
            return PaymentMode::unknown();
        }
        switch ($mode) {
            case self::MOLLIE:
                return PaymentMode::mollie();
            case self::PAYCONIQ:
                return PaymentMode::payconiq();
            case self::TRANSFER:
                return PaymentMode::transfer();
            case self::CASH:
                return PaymentMode::cash();
            case self::NONE:
                return PaymentMode::none();
            case self::KDOBON:
                return PaymentMode::kdobon();
            case self::LETS:
                return PaymentMode::lets();
            case self::MBON:
                return PaymentMode::mbon();
            case self::OVAM:
                return PaymentMode::ovam();
            case self::OTHER:
                return PaymentMode::other();
            case self::SPONSORING:
                return PaymentMode::sponsoring();
            case self::STROOM:
                return PaymentMode::stroom();
            default:
                return PaymentMode::unknown();                     
        }
    }    
}
