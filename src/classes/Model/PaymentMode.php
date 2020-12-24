<?php
namespace Api\Model;

abstract class PaymentMode
{
	const UNKNOWN = "UNKNOWN";
    const NONE = "NONE";
    const CASH = "CASH";
    const KDOBON = "KDOBON";
    const LETS = "LETS";
    const MBON = "MBON";
    const MOLLIE = "MOLLIE";
    const OVAM = "OVAM";
    const OTHER = "OTHER";
    const PAYCONIQ = "PAYCONIQ";
    const SPONSORING = "SPONSORING";
    const STROOM = "STROOM";
    const TRANSFER = "TRANSFER";

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
}
