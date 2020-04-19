<?php

namespace Api;


class Settings
{
    const ACCOUNT_NBR = 'BE79 5230 8088 4133';
    const DEFAULT_LOAN_DAYS = 7;

    const ENROLMENT_AMOUNT = 30;
    const ENROLMENT_AMOUNT_STRING = "30.00";
    const RENEWAL_AMOUNT = 20;
    const RENEWAL_AMOUNT_STRING = "20.00";
    const STROOM_ENROLMENT_AMOUNT = 0;
    const STROOM_RENEWAL_AMOUNT = 0;
    const MOLLIE_LOCALE = "nl_BE";

    const EMAIL_LINK = 'info@klusbib.be';
    const RESERVATION_EMAIL = 'reservatie@klusbib.be';
    const WEBPAGE_LINK = 'https://www.klusbib.be';
    const ENROLMENT_LINK = self::WEBPAGE_LINK . '/#!/lid-worden/form';
    const FACEBOOK_LINK = 'http://www.facebook.com/Klusbib';
    const PROFILE_LINK = self::WEBPAGE_LINK . '/#!/profile/';
    const ENQUETE_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSeXBVSBV-UOGZ7PVssWUe7jvAwAbdqJxrSJUs4GP5NtlQDoqA/viewform';
    const EVALUATION_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSegXexZqLtG0NvRxgJ7Y53iI530IcRtdvZMqKVc3PnEmRM01g/viewform';
    const GEN_CONDITIONS_URL = self::WEBPAGE_LINK . '/docs/KlusbibAfspraken.pdf';
    const PRIVACY_STATEMENT_URL = self::WEBPAGE_LINK . '/docs/PrivacyVerklaring.pdf';
    const INVENTORY_LINK = 'https://inventory.deelbaarmechelen.be';
}