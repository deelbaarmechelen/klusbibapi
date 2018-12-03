<?php

namespace Tests\Mock;

use Mollie\Api\Endpoints\PaymentEndpoint;
use \Mollie\Api\MollieApiClient;

class PaymentEndpointMock extends PaymentEndpoint
{
    protected $client;

    public static $paymentData;
    public static $payment;
    /**
     * PaymentEndpointMock constructor.
     */
    public function __construct(MollieApiClient $api)
    {
        $this->client = $api;
    }


    /**
     * Creates a payment in Mollie.
     *
     * @param array $data An array containing details on the payment.
     * @param array $filters
     *
     * @return Payment
     * @throws ApiException
     */
    public function create(array $data = [], array $filters = [])
    {
        PaymentEndpointMock::$paymentData = $data;
        return new PaymentMock($this->client);
    }

    /**
     * Retrieve a single payment from Mollie.
     *
     * Will throw a ApiException if the payment id is invalid or the resource cannot be found.
     *
     * @param string $paymentId
     * @param array $parameters
     * @return Payment
     * @throws ApiException
     */
    public function get($paymentId, array $parameters = [])
    {
        if (empty($paymentId) || strpos($paymentId, parent::RESOURCE_ID_PREFIX) !== 0) {
            throw new ApiException("Invalid payment ID: '{$paymentId}'. A payment ID should start with '" . parent::RESOURCE_ID_PREFIX . "'.");
        }

        return self::$payment;
    }

}