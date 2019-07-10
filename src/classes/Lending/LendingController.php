<?php

namespace Api\Lending;

use Api\ModelMapper\LendingMapper;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Authorisation;

class LendingController implements LendingControllerInterface
{
    protected $logger;
    protected $token;

    public function __construct($logger, $token)
    {
        $this->logger = $logger;
        $this->token = $token;
    }

    public function getAll($request, $response, $args){
        $this->logger->info("Klusbib GET '/lending' route");

        $authorised = Authorisation::checkLendingAccess($this->token, "list");
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $sortdir = $request->getQueryParam('_sortDir');
        if (!isset($sortdir)) {
            $sortdir = 'desc';
        }
//        $sortfield = $request->getQueryParam('_sortField');
//    if (!User::canBeSortedOn($sortfield) ) {
        $sortfield = 'created_at';
//    }
        $page = $request->getQueryParam('_page');
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $request->getQueryParam('_perPage');
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $lendings = Capsule::table('lendings')->orderBy($sortfield, $sortdir)->get();
        $lendings_page = array_slice($lendings->all(), ($page - 1) * $perPage, $perPage);
        $data = array();
        foreach ($lendings_page as $lending) {
            array_push($data, LendingMapper::mapLendingToArray($lending));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($lendings));
    }
}