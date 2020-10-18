<?php
namespace Api\Model;

abstract class PaymentState
{
    const NEW = "NEW";
	const OPEN = "OPEN";
	const PENDING = "PENDING";
	const SUCCESS = "SUCCESS";
	const FAILED = "FAILED";
    const EXPIRED = "EXPIRED";
    const CANCELED = "CANCELED";
    const REFUND = "REFUND";
    const CHARGEBACK = "CHARGEBACK";
}
