<?php

namespace Api\User;


interface UserControllerInterface
{
    function get ($request, $response, $args);
    function getById($request, $response, $args);
    function create($request, $response, $args);
    function update($request, $response, $args);
    function delete($request, $response, $args);

}