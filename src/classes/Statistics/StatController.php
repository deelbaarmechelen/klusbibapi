<?php

namespace Api\Statistics;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Model\Tool;
use Api\Model\ToolState;
use Illuminate\Database\Capsule\Manager as Capsule;


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
    function monthly($request, $response, $args) {
        // if month param given, lookup stat for that month, else stat of current month
        // also support period? from and to params for start and end month?

        // user stats
        $activeCount = \Api\Model\User::active()->members()->count();
        $expiredCount = \Api\Model\User::where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCount = \Api\Model\User::where('state', \Api\Model\UserState::DELETED)->count();
        $data = array();
        $userStats = array();
        $userStats["active-count"] = $activeCount;
        $userStats["expired-count"] = $expiredCount;
        $userStats["deleted-count"] = $deletedCount;
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
        return $response->withJson($data);
    }

    function yearly($request, $response, $args) {

    }
}