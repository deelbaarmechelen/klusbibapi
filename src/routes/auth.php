<?php

$app->post("/auth/reset", \Api\Authentication\PasswordResetController::class . ":postResetPassword");
$app->get("/auth/reset/{userId}", \Api\Authentication\PasswordResetController::class . ":getResetPassword");

$app->post('/auth/verifyemail', \Api\Authentication\VerifyEmailController::class . ":verifyEmail");
$app->get('/auth/confirm/{userId}', \Api\Authentication\VerifyEmailController::class . ":confirmEmail");

// $app->get("/auth/password/reset", "PasswordResetController:getResetPassword")->setName("auth.password.reset");
// $app->post("/auth/password/reset", "PasswordResetController:postResetPassword");

