<?php

namespace Api\User;

use Api\Model\Membership;
use Api\Model\MembershipType;
use Api\Model\PaymentMode;
use Api\ModelMapper\DeliveryMapper;
use Api\ModelMapper\MembershipMapper;
use Api\Tool\ToolManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\User\UserManager;
use Api\Model\User;
use Api\Model\UserState;
use Api\Model\EmailState;
use Api\ModelMapper\UserMapper;
use Api\ModelMapper\ReservationMapper;
use Api\Validator\UserValidator;
use Api\Exception\ForbiddenException;
use Api\Mail\MailManager;
use Api\Authorisation;
use Api\Token\Token;
use Slim\Http\Request;
use Slim\Http\Response;

class UserController implements UserControllerInterface
{
    protected $logger;
    protected $userManager;
    protected $toolManager;
    protected $token;

    public function __construct($logger, UserManager $userManager, ToolManager $toolManager, $token) {
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->toolManager = $toolManager;
        $this->token = $token;
    }

    public function getAll (Request $request, Response $response, $args) {
        // TODO: remove state, membership_start_date and membership_end_date once clients have been updated
        $this->logger->info("Klusbib GET on '/users' route (params=" . \json_encode($request->getQueryParams()) . ")");
        // query params snipe: deleted=false&company_id=&search=&sort=state&order=desc&offset=0&limit=500
        $authorised = Authorisation::checkUserAccess($this->token, "list", null);
        $email = $request->getQueryParam('email');
        if (!$authorised || isset($email)) {
            // Not authorised? Only allow getUserState
            // email param present? Only interested in state of user with that specific email address
            return $this->getUserState($request, $response, $email);
        }
        $sortdir = $request->getQueryParam('_sortDir');
        if (!isset($sortdir)) {
            $sortdir = 'asc';
        }
        $sortfield = $request->getQueryParam('_sortField');
        if (!User::canBeSortedOn($sortfield) ) {
            $sortfield = 'lastname';
        }
        $page = $request->getQueryParam('_page');
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $request->getQueryParam('_perPage');
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $query = $request->getQueryParam('_query');
//        $userQuery = Capsule::table('users');
        $userQuery = User::notDeleted();
        if (isset($query)) {
            $userQuery->searchName($query);
        }
        $users = $userQuery->orderBy($sortfield, $sortdir)->get();
        $users_page = array_slice($users->all(), ($page - 1) * $perPage, $perPage);
        $data = array();
        foreach ($users_page as $user) {
            array_push($data, UserMapper::mapUserToArray($user));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($users));
    }

    public function getById($request, $response, $args) {
        // TODO: remove state, membership_start_date and membership_end_date once clients have been updated
        $this->logger->info("Klusbib GET on '/users/id' route");
    // 	Authorisation::checkAccessByToken($this->token, ["users.all", "users.read", "users.read.owner"]);
        $authorised = Authorisation::checkUserAccess($this->token, "read", $args['userid']);
        if (!$authorised) {
            $this->logger->warn("Access denied for user " . $args['userid']);
            return $response->withStatus(403);
        }
        if (!is_numeric($args['userid'])) {
            $this->logger->warn("Invalid value provided for user id " . $args['userid']);
            return $response->withStatus(400);
        }
        // FIXME: restrict access to owner only for users.read.owner
        try {
            $user = $this->userManager->getById($args['userid'], false);
            if (null == $user) {
                return $response->withStatus(404);
            }
            $userArray = UserMapper::mapUserToArray($user);
        } catch (\Exception $ex) {
            $this->logger->error('Unexpected error on GET for user ' . $args['userid'] . ': ' . $ex->getMessage());
            return $response->withStatus(500)
                ->withJson(array('error' => $ex->getMessage()));
        }
        $userArray["reservations"] = $this->addUserReservations($user);
        $userArray["deliveries"] = $this->addUserDeliveries($user);
        $userArray["projects"] = $this->addUserProjects($user);

        return $response->withJson($userArray);
    }

