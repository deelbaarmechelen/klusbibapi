<?php

namespace Api\User;

use Api\Tool\ToolManager;
use Api\Loan\LoanManager;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\User\UserManager;
use Api\Model\Contact;
use Api\Model\Membership;
use Api\Model\MembershipState;
use Api\Model\MembershipType;
use Api\Model\PaymentMode;
use Api\Model\PaymentState;
use Api\Model\UserState;
use Api\Model\EmailState;
use Api\ModelMapper\UserMapper;
use Api\ModelMapper\ReservationMapper;
use Api\ModelMapper\DeliveryMapper;
use Api\ModelMapper\MembershipMapper;
use Api\Validator\UserValidator;
use Api\Exception\ForbiddenException;
use Api\Mail\MailManager;
use Api\Authorisation;
use Api\Token\Token;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class UserController implements UserControllerInterface
{
    protected $logger;
    protected $userManager;
    protected $toolManager;
    protected $loanManager;
    protected $token;

    public function __construct($logger, UserManager $userManager, ToolManager $toolManager, Token $token, LoanManager $loanManager) {
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->toolManager = $toolManager;
        $this->token = $token;
        $this->loanManager = $loanManager;
    }

    public function getAll (RequestInterface $request, ResponseInterface $response, $args) {
        // TODO: remove state, membership_start_date and membership_end_date once clients have been updated
        $this->logger->info("Klusbib GET on '/users' route (params=" . \json_encode($request->getQueryParams(), JSON_THROW_ON_ERROR) . ")");
        // query params snipe: deleted=false&company_id=&search=&sort=state&order=desc&offset=0&limit=500
        $authorised = Authorisation::checkUserAccess($this->token, "list", null);
        parse_str($request->getUri()->getQuery(), $queryParams);
        $email = $queryParams['email'] ??  null;
        if (!$authorised || isset($email)) {
            // Not authorised? Only allow getUserState
            // email param present? Only interested in state of user with that specific email address
            return $this->getUserState($request, $response, $email);
        }
        $sortdir = $queryParams['_sortDir'] ??  null;
        if (!isset($sortdir)) {
            $sortdir = 'asc';
        }
        $sortfield = $queryParams['_sortField'] ??  null;
        if (!Contact::canBeSortedOn($sortfield) ) {
            $sortfield = 'last_name';
        }
        $page = $queryParams['_page'] ??  null;
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $queryParams['_perPage'] ??  null;
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $query = $queryParams['_query'] ??  null;
        $userQuery = Contact::notDeleted();
        if (isset($query)) {
            $userQuery->searchName($query);
        }
        $users = $userQuery->orderBy($sortfield, $sortdir)->get();
        $users_page = array_slice($users->all(), ($page - 1) * $perPage, $perPage);
        $data = [];
        foreach ($users_page as $user) {
            array_push($data, UserMapper::mapUserToArray($user));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($users));
    }

    public function getById(RequestInterface $request, ResponseInterface $response, $args) {
        // TODO: remove state, membership_start_date and membership_end_date once clients have been updated
        $this->logger->info("Klusbib GET on '/users/id' route");
        $authorised = Authorisation::checkUserAccess($this->token, "read", $args['userid']);
        if (!$authorised) {
            $this->logger->warn("Access denied for user " . $args['userid']);
            return $response->withStatus(HttpResponseCode::FORBIDDEN);
        }
        if (!is_numeric($args['userid'])) {
            $this->logger->warn("Invalid value provided for user id " . $args['userid']);
            return $response->withStatus(HttpResponseCode::BAD_REQUEST);
        }
        // FIXME: restrict access to owner only for users.read.owner
        try {
            $user = $this->userManager->getById($args['userid'], false);
            if (null == $user) {
                return $response->withStatus(HttpResponseCode::NOT_FOUND);
            }
            $userArray = UserMapper::mapUserToArray($user);
        } catch (\Exception $ex) {
            $this->logger->error('Unexpected error on GET for user ' . $args['userid'] . ': ' . $ex->getMessage());
            return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)
                ->withJson(['error' => $ex->getMessage()]);
        }
        $userArray["reservations"] = $this->addUserReservations($user);
        //$userArray["deliveries"] = $this->addUserDeliveries($user);
        $userArray["projects"] = $this->addUserProjects($user);

        return $response->withJson($userArray);
    }

    /**
     * @deprecated use create instead
     */
    function add(RequestInterface $request, ResponseInterface $response, $args){
        return $this->create($request, $response, $args);
    }
    function create(RequestInterface $request, ResponseInterface $response, $args)
    {
        $this->logger->info("Klusbib POST on '/users' route");
        $data = $request->getParsedBody();
        $this->logger->info("parsedbody=" . json_encode($data, JSON_THROW_ON_ERROR));
        $errors = [];
        if (empty($data)
            || !UserValidator::containsMandatoryData($data, $this->logger, $errors)
            || !UserValidator::isValidUserData($data, $this->logger, $errors)) {
            $this->logger->info("errors=" . json_encode($errors, JSON_THROW_ON_ERROR));

            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
            ->withJson($errors);
        }

        $isAdmin = false;
        $user = new Contact;
        $authorised = Authorisation::checkUserAccess($this->token, "create", null);
        if ($authorised) {
            // check if authenticated user is also admin
            $currentUser = Contact::find($this->token->getSub());
            if (!isset($currentUser)) {
                $this->logger->warn("No user found for token " + $this->token->getSub());
                return $response->withStatus(HttpResponseCode::FORBIDDEN);
            }
            if ($currentUser->isAdmin()) {
                $isAdmin = true;
            }
            // disable notifications for user creations by admin
            $sendNotification = FALSE;
            $sendEmailVerification = FALSE;
        }

        $mailmgr = null;
        if ($this->isWebEnrolment($authorised, $data)) {
            $user->state = UserState::CHECK_PAYMENT;
            if (!isset($data["payment_mode"])) {
                $data["payment_mode"] = \Api\Model\PaymentMode::UNKNOWN;
            }

            $user->role = 'member'; // only members can be created through web enrolment
//            $data["membership_start_date"] = strftime('%Y-%m-%d', time());
            if (!isset($data["accept_terms"]) || $data["accept_terms"] !== true) {
                $this->logger->warn('user ' . $data["firstname"] . ' ' . $data["lastname"] . ' did not accept terms (accept_terms=' . $data["accept_terms"] . ')');
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->withJson(['error' => ['status' => HttpResponseCode::BAD_REQUEST, 'message' => "user should accept terms"]]);
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
            } else {
                $user->state = $data["state"];
            }
            $sendNotification = FALSE;
            $sendEmailVerification = FALSE;
    
        }
        if (isset($data["email"])) {
            $this->logger->debug('Checking user email ' . $data["email"] . ' already exists');
            $userByEmail = Contact::where('email', $data["email"])->first();
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
                    return $response->withJson(['error' => ['status' => HttpResponseCode::CONFLICT, 'message' => 'A user with that email already exists']])
                        ->withStatus(HttpResponseCode::CONFLICT);
                }
            } else {
                $this->logger->debug('No user found with email ' . $data["email"]);
            }
        }
        // TODO: else : check user exists based on name?

        if (!isset($data["user_id"]) || empty($data["user_id"])) {
            $max_user_id = Capsule::table('contact')->max('id');
            $user->id = $max_user_id + 1;
            $this->logger->info("New user will be assigned id " . $user->id);
        } else {
            // check user_id is numeric
            if (!is_numeric($data["user_id"])) {
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->withJson(['error' => ['status' => HttpResponseCode::BAD_REQUEST, 'message' => "user_id is not numeric"]]);
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
        $this->logger->debug('Creating user ' . \json_encode($user, JSON_THROW_ON_ERROR));

        $this->userManager->create($user);
        // For backward compatibility: create membership at once if start date is provided
        // FIXME: check if this can be removed and call "POST enrolment" instead
//        if (!empty($user->membership_start_date) && empty($user->active_membership)) {
//            // create membership
//            $status = MembershipMapper::getMembershipStatus($user->state, $user->id);
//            // FIXME: membership type not known yet. Could be stroom if payment mode STROOM is chosen in next step
//            \Api\Enrolment\EnrolmentManager::createMembership(MembershipType::regular(), $user->membership_start_date,
//                $user->membership_end_date, $user, $status);
//
//        }
        $this->logger->info("User created!");
        if ($sendEmailVerification) {
            $this->logger->info("Sending email verification");
            $sub = $user->id;
            $scopes = ["auth.confirm"];
            $result = $mailmgr->sendEmailVerification($user->id, $user->first_name, $user->email,
                Token::generateToken($scopes, $sub));
            $this->logger->info('Sending email verification result: ' . $mailmgr->getLastMessage());
        }
        if ($sendNotification) {
            $this->logger->info("Sending email notification - user created");
            // Notification to be sent to Klusbib team of new enrolment
            $result = $mailmgr->sendEnrolmentNotification(ENROLMENT_NOTIF_EMAIL, $user);
            $this->logger->info('Sending enrolment notification result: ' . $mailmgr->getLastMessage());
        }
        $resourceUri = '/users/' . $user->id;
        return $response->withAddedHeader('Location', $resourceUri)
            ->withJson(UserMapper::mapUserToArray($user))
            ->withStatus(201);
    }

    function update(RequestInterface $request, ResponseInterface $response, $args) {
        // TODO: remove state, membership_start_date and membership_end_date once clients have been updated
        $this->logger->info("Klusbib PUT on '/users/id' route");

        if (false === $this->token->hasScope(["users.all", "users.update", "users.update.owner", "users.update.password"])) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->write("Token not allowed to update users.");
        }

        $currentUser = \Api\Model\Contact::find($this->token->getSub());

        $user = \Api\Model\Contact::find($args['userid']);
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        if (!$user->isEmailConfirmed()) {
            $this->confirmEmail($user);
        }
        if (false === $this->token->hasScope(["users.all", "users.update"]) &&
            $user->id != $this->token->decoded->sub) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->write("Token sub doesn't match user.");
        }

        $data = $request->getParsedBody();
        $errors = [];
        if (empty($data) || !UserValidator::isValidUserData($data, $this->logger, $errors)) {
            $this->logger->info("errors=" . json_encode($errors, JSON_THROW_ON_ERROR));
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
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

    function updateTerms(RequestInterface $request, ResponseInterface $response, $args)
    {
        $this->logger->info("Klusbib PUT on '/users/id' route");

        if (false === $this->token->hasScope(["users.all", "users.update", "users.update.owner"])) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->write("Token not allowed to update user terms.");
        }
        $user = \Api\Model\Contact::find($args['userid']);
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        if (false === $this->token->hasScope(["users.all", "users.update"]) &&
            $user->id != $this->token->decoded->sub) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->write("Token sub doesn't match user.");
        }

        $data = $request->getParsedBody();
        $now = new \DateTime('now');
        if (isset($data["accept_terms_date"])) {
            // check terms date max 1 month in future
            $termsDate = \DateTime::createFromFormat('Y-m-d', $data["accept_terms_date"]);
            if (!$termsDate)
            {
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->write("Invalid accept_terms_date value (actual value: " . $data["accept_terms_date"] . ", expected format YYYY-MM-DD)");
            }
            if ($termsDate > $now->add(new \DateInterval('P1M')) ) {
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->write("accept_terms_date value of more than 1 month in future not allowed (actual value: " . $data["accept_terms_date"] . ")");
            }
        } else {
            $termsDate = new \DateTime('now');
        }
        // TODO: only update terms date if more recent than current value?
        $user->accept_terms_date = $termsDate;
        $this->userManager->update($user);

        return $response->withJson(UserMapper::mapUserToArray($user));
    }

    function delete(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib DELETE on '/users/id' route");

        if (false === $this->token->hasScope(["users.all", "users.delete"])) {
            throw new ForbiddenException("Token not allowed to delete users.", 403);
        }

        $user = \Api\Model\Contact::find($args['userid']);
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::NO_CONTENT);
        }

        // if last user on membership, mark membership as cancelled prior to user removal
        if (isset($user->active_membership)
         && $membership = Membership::find($user->active_membership)) {
            $user->activeMembership()->dissociate($membership);
            $user->save();
            if ($membership->contact_id == $user->id) {
                $membership->contact_id = null;
            }
            if ($membership->members()->count() <= 1) {
                $membership->status = MembershipState::STATUS_CANCELLED;
            }
            $membership->save();
        }
        $this->userManager->delete($user);
        return $response->withStatus(HttpResponseCode::OK);
    }

    protected function confirmEmail(Contact $user) {
        if ( $this->token->hasScope(["auth.confirm"])
            && $this->token->getDest() != null ) {
            if ($user->email == $this->token->getDest()) {
                $user->email_state = EmailState::CONFIRMED;
                $this->logger->info("Email address $user->email has been confirmed (user id $user->id)");
            }
        }
    }
    /**
     * @param $request
     * @param $response
     * @return mixed
     */
    protected function getUserState(RequestInterface $request, ResponseInterface $response, $email)
    {
        // TODO: create method to return membership state instead
        $this->logger->warn("User state is deprecated and replaced by membership status");
        if (Authorisation::checkUserAccess($this->token, Authorisation::OPERATION_READ_STATE, null)) {
            // Allow read of state to resume enrolment
            parse_str($request->getUri()->getQuery(), $queryParams);
//            $email = $request->getQueryParam('email');
            $email = $queryParams['email'] ??  null;
            if (!isset($email)) {
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->withJson(['message' => "Missing email parameter"]);
            }
            $contact = Capsule::table('contact')->where('email', $email)->first();
            if (!isset($contact)) {
                return $response->withStatus(HttpResponseCode::NOT_FOUND)
                    ->withJson(['message' => "Unknown email"]);
            }
            // possible state responses: 
            // ACTIVE, CHECK_PAYMENT, EXPIRED, DISABLED, DELETED
            $state = UserState::DISABLED;
            $membershipEndDate = $contact->membership_end_date;
            if ($contact->activeMembership) {
                // TODO: should be converted to 'YYYY-MM-DD'
                //$membershipEndDate = $contact->activeMembership->expires_at;
                if ($contact->activeMembership->status == MembershipState::STATUS_ACTIVE) {
                    $state = MembershipState::STATUS_ACTIVE;
                } else if ($contact->activeMembership->status == MembershipState::STATUS_EXPIRED) {
                    $state = MembershipState::STATUS_EXPIRED;
                } else if ($contact->activeMembership->status == MembershipState::STATUS_PENDING) {
                    $state = UserState::CHECK_PAYMENT;
                } else if ($contact->activeMembership->status == MembershipState::STATUS_CANCELLED) {
                    $state = $contact->activeMembership->status;
                }
            } else {
                $payment = $contact->payments()->where('kb_state', '=', PaymentState::OPEN)->first;
                if ($payment) {
                    $state = UserState::CHECK_PAYMENT;
                }    
            }
            //$membership = Membership::withUser($contact->id)->first();
            return $response->withJson([
                "user_id" => $contact->id, 
                "state" => $state, 
                "membership_end_date" => $membershipEndDate
            ]);
        }
        $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes(), JSON_THROW_ON_ERROR));
        return $response->withStatus(HttpResponseCode::FORBIDDEN);
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
        $reservationsArray = [];
        $userReservations = $this->loanManager->getUserReservations($user->id);
        foreach ($userReservations as $reservation) {
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
    // private function addUserDeliveries($user)
    // {
    //     $deliveriesArray = array();
    //     foreach ($user->deliveries as $delivery) {
    //         $deliveryData = DeliveryMapper::mapDeliveryToArray($delivery);
    //         array_push($deliveriesArray, $deliveryData);
    //     }
    //     return $deliveriesArray;
    // }
    private function addUserProjects($user)
    {
        $projectsArray = [];
        $this->logger->info(\json_encode($user->projects, JSON_THROW_ON_ERROR));
        foreach ($user->projects as $project) {
            $this->logger->info(\json_encode($project, JSON_THROW_ON_ERROR));
            $projectData = ["id" => $project->id, "name" => $project->name];

            //$projectData = ProjectMapper::mapDeliveryToArray($project);
            array_push($projectsArray, $projectData);
        }
        return $projectsArray;
    }
}