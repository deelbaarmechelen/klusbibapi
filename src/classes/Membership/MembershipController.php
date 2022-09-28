<?php

namespace Api\Membership;


use Api\Authorisation;
use Api\Model\Membership;
use Api\Model\MembershipState;
use Api\Model\MembershipType;
use Api\Model\Contact;
use Api\ModelMapper\MembershipMapper;
use Api\ModelMapper\UserMapper;
use Api\User\UserManager;
use Api\Util\HttpResponseCode;
use Api\Validator\MembershipValidator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MembershipController
{
    protected $logger;
    protected $userManager;
    protected $token;

    public function __construct($logger, UserManager $userManager, $token) {
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->token = $token;
    }

    public function subscribe($request, $response, $args) {
        $this->logger->info("Klusbib POST on '/membership/subscribe' route");
//        membership_subscribe[membershipType]: 1
//membership_subscribe[price]: 30.00
//membership_subscribe[paymentAmount]: 30.00
//membership_subscribe[paymentMethod]: 1
//membership_subscribe[paymentNote]:
//c: 2
//return:
//stripeCardId:
//stripePaymentMethod:
//depositTotal:
//chargeId:
//paymentId:
//paymentType: subscription
//itemId:
//membership_subscribe[_token]: R-LINop4r0pH4ymJIa37c9Ie_Vi4kmwFgB_pn5EliJw

    }

    public function getById($request, $response, $args) {
        $this->logger->info("Klusbib GET '/membership/id' route");

        $authorised = Authorisation::checkMembershipAccess($this->token, Authorisation::OPERATION_READ);
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $membership = \Api\Model\Membership::find($args['membershipId']);
        if (null == $membership) {
            return $response->withStatus(404);
        }
        $this->logger->info('membership found for id ' . $membership->lending_id);
        return $response->withJson(MembershipMapper::mapMembershipToArray($membership));

    }

    public function getAll($request, $response, $args) {
        $this->logger->info("Klusbib GET '/membership' route (params=" . \json_encode($request->getQueryParams()) . ")");

        $authorised = Authorisation::checkMembershipAccess($this->token, Authorisation::OPERATION_LIST);
        if (!$authorised) {
            $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()));
            return $response->withStatus(403);
        }
        $sortdir = $request->getQueryParam('_sortDir');
        if (!isset($sortdir)) {
            $sortdir = 'desc';
        }
        $sortfield = $request->getQueryParam('_sortField');
        if (!Membership::canBeSortedOn($sortfield) ) {
            $sortfield = 'created_at';
        }
        $page = $request->getQueryParam('_page');
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $request->getQueryParam('_perPage');
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $userId = $request->getQueryParam('user_id');
        $status = $request->getQueryParam('status');
        if (!isset($status)) {
            $status = MembershipState::STATUS_ACTIVE;
        }
        $subscriptionId = $request->getQueryParam('subscription_id');
        $startAt = $request->getQueryParam('start_at');

        if ($status === MembershipState::STATUS_ACTIVE) {
            $query = Membership::active();
        } elseif ($status === "OPEN") {
            $query = Membership::open();
        } elseif ($status === "ALL") {
            $query = Membership::anyStatus();
        } else {
            $query = Membership::withStatus($status);
        }

        if (isset($userId)) {
            $query = $query->withUser($userId);
        }
        if (isset($subscriptionId)) {
            $query = $query->withSubscriptionId($subscriptionId);
        }
        if (isset($startDate)) {
            $query = $query->withStartAt($startAt);
        }
        $memberships = $query->orderBy($sortfield, $sortdir)->get();
        $memberships_page = array_slice($memberships->all(), ($page - 1) * $perPage, $perPage);
        $data = array();
        foreach ($memberships_page as $membership) {
            $membershipData = MembershipMapper::mapMembershipToArray($membership);

            // lookup subscription and add it to data
            $subscription = MembershipType::find($membership->subscription_id);
            if (isset($subscription)) {
                $membershipData["subscription"] = MembershipMapper::mapSubscriptionToArray($subscription);
            }

            // lookup user and add it to data
            $user = $this->userManager->getById($membership->contact_id, false);
            if (isset($user)) {
                $this->logger->info('user found: ' . \json_encode($user));
                $membershipData['user'] = UserMapper::mapUserToArray($user);
                $this->logger->info(\json_encode($membershipData));
            }
            array_push($data, $membershipData);
        }
        $this->logger->info(count($memberships) . ' membership(s) found!');
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($memberships));
    }

    function update(RequestInterface $request, ResponseInterface $response, $args)
    {
        $this->logger->info("Klusbib PUT on '/membership/id' route");

        if (false === $this->token->hasScope(["memberships.all", "memberships.update", "memberships.update.owner"])) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->write("Token not allowed to update memberships.");
        }

        $currentUser = Contact::find($this->token->getSub());

        $membership = Membership::find($args['membershipId']);
        if (null == $membership) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $data = $request->getParsedBody();
        $errors = array();
        if (empty($data) || !MembershipValidator::isValidMembershipData($data, $this->logger, $errors)) {
            $this->logger->info("errors=" . json_encode($errors));
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)->withJson($errors); // Bad request
        }
        $updateMembershipUsers = false;
        if ($currentUser->isAdmin()) {
            if (isset($data["status"]) && $data["status"] !== $membership->status) {
                $this->logger->info("Klusbib PUT updating status from " . $membership->status . " to " . $data["status"]);
                $membership->status = $data["status"];
                $updateMembershipUsers = true;
            }
            if (isset($data["last_payment_mode"]) && $data["last_payment_mode"] !== $membership->last_payment_mode) {
                $this->logger->info("Klusbib PUT updating last_payment_mode from " . $membership->last_payment_mode . " to " . $data["last_payment_mode"]);
                $membership->last_payment_mode = $data["last_payment_mode"];
                $updateMembershipUsers = true;
            }
            if (isset($data["start_at"]) && $data["start_at"] !== $membership->start_at) {
                $this->logger->info("Klusbib PUT updating start_at from " . $membership->start_at . " to " . $data["start_at"]);
                $membership->start_at = $data["start_at"];
                $updateMembershipUsers = true;
            }
            if (isset($data["expires_at"]) && $data["expires_at"] !== $membership->expires_at) {
                $this->logger->info("Klusbib PUT updating expires_at from " . $membership->expires_at . " to " . $data["expires_at"]);
                $membership->expires_at = $data["expires_at"];
                $updateMembershipUsers = true;
            }
            if (isset($data["subscription_id"]) && $data["subscription_id"] !== $membership->subscription_id) {
                $this->logger->info("Klusbib PUT updating subscription_id from " . $membership->subscription_id . " to " . $data["subscription_id"]);
                $membership->subscription_id = $data["subscription_id"];
            }
            if (isset($data["contact_id"]) && $data["contact_id"] !== $membership->contact_id) {
                $this->logger->info("Klusbib PUT updating contact_id from " . $membership->contact_id . " to " . $data["contact_id"]);
                $membership->contact_id = $data["contact_id"];
            }
            if (isset($data["comment"]) && $data["comment"] !== $membership->comment) {
                $this->logger->info("Klusbib PUT updating comment from " . $membership->comment . " to " . $data["comment"]);
                $membership->comment = $data["comment"];
            }
        }
        if ($updateMembershipUsers) {
            // lookup users for this membership
            $this->logger->info("Klusbib PUT updating membership users... ");
            $users = Contact::notDeleted()->hasMembership($membership->id)->get();
            $this->logger->info("Klusbib PUT updating users " . \json_encode($users));
            if ($users) {
                foreach ($users as $user) {
                    // FIXME: update active_membership if status becomes inactive (expired or cancelled?) ?
//                    $user->membership_start_date = $membership->start_at;
//                    $user->membership_end_date = $membership->expires_at;
//                    $user->payment_mode = $membership->last_payment_mode;
//                    $user->save();
                }
            }
            $this->logger->info("Klusbib PUT membership users updated ! ");
        }
        $this->logger->info("Klusbib PUT saving updated membership: " . \json_encode($membership));
        $membership->save();

        return $response->withJson(MembershipMapper::mapMembershipToArray($membership));

    }
}