<?php

namespace Api\Tool;

use Api\Model\Accessory;
use Api\ModelMapper\ToolMapper;

/**
 * Class AccessoryController
 * Tools for which no unique id is used. A stock member is kept to track available identical items
 * The accessory name is preceeded by a unique id 'KB-A-xxx'
 * e.g. screwdriver, hammer, ...
 * @package Api\Tool
 */
class AccessoryController
{
    protected $logger;
    protected $toolManager;
    protected $token;

    public function __construct($logger, ToolManager $toolManager, $token) {
        $this->logger = $logger;
        $this->toolManager = $toolManager;
        $this->token = $token;
    }
    function getAll($request, $response, $args) {

        $this->logger->info("Klusbib GET '/accessories' route");
        $sortdir = $request->getQueryParam('_sortDir');
        if (!isset($sortdir)) {
            $sortdir = 'asc';
        }
        $sortfield = $request->getQueryParam('_sortField');
        if (!Accessory::canBeSortedOn($sortfield) ) {
            $sortfield = 'code';
        }
        $page = $request->getQueryParam('_page');
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $request->getQueryParam('_perPage');
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $showAll = $request->getQueryParam('_all');
        if (isset($showAll) && $showAll == 'true') {
            $showAll = true;
        } else {
            $showAll = false;
        }
        $category = $request->getQueryParam('category');
        $accesories = $this->toolManager->getAllAccessories($showAll, $category, $sortfield, $sortdir, $page, $perPage);
        $accesories_page = array_slice($accesories->all(), ($page - 1) * $perPage, $perPage);

        $data = [];
        foreach ($accesories_page as $accessory) {
            array_push($data, ToolMapper::mapAccessoryToArray($accessory));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', is_array($accesories) || $accesories instanceof \Countable ? count($accesories) : 0);
    }

    function getById($request, $response, $args) {
        $this->logger->info("Klusbib GET '/accessories/id' route");

        try {
            $accessory = $this->toolManager->getAccessoryById($args['accessoryId']);
        } catch (NotFoundException $nfe) {
            return $response->withStatus(404);
        }

        if (null == $accessory) {
            return $response->withStatus(404);
        }

        $data = ToolMapper::mapAccessoryToArray($accessory);

        return $response->withJson($data);
    }

}