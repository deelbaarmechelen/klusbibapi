<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/12/18
 * Time: 23:12
 */

namespace Tests\Mock;


use Mollie\Api\Resources\Payment;

class PaymentMock extends Payment
{
    public static $checkoutUrl;
    /**
     * Get the checkout URL where the customer can complete the payment.
     *
     * @return string|null
     */
    public function getCheckoutUrl()
    {

        return PaymentMock::$checkoutUrl;
    }

}