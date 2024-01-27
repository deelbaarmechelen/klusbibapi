<?php

namespace Api\Lending;

use Api\Loan\LoanManager;
use Api\Mail\MailManager;
use Api\Model\Lending;
use Api\Model\ToolType;
use Api\ModelMapper\LendingMapper;
use Api\ModelMapper\ToolMapper;
use Api\ModelMapper\UserMapper;
use Api\Settings;
use Api\Tool\ToolManager;
use Api\User\UserManager;
use Api\Validator\LendingValidator;
use Api\Token\Token;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Authorisation;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class LendingController implements LendingControllerInterface
{
    protected LoggerInterface $logger;
    protected Token $token;
    protected ToolManager $toolManager;
    protected UserManager $userManager;
    protected LoanManager $loanManager;
    protected MailManager $mailManager;

    public function __construct(LoggerInterface $logger, Token $token, ToolManager $toolManager, UserManager $userManager, MailManager $mailManager)
    {
        $this->logger = $logger;
        $this->token = $token;
        $this->toolManager = $toolManager;
        $this->userManager = $userManager;
        $this->mailManager = $mailManager;
        $this->loanManager = LoanManager::instance($this->logger, $this->mailManager);
    }

    public function getAll(RequestInterface $request, ResponseInterface $response, $args) : ResponseInterface {
        $this->logger->info("Klusbib GET '/lendings' route (params=" . \json_encode($request->getQueryParams()) . ")");
        $authorised = Authorisation::checkLendingAccess($this->token, "list");
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(HttpResponseCode::FORBIDDEN);
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
        $userId = $queryParams['user_id'] ?? null;
        $toolId = $queryParams['tool_id'] ?? null;
        $toolType = $queryParams['tool_type'] ?? null;
        $startDate = $queryParams['start_date'] ?? null;
        $active = $queryParams['active'] ?? null;

        $lendings = $this->loanManager->getAllLendings($userId, $toolId, $toolType, $startDate, $active, 
            $sortfield, $sortdir);
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
            return $response->withStatus(HttpResponseCode::FORBIDDEN);
        }

        $lending = \Api\Model\Lending::find($args['lendingId']);
        if (null == $lending) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
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
            return $response->withStatus(HttpResponseCode::FORBIDDEN);
        }
        $data = $request->getParsedBody();
        if (!LendingValidator::isValidLendingData($data, $this->logger, $this->toolManager)) {
            $this->logger->warn("Rejecting invalid lending (data: " . json_encode($data));
            return $response->withStatus(HttpResponseCode::BAD_REQUEST); // Bad request
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
            if ($this->loanManager->hasActiveLending($lending->tool_id)) {
                $this->logger->info('An active lending for tool with id ' . $lending->tool_id . ' already exists');
                return $response->withJson(array('error' => array('status' => 400, 'message' => 'An active lending for that tool already exists')))
                    ->withStatus(HttpResponseCode::BAD_REQUEST);
            }
        }
        $lending = $this->loanManager->createLending($lending);
        //$lending->save();
        if (!$lending) {
            $this->logger->info('Internal error when creating lending for user with id ' . $lending->user_id .
            ' and tool with id/type ' . $lending->tool_id . '/' . $lending->tool_type);
            return $response->withStatus(HttpResponseCode::INTERNAL_ERROR);            
        }
        $this->logger->info('New lending for user with id ' . $lending->user_id .
            ' and tool with id/type ' . $lending->tool_id . '/' . $lending->tool_type . ' successfully saved');
        return $response->withJson(LendingMapper::mapLendingToArray($lending))
            ->withStatus(HttpResponseCode::CREATED);
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
            return $response->withStatus(HttpResponseCode::FORBIDDEN);
        }
        $lending = $this->loanManager->getLendingById($args['lendingId']);
        if (null == $lending) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $data = $request->getParsedBody();
        if (!LendingValidator::isValidLendingData($data, $this->logger, $this->toolManager, false)) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST); // Bad request
        }
        $this->logger->info("Lending request is valid");
        $newStartDate = null;
        if (isset($data["start_date"]) && $lending->start_date != $data["start_date"]) {
            $lending->start_date = $data["start_date"];
            $newStartDate = $data["start_date"];
        }
        $newDueDate = null;
        if (isset($data["due_date"]) && $lending->due_date != $data["due_date"]) {
            $lending->due_date = $data["due_date"];
            $newDueDate = $data["due_date"];
        }
        $newComments = null;
        if (isset($data["comments"]) && $lending->comments != $data["comments"]) {
            $lending->comments = $data["comments"];
            $newComments = $data["comments"];
        }
        $newCreatedBy = null;
        if (isset($data["created_by"]) && $lending->created_by != $data["created_by"]) {
            $lending->created_by = $data["created_by"];
            $newCreatedBy = $data["created_by"];
        }
        $newReturnedDate = null;
        if (isset($data["returned_date"]) && $lending->returned_date != $data["returned_date"]) {
            $lending->returned_date = $data["returned_date"];
            $newReturnedDate = $data["returned_date"];
        }
        $this->loanManager->updateLending($lending, $newStartDate, $newDueDate, $newReturnedDate, $newComments, $newCreatedBy);
        return $response->withJson(LendingMapper::mapLendingToArray($lending))
            ->withStatus(HttpResponseCode::OK);
    }
}