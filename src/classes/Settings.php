<?php

namespace Api;


class Settings
{
    const ACCOUNT_NBR = 'BE79 5230 8088 4133';
    const ENROLMENT_AMOUNT = 30;
    const ENROLMENT_AMOUNT_STRING = "30.00";
    const RENEWAL_AMOUNT = 20;
    const RENEWAL_AMOUNT_STRING = "20.00";
    const MOLLIE_LOCALE = "nl_BE";

    const EMAIL_LINK = 'info@klusbib.be';
    const RESERVATION_EMAIL = 'reservation@klusbib.be';
    const WEBPAGE_LINK = 'https://www.klusbib.be';
    const ENROLMENT_LINK = self::WEBPAGE_LINK . '/#!/lid-worden/form';
    const FACEBOOK_LINK = 'http://www.facebook.com/Klusbib';
    const PROFILE_LINK = self::WEBPAGE_LINK . '/#!/profile/';
    const EVALUATION_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSegXexZqLtG0NvRxgJ7Y53iI530IcRtdvZMqKVc3PnEmRM01g/viewform';
    const GEN_CONDITIONS_URL = self::WEBPAGE_LINK . '/docs/KlusbibAfspraken.pdf';
    const PRIVACY_STATEMENT_URL = self::WEBPAGE_LINK . '/docs/PrivacyVerklaring.pdf';
}