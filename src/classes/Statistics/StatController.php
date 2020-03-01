<?php

namespace Api\Statistics;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Model\Lending;
use Api\Model\Tool;
use Api\Model\ToolState;
use Illuminate\Database\Capsule\Manager as Capsule;
use DateTime;
use Slim\Http\Request;
use Slim\Http\Response;

class StatController
{
    private $inventory;
    private $logger;

    /**
     * StatController constructor.
     */
    public function __construct(Inventory $inventory, $logger)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
    }


    /**
     * Returns monthly statistics
     * - asset count
     * - user count
     * - new user count
     * - expired / delete user count
     * - checkout count / checkin count
     * @param $request
     * @param $response
     * @param $args
     */
    function monthly(Request $request, Response $response, $args) {
        $data = array();

        // if month param given, lookup stat for that month, else stat of current month
        // also support period? from and to params for start and end month?

        // user stats
        $userStats = $this->getUserStats();
        $data["user-statistics"] = $userStats;

        // tool stats
        $toolStats = $this->getToolStats();
        $data["tool-statistics"] = $toolStats;

        // accessory stats
        $accessoryStats = array();
        $accessoryStats = $this->getAccessoryStats($accessoryStats);
        $data["accessory-statistics"] = $accessoryStats;

        // activity stats
        $activityStats = $this->getLendingStats();
        $data["activity-statistics"] = $activityStats;

        return $response->withJson($data);
    }

    function yearly($request, $response, $args) {

    }

    /**
     * @param $startLastMonth
     * @param $startThisMonth
     * @return array
     */
    private function getUserStats(): array
    {
        $utc = new \DateTimeZone("UTC");
        $startLastMonth = new DateTime('first day of last month', $utc);
        $startThisMonth = new DateTime('first day of this month', $utc);

        $activeCount = \Api\Model\User::active()->members()->count();
        $expiredCount = \Api\Model\User::where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCount = \Api\Model\User::where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCurrMonthCount = \Api\Model\User::members()->where('created_at', '>', new DateTime('first day of this month'))->count();
        $newUsersPrevMonthCount = \Api\Model\User::members()
            ->where('created_at', '>', $startLastMonth)
            ->where('created_at', '<', $startThisMonth)->count();
        $userStats = array();
        $userStats["total-count"] = $activeCount + $expiredCount;
        $userStats["active-count"] = $activeCount;
        $userStats["expired-count"] = $expiredCount;
        $userStats["deleted-count"] = $deletedCount;
        $userStats["new-users-curr-month-count"] = $newUsersCurrMonthCount;
        $userStats["new-users-prev-month-count"] = $newUsersPrevMonthCount;

        // Stroom
        $activeCountStroom = \Api\Model\User::stroom()->count();
        $expiredCountStroom = \Api\Model\User::stroom()->where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCountStroom = \Api\Model\User::stroom()->where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCurrMonthCountStroom = \Api\Model\User::stroom()->members()->where('created_at', '>', new DateTime('first day of this month'))->count();
        $newUsersPrevMonthCountStroom = \Api\Model\User::stroom()
            ->where('created_at', '>', $startLastMonth)
            ->where('created_at', '<', $startThisMonth)->count();
        $stroomStats = array();
        $stroomStats["total-count"] = $activeCountStroom + $expiredCountStroom;
        $stroomStats["active-count"] = $activeCountStroom;
        $stroomStats["expired-count"] = $expiredCountStroom;
        $stroomStats["deleted-count"] = $deletedCountStroom;
        $stroomStats["new-users-curr-month-count"] = $newUsersCurrMonthCountStroom;
        $stroomStats["new-users-prev-month-count"] = $newUsersPrevMonthCountStroom;

        $userStats["stroom"] = $stroomStats; //count($stroomUsers)

        return $userStats;
    }

    /**
     * @return array
     */
    private function getToolStats(): array
    {
        $tools = $this->inventory->getTools();

//        $toolCount = Tool::all()->count();
        $toolStats = array();
        $toolStats["total-count"] = count($tools);
        $newTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::NEW;
        });
        $toolStats["new-count"] = count($newTools);
        $availableTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::READY;
        });
        $toolStats["available-count"] = count($availableTools);
        $deployedTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::IN_USE;
        });
        $toolStats["deployed-count"] = count($deployedTools);
        $maintenanceTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::MAINTENANCE;
        });
        $toolStats["maintenance-count"] = count($maintenanceTools);
        $archivedTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::DISPOSED;
        });
        $toolStats["archived-count"] = count($archivedTools);
        return $toolStats;
    }

    /**
     * @param $accessoryStats
     * @return mixed
     */
    private function getAccessoryStats($accessoryStats)
    {
        $accessories = $this->inventory->getAccessories();

        $accessoryStats["total-count"] = count($accessories);
        return $accessoryStats;
    }

    /**
     * @param $startLastMonth
     * @param $startThisMonth
     * @return array
     */
    private function getLendingStats(): array
    {
        $utc = new \DateTimeZone("UTC");
        $startLastMonth = new DateTime('first day of last month', $utc);
        $startThisMonth = new DateTime('first day of this month', $utc);

        $checkoutPrevMonthCount = Lending::inYear($startLastMonth->format("Y"))->inMonth($startLastMonth->format("m"))->count();
        $checkoutCurrMonthCount = Lending::inYear($startThisMonth->format("Y"))->inMonth($startThisMonth->format("m"))->count();
        $checkinPrevMonthCount = Lending::returnedInYear($startLastMonth->format("Y"))->returnedInMonth($startLastMonth->format("m"))->count();
        $checkinCurrMonthCount = Lending::returnedInYear($startThisMonth->format("Y"))->returnedInMonth($startThisMonth->format("m"))->count();
        $activityStats = array();
        $activityStats["total-count"] = Lending::count();
        $activityStats["active-count"] = Lending::active()->count();
        $activityStats["overdue-count"] = Lending::overdue()->count();
        $activityStats["checkout-prev-month-count"] = $checkoutPrevMonthCount;
        $activityStats["checkout-curr-month-count"] = $checkoutCurrMonthCount;
        $activityStats["checkin-prev-month-count"] = $checkinPrevMonthCount;
        $activityStats["checkin-curr-month-count"] = $checkinCurrMonthCount;

        // Stroom
        $stroomStats = array();
        $stroomStats["total-count"] = Lending::stroom()->count();
        $stroomStats["active-count"] = Lending::stroom()->active()->count();
        $stroomStats["overdue-count"] = Lending::stroom()->overdue()->count();

        $checkoutPrevMonthCountStroom = Lending::stroom()->inYear($startLastMonth->format("Y"))->inMonth($startLastMonth->format("m"))->count();
        $checkoutCurrMonthCountStroom = Lending::stroom()->inYear($startThisMonth->format("Y"))->inMonth($startThisMonth->format("m"))->count();
        $checkinPrevMonthCountStroom = Lending::stroom()->returnedInYear($startLastMonth->format("Y"))->returnedInMonth($startLastMonth->format("m"))->count();
        $checkinCurrMonthCountStroom = Lending::stroom()->returnedInYear($startThisMonth->format("Y"))->returnedInMonth($startThisMonth->format("m"))->count();

        $stroomStats["checkout-prev-month-count"] = $checkoutPrevMonthCountStroom;
        $stroomStats["checkout-curr-month-count"] = $checkoutCurrMonthCountStroom;
        $stroomStats["checkin-prev-month-count"] = $checkinPrevMonthCountStroom;
        $stroomStats["checkin-curr-month-count"] = $checkinCurrMonthCountStroom;
        $activityStats["stroom"] = $stroomStats;
        return $activityStats;
    }
}