<?php

namespace Api\Lending;


use Slim\Http\Request;
use Slim\Http\Response;

interface LendingControllerInterface
{
    function getAll(Request $request, Response $response, $args);
    function getById(Request $request, Response $response, $args);
    function create(Request $request, Response $response, $args);
    function update(Request $request, Response $response, $args);
}