    /**
     * @deprecated use create instead
     */
    function add($request, $response, $args){
        return $this->create($request, $response, $args);
    }
    function create($request, $response, $args)
    {
        $this->logger->info("Klusbib POST on '/users' route");
        $data = $request->getParsedBody();
        $this->logger->info("parsedbody=" . json_encode($data));
        $errors = array();
        if (empty($data)
            || !UserValidator::containsMandatoryData($data, $this->logger, $errors)
            || !UserValidator::isValidUserData($data, $this->logger, $errors)) {
            $this->logger->info("errors=" . json_encode($errors));

            return $response->withStatus(400)// Bad request
            ->withJson($errors);
        }

        $isAdmin = false;
        $user = new User;
        $authorised = Authorisation::checkUserAccess($this->token, "create", null);
        if ($authorised) {
            // check if authenticated user is also admin
            $currentUser = User::find($this->token->getSub());
            if (!isset($currentUser)) {
                $this->logger->warn("No user found for token " + $this->token->getSub());
                return $response->withStatus(403);
            }
            if ($currentUser->isAdmin()) {
                $isAdmin = true;
            }
            // disable notifications for user creations by admin
            $sendNotification = FALSE;
            $sendEmailVerification = FALSE;
        }
        if ($this->isWebEnrolment($authorised, $data)) {
            $user->state = UserState::CHECK_PAYMENT;
            if (!isset($data["payment_mode"])) {
                $data["payment_mode"] = \Api\Model\PaymentMode::UNKNOWN;
            }

            $user->role = 'member'; // only members can be created through web enrolment
//            $data["membership_start_date"] = strftime('%Y-%m-%d', time());
            if (!isset($data["accept_terms"]) || $data["accept_terms"] !== true) {
                $this->logger->warn('user ' . $data["firstname"] . ' ' . $data["lastname"] . ' did not accept terms (accept_terms=' . $data["accept_terms"] . ')');
                return $response->withStatus(400)
                    ->withJson(array('error' => array('status' => 400, 'message' => "user should accept terms")));
            } else {
                // set date on which terms were accepted
                // FIXME: should be moved to membership entity?
                $user->accept_terms_date = date('Y-m-d');
            }
            $mailmgr = new MailManager(null, null, $this->logger);
            $sendNotification = TRUE;
            $sendEmailVerification = FALSE;
        } else {
            // set default values
            if (!isset($data["state"])) {
                $user->state = UserState::DISABLED;
            }

        }
        if (isset($data["email"])) {
            $this->logger->debug('Checking user email ' . $data["email"] . ' already exists');
            $userByEmail = User::where('email', $data["email"])->first();
            if (isset($userByEmail)) {
                $this->logger->info('user with email ' . $data["email"] . ' already exists');
                // user already exists
                if ($userByEmail->state == UserState::DELETED
                  || $userByEmail->state == UserState::DISABLED
                  || ($userByEmail->state == UserState::CHECK_PAYMENT
                        && $userByEmail->payment_mode != PaymentMode::MOLLIE)
                  || ($userByEmail->state == UserState::CHECK_PAYMENT
                        && $userByEmail->payment_mode == PaymentMode::MOLLIE
                        && strtotime($userByEmail->updated_at) < strtotime('-1 hour'))) {
                    // just remove the user to allow enrolment to restart
                    // For users that already initiated a Mollie payment, wait at least 1 hour until mollie payment is expired
                    $userByEmail->delete();
                } else {
                    return $response->withJson(array('error' => array('status' => 409, 'message' => 'A user with that email already exists')))
                        ->withStatus(409);
                }
            } else {
                $this->logger->debug('No user found with email ' . $data["email"]);
            }
        }
        // TODO: else : check user exists based on name? or registration id?

        if (!isset($data["user_id"]) || empty($data["user_id"])) {
            $max_user_id = Capsule::table('users')->max('user_id');
            $user->user_id = $max_user_id + 1;
            $this->logger->info("New user will be assigned id " . $user->user_id);
        } else {
            // check user_id is numeric
            if (!is_numeric($data["user_id"])) {
                return $response->withStatus(400)
                    ->withJson(array('error' => array('status' => 400, 'message' => "user_id is not numeric")));
            }
        }
//        if (!empty($data["membership_start_date"])) {
//            $user->membership_start_date = $data["membership_start_date"];
//            if (!empty($data["membership_end_date"])) {
//                $user->membership_end_date = $data["membership_end_date"];
//            } else { // default to 1 year membership
//                $data["membership_end_date"] = \Api\Enrolment\EnrolmentManager::getMembershipEndDate($user->membership_start_date);
//            }
//        }
        UserMapper::mapArrayToUser($data, $user, $isAdmin, $this->logger);
        $this->logger->debug('Creating user ' . \json_encode($user));

        $this->userManager->create($user);
        // For backward compatibility: create membership at once if start date is provided
        // FIXME: check if this can be removed and call "POST enrolment" instead
//        if (!empty($user->membership_start_date) && empty($user->active_membership)) {
//            // create membership
//            $status = MembershipMapper::getMembershipStatus($user->state, $user->user_id);
//            // FIXME: membership type not known yet. Could be stroom if payment mode STROOM is chosen in next step
//            \Api\Enrolment\EnrolmentManager::createMembership(MembershipType::regular(), $user->membership_start_date,
//                $user->membership_end_date, $user, $status);
//
//        }
        $this->logger->info("User created!");
        if ($sendEmailVerification) {
            $this->logger->info("Sending email verification");
            $sub = $user->user_id;
            $scopes = ["auth.confirm"];
            $result = $mailmgr->sendEmailVerification($user->user_id, $user->firstname, $user->email,
                Token::generateToken($scopes, $sub));
            $this->logger->info('Sending email verification result: ' . $mailmgr->getLastMessage());
        }
        if ($sendNotification) {
            $this->logger->info("Sending email notification - user created");
            // Notification to be sent to Klusbib team of new enrolment
            $result = $mailmgr->sendEnrolmentNotification(ENROLMENT_NOTIF_EMAIL, $user);
            $this->logger->info('Sending enrolment notification result: ' . $mailmgr->getLastMessage());
        }
        $resourceUri = '/users/' . $user->user_id;
        return $response->withAddedHeader('Location', $resourceUri)
            ->withJson(UserMapper::mapUserToArray($user))
            ->withStatus(201);
    }

