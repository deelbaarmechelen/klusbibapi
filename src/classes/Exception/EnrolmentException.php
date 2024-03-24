<?php

namespace Api\Exception;


class EnrolmentException extends \Exception
{
    public const ALREADY_ENROLLED = 1;
    public const UNSUPPORTED_STATE = 2;
    public const MOLLIE_EXCEPTION = 3;
    public const NOT_ENROLLED = 4;
    public const UNKNOWN_USER = 5;
    public const UNEXPECTED_PAYMENT_MODE = 6;
    public const UNEXPECTED_CONFIRMATION = 7;
    public const UNEXPECTED_MEMBERSHIP_TYPE = 8;
    public const UNEXPECTED_PAYMENT_STATE = 9;
    public const UNKNOWN_PAYMENT = 10;
    public const INCOMPLETE_USER_DATA = 11;
    public const ACCEPT_TERMS_MISSING = 12;
    public const DUPLICATE_REQUEST = 13;
    public const UNEXPECTED_START_DATE = 14;

    public function __construct($message = "", $code = 0, $previous = null) {
        parent::__construct($message, $code, $previous);
    }

}