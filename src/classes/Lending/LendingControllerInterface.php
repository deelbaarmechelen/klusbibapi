<?php

namespace Api\Lending;


interface LendingControllerInterface
{
    function getAll($request, $response, $args);
}