<?php

use Api\Membership\MembershipController;

$app->get('/membership/{membershipId}', MembershipController::class . ':getById');
$app->get('/membership', MembershipController::class . ':getAll');
$app->put('/membership/{membershipId}', MembershipController::class . ':update');
//$app->post('/membership/subscribe', MembershipController::class . ':subscribe');
