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
        // if month param given, lookup stat for that month, else stat of current month
        // also support period? from and to params for start and end month?
        $utc = new \DateTimeZone("UTC");
        $startLastMonth = new DateTime('first day of last month', $utc);
        $startThisMonth = new DateTime('first day of this month', $utc);
//        echo $startThisMonth;

        // user stats
        $activeCount = \Api\Model\User::active()->members()->count();
        $expiredCount = \Api\Model\User::where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCount = \Api\Model\User::where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCurrMonthCount = \Api\Model\User::members()->where('created_at','>', new DateTime('first day of this month'))->count();
        $newUsersPrevMonthCount = \Api\Model\User::members()
            ->where('created_at','>', $startLastMonth)
            ->where('created_at','<', $startThisMonth)->count();
        $data = array();
        $userStats = array();
        $userStats["total-count"] = $activeCount + $expiredCount;
        $userStats["active-count"] = $activeCount;
        $userStats["expired-count"] = $expiredCount;
        $userStats["deleted-count"] = $deletedCount;
        $userStats["new-users-curr-month-count"] = $newUsersCurrMonthCount;
        $userStats["new-users-prev-month-count"] = $newUsersPrevMonthCount;
        $data["user-statistics"] = $userStats;

        // tool stats
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
        $data["tool-statistics"] = $toolStats;

        // accessory stats
        $accessoryStats = array();
        $accessories = $this->inventory->getAccessories();

        $accessoryStats["total-count"] = count($accessories);
        $data["accessory-statistics"] = $accessoryStats;

//        $checkoutPrevMonthCount = Lending::startFrom($startLastMonth)->startBefore($startThisMonth)->count();
//        $checkoutCurrMonthCount = Lending::startFrom($startThisMonth)->count();
        $currYear = date("Y");
        $currMonth = date("M");
        $prevYear = date("Y");
        $checkoutPrevMonthCount = Lending::inYear($startLastMonth->format("Y"))->inMonth($startLastMonth->format("m"))->count();
        $checkoutCurrMonthCount = Lending::inYear($startThisMonth->format("Y"))->inMonth($startThisMonth->format("m"))->count();
        $checkinPrevMonthCount = Lending::returnedInYear($startLastMonth->format("Y"))->returnedInMonth($startLastMonth->format("m"))->count();
        $checkinCurrMonthCount = Lending::returnedInYear($startThisMonth->format("Y"))->returnedInMonth($startThisMonth->format("m"))->count();
        $activityStats = array();
        $activityStats["checkout-prev-month-count"] = $checkoutPrevMonthCount;
        $activityStats["checkout-curr-month-count"] = $checkoutCurrMonthCount;
        $activityStats["checkin-prev-month-count"] = $checkinPrevMonthCount;
        $activityStats["checkin-curr-month-count"] = $checkinCurrMonthCount;
        $activityStats["active"] = Lending::active()->count();
        $activityStats["overdue"] = Lending::overdue()->count();

        $data["activity-statistics"] = $activityStats;


        return $response->withJson($data);
    }

    function yearly($request, $response, $args) {

    }
}