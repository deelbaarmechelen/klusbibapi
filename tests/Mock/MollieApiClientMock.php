<?php

namespace Tests\Mock;

use \Mollie\Api\MollieApiClient;

class MollieApiClientMock extends MollieApiClient
{
    /**
     * MollieApiClientMock constructor.
     * @param PaymentEndpointMock $payments
     */
    public function __construct()
    {
        $this->payments = new PaymentEndpointMock($this);
    }


}