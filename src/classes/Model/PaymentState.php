<?php
namespace Api\Model;

abstract class PaymentState
{
    //const NEW = "NEW"; // deprecated
	public const OPEN = "OPEN";
	public const PENDING = "PENDING"; // Mollie only
	public const SUCCESS = "SUCCESS";
	public const FAILED = "FAILED";
    public const EXPIRED = "EXPIRED"; // Mollie only
    public const CANCELED = "CANCELED"; // Mollie only

    public const REFUND = "REFUND"; // Mollie only
    public const CHARGEBACK = "CHARGEBACK"; // Mollie only
}
