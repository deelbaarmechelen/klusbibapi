<?php

namespace Tests\Mock;

use \Mollie\Api\MollieApiClient;

class MollieApiClientMock extends MollieApiClient
{
    /**
     * MollieApiClientMock constructor.
     * Customize behaviour by updating (publicly accessible) $payments
     */
    public function __construct()
    {
        $this->payments = new PaymentEndpointMock($this);
    }


}