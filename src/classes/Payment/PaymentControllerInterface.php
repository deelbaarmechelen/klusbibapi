<?php

namespace Api\Payment;


interface PaymentControllerInterface
{
    function getAll($request, $response, $args);
    function getByID($request, $response, $args);
    function create($request, $response, $args);
    function createWithOrderId($request, $response, $args);
}