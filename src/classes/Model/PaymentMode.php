<?php
namespace Api\Model;

abstract class PaymentMode
{
	const UNKNOWN = "UNKNOWN";
	const MOLLIE = "MOLLIE";
	const CASH = "CASH";
	const TRANSFER = "TRANSFER";
	const OVAM = "OVAM";
	const LETS = "LETS";
	const PAYCONIQ = "PAYCONIQ";
}
