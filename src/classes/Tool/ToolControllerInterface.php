<?php

namespace Api\Tool;


interface ToolControllerInterface
{
    function get($request, $response, $args);
    function getById($request, $response, $args);
    function create($request, $response, $args);
    function uploadToolImage($request, $response, $args);
    function update($request, $response, $args);
    function delete($request, $response, $args);
}