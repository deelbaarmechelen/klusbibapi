<?php

namespace Api;
require_once __DIR__ . '/../../app/env.php';

class Settings
{
    public const ACCOUNT_NBR = 'BE79 5230 8088 4133';
    public const DEFAULT_LOAN_DAYS = 7;

    public const ENROLMENT_AMOUNT = 30; // FIXME: should not be used as amount is read from MembershipType model
    public const MOLLIE_LOCALE = "nl_BE";
    public const CURRENCY = "EUR";
    public const LATEST_TERMS_VERSION = "20210701";
    public const LAST_TERMS_DATE_UPDATE = LAST_TERMS_DATE; // FIXME: how to avoid having to define this twice?

    public const EMAIL_LINK = 'info@klusbib.be';
    public const RESERVATION_EMAIL = 'reservatie@klusbib.be';
    public const WEBPAGE_LINK = WEB_URL;
    public const ENROLMENT_LINK = self::WEBPAGE_LINK . '/#!/lid-worden/form';
    public const RESET_PWD_LINK = self::WEBPAGE_LINK . '/#!/setpwd';
    public const PROFILE_LINK = self::WEBPAGE_LINK . '/#!/profile/';
    public const FACEBOOK_LINK = 'http://www.facebook.com/DeelbaarMechelen';
    public const ENQUETE_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSeXBVSBV-UOGZ7PVssWUe7jvAwAbdqJxrSJUs4GP5NtlQDoqA/viewform';
    public const EVALUATION_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSegXexZqLtG0NvRxgJ7Y53iI530IcRtdvZMqKVc3PnEmRM01g/viewform';
    public const GEN_CONDITIONS_URL = self::WEBPAGE_LINK . '/docs/KlusbibAfspraken-' . self::LATEST_TERMS_VERSION . '.pdf';
    public const PRIVACY_STATEMENT_URL = self::WEBPAGE_LINK . '/docs/PrivacyVerklaring-' . self::LATEST_TERMS_VERSION . '.pdf';
    public const INVENTORY_LINK = INVENTORY_URL;

    static function getLatestTermsDate () {
        return \DateTime::createFromFormat('Ymd', self::LATEST_TERMS_VERSION);
    }

}