<?php
namespace Api\Model;

abstract class PaymentMode
{
	public const UNKNOWN = "UNKNOWN";
    public const NONE = "NONE";
    public const CASH = "CASH";
    public const KDOBON = "KDOBON";
    public const LETS = "LETS";
    public const MBON = "MBON";
    public const MOLLIE = "MOLLIE";
    public const OVAM = "OVAM";
    public const OTHER = "OTHER";
    public const PAYCONIQ = "PAYCONIQ";
    public const SPONSORING = "SPONSORING";
    public const STROOM = "STROOM";
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
}
