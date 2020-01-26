<?php

namespace Api\User;


use Slim\Http\Request;
use Slim\Http\Response;

interface UserControllerInterface
{
    // duplicate name -> which shall we choose?
    function add($request, $response, $args);
    function create($request, $response, $args);

    function getAll (Request $request, Response $response, $args);
    function getById($request, $response, $args);
    function update($request, $response, $args);
    function delete($request, $response, $args);

}