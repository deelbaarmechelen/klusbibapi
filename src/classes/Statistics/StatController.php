<?php

namespace Api\Statistics;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Model\Lending;
use Api\Model\Tool;
use Api\Model\ToolState;
use Api\Model\Stat;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use DateInterval;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
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
    function monthly(RequestInterface $request, ResponseInterface $response, $args) {
        parse_str($request->getUri()->getQuery(), $queryParams);
        $statMonth = $queryParams['stat-month'] ??  null;
 
        $utc = new \DateTimeZone("UTC");
        if (isset($statMonth)) {
            $startThisMonth = DateTimeImmutable::createFromFormat('Y-m-01', $statMonth . '-01', $utc);
            if (!$startThisMonth) {
                // createFromFormat failed
                $error = "Invalid stat-month value $statMonth, expected 'YYYY-MM'";
                $this->logger->warning($error);
                $errors = array();
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                                ->withJson(array_push($errors, $error));;
            }
        } else {
            $startThisMonth = new DateTime('first day of this month', $utc);
        }
        $startLastMonth = $startThisMonth->sub(new \DateInterval('P1M'));
        $data = $this->createVersion1Stats($startLastMonth, $startThisMonth);

        // TODO: if month param given, lookup stat for that month, else stat of current month
        // also support period? from and to params for start and end month?

        // store statistic
        $endStat = $startThisMonth->add(new \DateInterval('P1M'));

        $stat = Stat::firstOrCreate([
            'name' => $startThisMonth->format('Ym'),
            'version' => 1
        ]);
        $stat->stats = \json_encode($data);
        $stat->start_date = $startThisMonth->format('Y-m-d');
        $stat->end_date = $endStat->format('Y-m-d');
        $stat->save();
        return $response->withJson($data);
    }

    function createVersion1Stats($startLastMonth, $startThisMonth) {
        $data = array();

        // user stats
        $userStats = $this->getUserStats($startLastMonth, $startThisMonth);
        $data["user-statistics"] = $userStats;

        // tool stats
        $toolStats = $this->getToolStats();
        $data["tool-statistics"] = $toolStats;

        // accessory stats
        $accessoryStats = $this->getAccessoryStats();
        $data["accessory-statistics"] = $accessoryStats;

        // activity stats
        $activityStats = $this->getLendingStats($startLastMonth, $startThisMonth);
        $data["activity-statistics"] = $activityStats;

        return $data;
    }
    function createVersion2Stats() {
        $data = array();
        // TODO: get #memberships by membership type started or renewed in stat period

        // TODO: get #lendings
        // TODO: get #reservations
    }

    function yearly(RequestInterface $request, ResponseInterface $response, $args) {
        $data = array();
        $now = new DateTimeImmutable('now');

        $userStats = $this->getYearlyUserStats($now->format('Y'));
        $data["user-statistics"] = $userStats;

        // activity stats
        $activityStats = $this->getYearlyLendingStats($now->format('Y'));
        $data["activity-statistics"] = $activityStats;

        return $response->withJson($data);
    }

    /**
     * @param $year
     * @return array
     */
    private function getYearlyUserStats($year): array
    {
        $startYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-01-01', $utc);
        $endYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-12-31', $utc);
        $activeCount = \Api\Model\Contact::active()->members()->count();
        $expiredCount = \Api\Model\Contact::where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCount = \Api\Model\Contact::members()
            ->where('created_at', '>=', $startYear)
            ->where('created_at', '<=', $endYear)->count();
        $userStats = array();
        $userStats["total-count"] = $activeCount + $expiredCount;
        $userStats["active-count"] = $activeCount;
        $userStats["expired-count"] = $expiredCount;
        $userStats["deleted-count"] = $deletedCount;
        $userStats["new-users-count"] = $newUsersCount;

        return $userStats;
    }
        
    /**
     * @param $startLastMonth
     * @param $startThisMonth
     * @return array
     */
    private function getUserStats($startLastMonth, $startThisMonth): array
    {
        $activeCount = \Api\Model\Contact::active()->members()->count();
        $expiredCount = \Api\Model\Contact::where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCurrMonthCount = \Api\Model\Contact::members()->where('created_at', '>', $startThisMonth)->count();
        $newUsersPrevMonthCount = \Api\Model\Contact::members()
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
        $activeCountStroom = \Api\Model\Contact::stroom()->count();
        $expiredCountStroom = \Api\Model\Contact::stroom()->where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCountStroom = \Api\Model\Contact::stroom()->where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCurrMonthCountStroom = \Api\Model\Contact::stroom()->members()->where('created_at', '>', $startThisMonth)->count();
        $newUsersPrevMonthCountStroom = \Api\Model\Contact::stroom()
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

        //$toolCount = Tool::all()->count();
        $toolStats = array();
        $toolStats["total-count"] = isset($tools) ? count($tools) : 0;
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
     * @return mixed
     */
    private function getAccessoryStats()
    {
        $accessories = $this->inventory->getAccessories();

        $accessoryStats["total-count"] = isset($accessories) ? count($accessories) : 0;
        return $accessoryStats;
    }

    /**
     * @param $year
     * @return array
     */
    private function getYearlyLendingStats($year): array
    {
        $startYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-01-01', $utc);
        $endYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-12-31', $utc);
        $checkoutCount = Lending::inYear($startYear->format("Y"))->count();
        $checkinCount = Lending::returnedInYear($startYear->format("Y"))->count();

        $activityStats = array();
        $activityStats["total-count"] = Lending::count();
        $activityStats["active-count"] = Lending::active()->count();
        $activityStats["overdue-count"] = Lending::overdue()->count();
        $activityStats["checkout-count"] = $checkoutCount;
        $activityStats["checkin-count"] = $checkinCount;
        return $activityStats;
    }
        
    /**
     * @param $startLastMonth
     * @param $startThisMonth
     * @return array
     */
    private function getLendingStats($startLastMonth, $startThisMonth): array
    {
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