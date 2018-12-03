<?php
namespace Api\Model;

abstract class PaymentState
{
    const NEW = "NEW";
	const OPEN = "OPEN";
	const SUCCESS = "SUCCESS";
	const FAILED = "FAILED";
}
