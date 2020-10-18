<?php
namespace Api\Model;

abstract class PaymentState
{
    //const NEW = "NEW"; // deprecated
	const OPEN = "OPEN";
	const PENDING = "PENDING"; // Mollie only
	const SUCCESS = "SUCCESS";
	const FAILED = "FAILED";
    const EXPIRED = "EXPIRED"; // Mollie only
    const CANCELED = "CANCELED"; // Mollie only

    const REFUND = "REFUND"; // Mollie only
    const CHARGEBACK = "CHARGEBACK"; // Mollie only
}
