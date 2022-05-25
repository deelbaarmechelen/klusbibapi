<?php

use Api\Reservation\ReservationController;

$app->get('/reservations', ReservationController::class . ':getAll');

$app->get('/reservations/{reservationid}', ReservationController::class . ':getByID');

$app->post('/reservations', ReservationController::class . ':create');

$app->put('/reservations/{reservationid}', ReservationController::class . ':update');

$app->delete('/reservations/{reservationid}', ReservationController::class . ':delete');



