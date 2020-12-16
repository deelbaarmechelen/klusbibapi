<?php

namespace Api\Membership;


use Api\Authorisation;
use Api\Model\Membership;
use Api\Model\MembershipType;
use Api\ModelMapper\MembershipMapper;
use Api\ModelMapper\UserMapper;
use Api\User\UserManager;

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
            $status = Membership::STATUS_ACTIVE;
        }
        $subscriptionId = $request->getQueryParam('subscription_id');
        $startAt = $request->getQueryParam('start_at');
        $query = Membership::all();

        if ($status == Membership::STATUS_ACTIVE) {
            $query = Membership::active();
        } elseif ($status == "OPEN") {
            $query = Membership::open();
        } elseif ($status == "ALL") {
            // nothing to do
        } else {
            $query = $query->withStatus($status);
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
}