<?php

namespace Api\Consumer;


interface ConsumerControllerInterface
{
    function get($request, $response, $args);

}