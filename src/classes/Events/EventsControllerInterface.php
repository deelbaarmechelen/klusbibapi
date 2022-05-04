<?php

namespace Api\Events;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface EventsControllerInterface
{
    function getAll(RequestInterface $request, ResponseInterface $response, $args);
    function create(RequestInterface $request, ResponseInterface $response, $args);
}