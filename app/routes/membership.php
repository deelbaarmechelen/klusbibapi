<?php

use Api\Membership\MembershipController;

$app->get('/membership/{membershipId}', MembershipController::class . ':getById');
$app->get('/membership', MembershipController::class . ':getAll');
//$app->post('/membership/subscribe', MembershipController::class . ':subscribe');
