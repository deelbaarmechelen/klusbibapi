<?php

namespace Api\Product;


interface ProductControllerInterface
{
    // what's the difference between add and create?
    function add($request, $response, $args);
    function create($request, $response, $args);

    function update($request, $response, $args);
    function uploadProductImage($request, $response, $args);

    /**
     * @deprecated
     * Use delete($request, $response, $args) instead
     */
    function deleteByID($request, $response, $args);
    function delete($request, $response, $args);

    function getAll($request, $response, $args);
    function getByID($request, $response, $args);

    /**
     * returns a JSON encoded list of dates at which this product is not available
     */
    function getDisabledDates($request, $response, $args);
}