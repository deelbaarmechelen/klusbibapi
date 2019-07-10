<?php

namespace Api\Token;


interface TokenControllerInterface
{
    function create($request, $response, $args);
    function createForGuest($request, $response, $args);
}