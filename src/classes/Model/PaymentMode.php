<?php
namespace Api\Model;

abstract class PaymentMode
{
	const UNKNOWN = "UNKNOWN";
    const OTHER = "OTHER";
    const SPONSORING = "SPONSORING";
	const MOLLIE = "MOLLIE";
	const CASH = "CASH";
	const TRANSFER = "TRANSFER";
	const OVAM = "OVAM";
	const LETS = "LETS";
    const MBON = "MBON";
	const PAYCONIQ = "PAYCONIQ";
	const STROOM = "STROOM";
}
