<?php

/**
 * Launches the enrolment operation
 * The user needs to be created prior to this operation
 * This operation is normally terminated by a POST to /enrolment_confirm
 *
 * For online payment with MOLLIE an extra notification is received to process the enrolment
 * as POST to /enrolment/{orderId}
 */
$app->post('/enrolment', \Api\Enrolment\EnrolmentController::class . ':postEnrolment');

/**
 * Manual confirmation for enrolments by TRANSFER or STROOM
 */
$app->post('/enrolment_confirm', \Api\Enrolment\EnrolmentController::class . ':postEnrolmentConfirm');

/**
 * Manual decline for invalid enrolments by STROOM
 */
$app->post('/enrolment_decline', \Api\Enrolment\EnrolmentController::class . ':postEnrolmentDecline');

/**
 * Confirmation from payment processor (Mollie) on enrolment order
 */
$app->post('/enrolment/{orderId}', \Api\Enrolment\EnrolmentController::class . ':postEnrolmentOrder');
