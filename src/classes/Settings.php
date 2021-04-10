<?php

namespace Api;
require_once __DIR__ . '/../../src/env.php';

class Settings
{
    const ACCOUNT_NBR = 'BE79 5230 8088 4133';
    const DEFAULT_LOAN_DAYS = 7;

    const ENROLMENT_AMOUNT = 30; // FIXME: should not be used as amount is read from MembershipType model
    //const RENEWAL_AMOUNT = 20;
    //const STROOM_ENROLMENT_AMOUNT = 0;
    //const STROOM_RENEWAL_AMOUNT = 0;
    const MOLLIE_LOCALE = "nl_BE";
    const CURRENCY = "EUR";

    const EMAIL_LINK = 'info@klusbib.be';
    const RESERVATION_EMAIL = 'reservatie@klusbib.be';
    const WEBPAGE_LINK = WEB_URL;
//    const WEBPAGE_LINK = 'https://www.klusbib.be';
    const ENROLMENT_LINK = self::WEBPAGE_LINK . '/#!/lid-worden/form';
    const RESET_PWD_LINK = self::WEBPAGE_LINK . '/#!/setpwd';
    const PROFILE_LINK = self::WEBPAGE_LINK . '/#!/profile/';
    const FACEBOOK_LINK = 'http://www.facebook.com/DeelbaarMechelen';
    const ENQUETE_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSeXBVSBV-UOGZ7PVssWUe7jvAwAbdqJxrSJUs4GP5NtlQDoqA/viewform';
    const EVALUATION_LINK = 'https://docs.google.com/forms/d/e/1FAIpQLSegXexZqLtG0NvRxgJ7Y53iI530IcRtdvZMqKVc3PnEmRM01g/viewform';
    const GEN_CONDITIONS_URL = self::WEBPAGE_LINK . '/docs/KlusbibAfspraken.pdf';
    const PRIVACY_STATEMENT_URL = self::WEBPAGE_LINK . '/docs/PrivacyVerklaring.pdf';
//    const INVENTORY_LINK = 'https://inventory.deelbaarmechelen.be';
    const INVENTORY_LINK = INVENTORY_URL;
    const LAST_TERMS_DATE_UPDATE = "2019-12-09";
}