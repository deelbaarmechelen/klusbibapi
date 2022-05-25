<?php

namespace Api\Product;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ProductControllerInterface
{
    // what's the difference between add and create?
    function add(RequestInterface $request, ResponseInterface $response, $args);
    function create(RequestInterface $request, ResponseInterface $response, $args);

    function update(RequestInterface $request, ResponseInterface $response, $args);
    function uploadProductImage(RequestInterface $request, ResponseInterface $response, $args);

    /**
     * @deprecated
     * Use delete($request, $response, $args) instead
     */
    function deleteByID(RequestInterface $request, ResponseInterface $response, $args);
    function delete(RequestInterface $request, ResponseInterface $response, $args);

    function getAll(RequestInterface $request, ResponseInterface $response, $args);
    function getByID(RequestInterface $request, ResponseInterface $response, $args);

    /**
     * returns a JSON encoded list of dates at which this product is not available
     */
    function getDisabledDates(RequestInterface $request, ResponseInterface $response, $args);
}