    function update($request, $response, $args) {
        // TODO: remove state, membership_start_date and membership_end_date once clients have been updated
        $this->logger->info("Klusbib PUT on '/users/id' route");

        if (false === $this->token->hasScope(["users.all", "users.update", "users.update.owner", "users.update.password"])) {
            return $response->withStatus(403)->write("Token not allowed to update users.");
        }

        $currentUser = \Api\Model\User::find($this->token->getSub());

        $user = \Api\Model\User::find($args['userid']);
        if (null == $user) {
            return $response->withStatus(404);
        }

        if (false === $this->token->hasScope(["users.all", "users.update"]) &&
            $user->user_id != $this->token->decoded->sub) {
            return $response->withStatus(403)->write("Token sub doesn't match user.");
        }
        $data = $request->getParsedBody();
        $errors = array();
        if (empty($data) || !UserValidator::isValidUserData($data, $this->logger, $errors)) {
            $this->logger->info("errors=" . json_encode($errors));
            return $response->withStatus(400)->withJson($errors); // Bad request
        }

        UserMapper::mapArrayToUser($data, $user, $currentUser->isAdmin(), $this->logger);
        $this->userManager->update($user);

        // update membership
        if ($membership = Membership::find($user->active_membership)) {
            MembershipMapper::mapUserArrayToMembership($data, $membership, $currentUser->isAdmin(), $this->logger);
            $membership->save();
        }

        return $response->withJson(UserMapper::mapUserToArray($user));
    }

