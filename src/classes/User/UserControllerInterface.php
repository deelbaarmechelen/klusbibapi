<?php

namespace Api\User;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;

interface UserControllerInterface
{
    // duplicate name -> which shall we choose?
    function add(RequestInterface $request, ResponseInterface $response, $args);
    function create(RequestInterface $request, ResponseInterface $response, $args);

    function getAll (RequestInterface $request, ResponseInterface $response, $args);
    function getById(RequestInterface $request, ResponseInterface $response, $args);
    function update(RequestInterface $request, ResponseInterface $response, $args);
    function delete(RequestInterface $request, ResponseInterface $response, $args);

}