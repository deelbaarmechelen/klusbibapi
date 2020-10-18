<?php

namespace Api\Exception;


class EnrolmentException extends \Exception
{
    const ALREADY_ENROLLED = 1;
    const UNSUPPORTED_STATE = 2;
    const MOLLIE_EXCEPTION = 3;
    const NOT_ENROLLED = 4;
    const UNKNOWN_USER = 5;
    const UNEXPECTED_PAYMENT_MODE = 6;
    const UNEXPECTED_CONFIRMATION = 7;
    const UNEXPECTED_MEMBERSHIP_TYPE = 8;
    const UNEXPECTED_PAYMENT_STATE = 9;
    const UNKNOWN_PAYMENT = 10;

    public function __construct($message = "", $code = 0, $previous = null) {
        parent::__construct($message, $code, $previous);
    }

}