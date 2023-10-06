<?php

namespace Api\Lending;

use Api\Model\Lending;
use Api\Model\ToolType;
use Api\ModelMapper\LendingMapper;
use Api\ModelMapper\ToolMapper;
use Api\ModelMapper\UserMapper;
use Api\Settings;
use Api\Tool\ToolManager;
use Api\User\UserManager;
use Api\Validator\LendingValidator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Authorisation;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class LendingController implements LendingControllerInterface
{
    protected $logger;
    protected $token;
    protected $toolManager;
    protected $userManager;

    public function __construct($logger, $token, ToolManager $toolManager, UserManager $userManager)
    {
        $this->logger = $logger;
        $this->token = $token;
        $this->toolManager = $toolManager;
        $this->userManager = $userManager;
    }

    public function getAll(RequestInterface $request, ResponseInterface $response, $args){
        $this->logger->info("Klusbib GET '/lendings' route (params=" . \json_encode($request->getQueryParams()) . ")");

        $authorised = Authorisation::checkLendingAccess($this->token, "list");
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        parse_str($request->getUri()->getQuery(), $queryParams);
        $sortdir = $queryParams['_sortDir'] ?? null;
        if (!isset($sortdir)) {
            $sortdir = 'desc';
        }
        $sortfield = $queryParams['_sortField'] ?? null;
        if (!Lending::canBeSortedOn($sortfield) ) {
            $sortfield = 'created_at';
        }
        $page = $queryParams['_page'] ?? null;
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $queryParams['_perPage'] ?? null;
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $expandTool = $queryParams['_expandTool'] ?? null;
        if (!isset($expandTool)) {
            $expandTool = false; // tool is remote, so default to false
        } else {
            $expandTool = filter_var($expandTool, FILTER_VALIDATE_BOOLEAN);
        }
        $expandUser = $queryParams['_expandUser'] ?? null;
        if (!isset($expandUser)) {
            $expandUser = true; // user is local, so default to true
        } else {
            $expandUser = filter_var($expandUser, FILTER_VALIDATE_BOOLEAN);
        }
        $query = Lending::valid();
        $userId = $queryParams['user_id'] ?? null;
        $toolId = $queryParams['tool_id'] ?? null;
        $toolType = $queryParams['tool_type'] ?? null;
        $startDate = $queryParams['start_date'] ?? null;
        $active = $queryParams['active'] ?? null;
        if (isset($userId)) {
            $query = $query->withUser($userId);
        }
        if (isset($toolId)) {
            if (isset($toolType)) {
                $query = $query->withTool($toolId, $toolType);
            } else {
                $query = $query->withTool($toolId); // defaults to type TOOL
            }
        }
        if (isset($startDate)) {
            $query = $query->withStartDate($startDate);
        }
        if (isset($active)) {
            $query = $query->active();
        }
        $lendings = $query->orderBy($sortfield, $sortdir)->get();
        $lendings_page = array_slice($lendings->all(), ($page - 1) * $perPage, $perPage);
        $data = array();
        foreach ($lendings_page as $lending) {
            $lendingData = LendingMapper::mapLendingToArray($lending);
            if ($expandTool) {
                // lookup tool and add it to data
                $tool = $this->toolManager->getByIdAndType($lending->tool_id, $lending->tool_type);
                if (isset($tool)) {
                    if ($tool->tool_type = ToolType::TOOL) {
                        $lendingData['tool'] = ToolMapper::mapToolToArray($tool);
                    } else if ($tool->tool_type = ToolType::ACCESSORY) {
                        $lendingData['tool'] = ToolMapper::mapAccessoryToArray($tool);
                    }
                }
            }
            if ($expandUser) {
                $user = $this->userManager->getByIdNoSync($lending->user_id);

                if (isset($user)) {
                    $lendingData['user'] = UserMapper::mapUserToArrayMinimal($user);
                }
            }
            array_push($data, $lendingData);
        }
        $this->logger->info(count($lendings) . ' lending(s) found!');
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($lendings));
    }

    public function getById(RequestInterface $request, ResponseInterface $response, $args)
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
        $this->logger->info('lending found for id ' . $lending->lending_id);

        return $response->withJson(LendingMapper::mapLendingToArray($lending));
    }

    // deprecated: lendings created at inventory and synced by sync_loans / sync_inventory batch 
    public function create(RequestInterface $request, ResponseInterface $response, $args)
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
            $this->logger->warn("Rejecting invalid lending (data: " . json_encode($data));
            return $response->withStatus(400); // Bad request
        }
        $this->logger->info("Lending request is valid");
        $lending = new Lending();
        $lending->tool_id = $data["tool_id"];
        $lending->tool_type = $data["tool_type"];
        $lending->user_id = $data["user_id"];
        if (!empty($data["start_date"])) {
            $this->logger->info("Start date is set to " . $data["start_date"]);
            $lending->start_date = $data["start_date"];
        } else {
            $lending->start_date = date('Y-m-d'); // default to current date
        }
        if (isset($data["due_date"])) {
            $lending->due_date = $data["due_date"];
        } else {
            $dueDate = strtotime($lending->start_date);
            $dueDate = strtotime("+" . Settings::DEFAULT_LOAN_DAYS . " day", $dueDate);
            $lending->due_date = date('Y-m-d', $dueDate);
        }
        if (isset($data["comments"])) {
            $lending->comments = $data["comments"];
        }
        if (isset($data["created_by"])) {
            $lending->created_by = $data["created_by"];
        }
        if (isset($data["returned_date"])) {
            $lending->returned_date = $data["returned_date"];
        } else {
            // creating an active lending -> make sure no other active lending exists for this tool
            $activeLendings = Lending::active()->withTool($lending->tool_id, $lending->tool_type)->count();
            if ($activeLendings> 0 && $lending->tool_type == ToolType::TOOL) {
                $this->logger->info('An active lending for tool with id ' . $lending->tool_id . ' already exists');
                return $response->withJson(array('error' => array('status' => 400, 'message' => 'An active lending for that tool already exists')))
                    ->withStatus(400);
            }
        }
        $lending->save();
        $this->logger->info('New lending for user with id ' . $lending->user_id .
            ' and tool with id/type ' . $lending->tool_id . '/' . $lending->tool_type . ' successfully saved');
        return $response->withJson(LendingMapper::mapLendingToArray($lending))
            ->withStatus(201);
    }
    // deprecated: lendings updated at inventory and synced by sync_loans / sync_inventory batch 
    public function update(RequestInterface $request, ResponseInterface $response, $args)
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
        if (!LendingValidator::isValidLendingData($data, $this->logger, $this->toolManager, false)) {
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