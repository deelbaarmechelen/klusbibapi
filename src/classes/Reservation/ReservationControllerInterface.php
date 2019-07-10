<?php

namespace Api\Reservation;


interface ReservationControllerInterface
{
    function getAll($request, $response, $args);
    function getByID($request, $response, $args);
    function create($request, $response, $args);
    function update($request, $response, $args);
    function delete($request, $response, $args);
}