<?php

namespace Api\Lending;

use Api\Model\Lending;
use Api\ModelMapper\LendingMapper;
use Api\Validator\LendingValidator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Authorisation;
use Slim\Http\Request;
use Slim\Http\Response;

class LendingController implements LendingControllerInterface
{
    protected $logger;
    protected $token;
    protected $toolManager;

    public function __construct($logger, $token, $toolManager)
    {
        $this->logger = $logger;
        $this->token = $token;
        $this->toolManager = $toolManager;
    }

    public function getAll(Request $request, Response $response, $args){
        $this->logger->info("Klusbib GET '/lendings' route");

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

    public function getById(Request $request, Response $response, $args)
    {
        $this->logger->info("Klusbib GET '/lendings/id' route");

        $authorised = Authorisation::checkLendingAccess($this->token, Authorisation::OPERATION_READ);
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $lending = \Api\Model\Lending::find($args['lendingId']);
        if (null == $lending) {
            return $response->withStatus(404);
        }
        return $response->withJson(LendingMapper::mapLendingToArray($lending));
    }

    public function create(Request $request, Response $response, $args)
    {
        $this->logger->info("Klusbib " . $request->getMethod() . " " . $request->getRequestTarget()
            . " route. Body: " . $request->getBody()->read(100)
            . ($request->getBody()->getSize() > 100 ? "..." : ""));

        $authorised = Authorisation::checkLendingAccess($this->token, Authorisation::OPERATION_CREATE);
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $data = $request->getParsedBody();
        if (!LendingValidator::isValidLendingData($data, $this->logger, $this->toolManager)) {
            return $response->withStatus(400); // Bad request
        }
        $this->logger->info("Lending request is valid");
        $lending = new Lending();
        $lending->tool_id = $data["tool_id"];
        $lending->user_id = $data["user_id"];
        if (isset($data["start_date"])) {
            $lending->start_date = $data["start_date"];
            if (isset($data["due_date"])) {
                $lending->due_date = $data["due_date"];
            } else {
                $lending->due_date = $lending->start_date; // FIXME: add 1 week
            }
        }
        if (isset($data["comments"])) {
            $lending->comments = $data["comments"];
        }
        if (isset($data["created_by"])) {
            $lending->created_by = $data["created_by"];
        }
        if (isset($data["returned_date"])) {
            $lending->returned_date = $data["returned_date"];
        }
        $lending->save();
        return $response->withJson(LendingMapper::mapLendingToArray($lending))
            ->withStatus(201);
    }
    public function update(Request $request, Response $response, $args)
    {
        $this->logger->info("Klusbib " . $request->getMethod() . " " . $request->getRequestTarget()
            . " route. Body: " . $request->getBody()->read(100)
            . ($request->getBody()->getSize() > 100 ? "..." : ""));

        $authorised = Authorisation::checkLendingAccess($this->token, Authorisation::OPERATION_UPDATE);
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $lending = \Api\Model\Lending::find($args['lendingId']);
        if (null == $lending) {
            return $response->withStatus(404);
        }
        $data = $request->getParsedBody();
        if (!LendingValidator::isValidLendingData($data, $this->logger, $this->toolManager)) {
            return $response->withStatus(400); // Bad request
        }
        $this->logger->info("Lending request is valid");
        if (isset($data["start_date"])) {
            $lending->start_date = $data["start_date"];
        }
        if (isset($data["due_date"])) {
            $lending->due_date = $data["due_date"];
        }
        if (isset($data["comments"])) {
            $lending->comments = $data["comments"];
        }
        if (isset($data["created_by"])) {
            $lending->created_by = $data["created_by"];
        }
        if (isset($data["returned_date"])) {
            $lending->returned_date = $data["returned_date"];
        }
        $lending->save();
        return $response->withJson(LendingMapper::mapLendingToArray($lending))
            ->withStatus(200);
    }
}