<?php

namespace Api\Enrolment;


use Api\Model\User;
use Api\Mail\MailManager;
use Mollie\Api\MollieApiClient;

class EnrolmentFactory
{
    private $mailMgr;
    private $mollie;

    /**
     * EnrolmentFactory constructor.
     * @param $mailMgr
     * @param $mollie
     */
    public function __construct($mailMgr, $mollie)
    {
        $this->mailMgr = $mailMgr;
        $this->mollie = $mollie;
    }

    public function createEnrolmentManager($logger, User $user = null) {
        return new EnrolmentManager($logger, $user, $this->mailMgr, $this->mollie);
    }
}