    function delete($request, $response, $args) {
        $this->logger->info("Klusbib DELETE on '/users/id' route");

        if (false === $this->token->hasScope(["users.all", "users.delete"])) {
            throw new ForbiddenException("Token not allowed to delete users.", 403);
        }

        $user = \Api\Model\User::find($args['userid']);
        if (null == $user) {
            return $response->withStatus(204);
        }

        // if last user on membership, mark membership as cancelled prior to user removal
        if (isset($user->active_membership)
         && $membership = Membership::find($user->active_membership)) {
            $user->activeMembership()->dissociate($membership);
            $user->save();
            if ($membership->members()->count() <= 1) {
                $membership->status = Membership::STATUS_CANCELLED;
                $membership->save();
            }
        }
        $this->userManager->delete($user);
        return $response->withStatus(200);
    }

    /**
     * @param $request
     * @param $response
     * @return mixed
     */
    protected function getUserState($request, $response, $email)
    {
        // TODO: create method to return membership state instead
        $this->logger->warn("User state is deprecated and replaced by membership status");
        if (Authorisation::checkUserAccess($this->token, "read.state", null)) {
            // Allow read of state to resume enrolment
            $email = $request->getQueryParam('email');
            if (!isset($email)) {
                return $response->withStatus(400)
                    ->withJson(array('message' => "Missing email parameter"));
            }
            $user = Capsule::table('users')->where('email', $email)->first();
            if (!isset($user)) {
                return $response->withStatus(404)
                    ->withJson(array('message' => "Unknown email"));
            }

            return $response->withJson(array("user_id" => $user->user_id,
                "state" => $user->state,
                "membership_end_date" => $user->membership_end_date
            ));
        }
        $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
        return $response->withStatus(403);
    }

    /**
     * Returns true if the request should be considered as an unauthenticated web enrolment
     * @param $authorised
     * @param $data
     * @param $webEnrolment
     */
    private function isWebEnrolment($authorised, $data): bool
    {
        if (!$authorised || isset($data["webenrolment"])) {
            return true;
        }
        return false;
    }

    /**
     * @param $user
      * @return mixed
     */
    private function addUserReservations($user)
    {
        $reservationsArray = array();
        foreach ($user->reservations as $reservation) {
            $reservationData = ReservationMapper::mapReservationToArray($reservation);
            $tool = $this->toolManager->getById($reservationData['tool_id']);
            if (isset($tool)) {
                $reservationData['tool_code'] = $tool->code;
                $reservationData['tool_name'] = $tool->name;
                $reservationData['tool_brand'] = $tool->brand;
                $reservationData['tool_type'] = $tool->type;
                $reservationData["tool_size"] = $tool->size;
                $reservationData["tool_fee"] = $tool->fee;
                $reservationData["deliverable"] = $tool->deliverable;
            }

            array_push($reservationsArray, $reservationData);
        }
        return $reservationsArray;
    }
    private function addUserDeliveries($user)
    {
        $deliveriesArray = array();
        foreach ($user->deliveries as $delivery) {
            $deliveryData = DeliveryMapper::mapDeliveryToArray($delivery);
            array_push($deliveriesArray, $deliveryData);
        }
        return $deliveriesArray;
    }
    private function addUserProjects($user)
    {
        $projectsArray = array();
        $this->logger->info(\json_encode($user->projects));
        foreach ($user->projects as $project) {
            $this->logger->info(\json_encode($project));
            $projectData = array("id" => $project->id, "name" => $project->name);

            //$projectData = ProjectMapper::mapDeliveryToArray($project);
            array_push($projectsArray, $projectData);
        }
        return $projectsArray;
    }
}