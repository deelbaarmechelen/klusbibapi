<?php

namespace Api\Lending;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface LendingControllerInterface
{
    function getAll(RequestInterface $request, ResponseInterface $response, $args);
    function getById(RequestInterface $request, ResponseInterface $response, $args);
    function create(RequestInterface $request, ResponseInterface $response, $args);
    function update(RequestInterface $request, ResponseInterface $response, $args);
}