<?php

namespace Api\Events;


interface EventsControllerInterface
{
    function getAll($request, $response, $args);
    function create($request, $response, $args);
}