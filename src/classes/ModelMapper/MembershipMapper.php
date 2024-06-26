<?php

namespace Api\ModelMapper;


use Api\Model\Membership;
use Api\Model\MembershipState;
use Api\Model\MembershipType;
use Api\Model\Contact;
use Api\Model\UserState;

class MembershipMapper
{

    static public function mapArrayToMembership($data)
    {
        if (!is_array($data)) {
            return null;
        }
        $membership = new Membership();

    }
    static public function mapMembershipToArray($membership)
    {
        if (!isset($membership)) {
            return [];
        }
        $subscription = MembershipType::find($membership->subscription_id);

        $membershipArray = ["id" => $membership->id,
            "status" => $membership->status,
            "start_at" => $membership->starts_at->format('Y-m-d'),
            "expires_at" => $membership->expires_at->format('Y-m-d'),
            "subscription_id" => $membership->subscription_id,
            "subscription" => !$subscription ? [] : MembershipMapper::mapSubscriptionToArray($subscription),
            "contact_id" => $membership->contact_id,
            "last_payment_mode" => $membership->last_payment_mode,
            "comment" => $membership->comment,
            "created_at" => $membership->created_at,
            "updated_at" => $membership->updated_at,
            "deleted_at" => $membership->deleted_at
        ];
        return $membershipArray;
    }

    static public function mapSubscriptionToArray(MembershipType $membershipType) {
        $nextSubscription = MembershipType::find($membershipType->next_subscription_id);
        $membershipTypeArray = ["id" => $membershipType->id,
            "name" => $membershipType->name,
            "price" => $membershipType->price,
            "duration" => $membershipType->duration,
            "discount" => $membershipType->discount,
            "self_serve" => $membershipType->self_serve,
            "max_items" => $membershipType->max_items,
            "is_active" => $membershipType->is_active,
            "next_subscription_id" => $membershipType->next_subscription_id,
            "next_subscription_price" => !$nextSubscription ? 'N/A' : $nextSubscription->price,
            "created_at" => $membershipType->created_at,
            "updated_at" => $membershipType->updated_at
        ];
        return $membershipTypeArray;
    }

    static public function mapUserArrayToMembership($data, Membership $membership, bool $isAdmin = false, $logger = null) {
        if (isset($data["state"]) && $isAdmin) {
            $status = self::getMembershipStatus($data["state"]);
            $membership->status = $status;
        }
        if (!empty($data["membership_start_date"]) && $isAdmin ) {
            $membership->starts_at = $data["membership_start_date"];
        }
        if (!empty($data["membership_end_date"])  && $isAdmin) {
            $membership->expires_at = $data["membership_end_date"];
        }

    }

    /**
     * @param $user
     * @return string
     * @throws \Exception
     */
    static public function getMembershipStatus($userState, $userId = "unknown"): string
    {
        if ($userState == UserState::ACTIVE) {
            $status = MembershipState::STATUS_ACTIVE;
        } elseif ($userState == UserState::EXPIRED) {
            $status = MembershipState::STATUS_EXPIRED;
        } elseif ($userState == UserState::CHECK_PAYMENT) {
            $status = MembershipState::STATUS_PENDING;
        } elseif ($userState == UserState::DELETED
            || $userState == UserState::DISABLED) {
            $status = MembershipState::STATUS_CANCELLED;
        } else {
            throw new \Exception("Invalid user state value $userState for user with id $userId");
        }
        return $status;
    }

}