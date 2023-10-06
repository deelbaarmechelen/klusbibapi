<?php

namespace Api\Enrolment;


use Api\Model\Contact;
use Api\Mail\MailManager;
use Api\User\UserManager;
use Mollie\Api\MollieApiClient;

class EnrolmentFactory
{
    private $mailMgr;
    private $mollie;
    private $userMgr;

    /**
     * EnrolmentFactory constructor.
     * @param $mailMgr
     * @param $mollie
     */
    public function __construct($mailMgr, $mollie, $userMgr)
    {
        $this->mailMgr = $mailMgr;
        $this->mollie = $mollie;
        $this->userMgr = $userMgr;
    }

    public function createEnrolmentManager($logger, Contact $user = null) {
        return new EnrolmentManager($logger, $user, $this->mailMgr, $this->mollie, $this->userMgr);
    }
}