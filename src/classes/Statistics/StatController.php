<?php

namespace Api\Statistics;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Model\Lending;
use Api\Model\Tool;
use Api\Model\ToolState;
use Api\Model\Stat;
use Api\Model\Membership;
use Api\Model\MembershipType;
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
 
        $utc = new DateTimeZone("UTC");
        if (isset($statMonth)) {
            $startThisMonth = DateTimeImmutable::createFromFormat('Y-m-d', $statMonth . '-01', $utc);
            if (!$startThisMonth) {
                // createFromFormat failed
                $error = "Invalid stat-month value $statMonth, expected 'YYYY-MM'";
                $this->logger->warning($error);
                $errors = array();
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                                ->withJson(array_push($errors, $error));;
            }
        } else {
            $startThisMonth = new DateTimeImmutable('first day of this month', $utc);
        }
        $startLastMonth = $startThisMonth->sub(new \DateInterval('P1M'));
        //$data = $this->createVersion1Stats($startLastMonth, $startThisMonth);
        $data = $this->createVersion2Stats($startLastMonth, $startThisMonth);

        // store statistic
        $this->logger->info("Storing monthly statistics for " . $startThisMonth->format('Ym'));
        $endStat = $startThisMonth->add(new \DateInterval('P1M'));

        $stat = Stat::firstOrCreate([
            'name' => $startThisMonth->format('Ym'),
            'version' => 1
        ]);
        $stat->stats = \json_encode($data);
        $stat->start_date = $startThisMonth->format('Y-m-01');
        $stat->end_date = $startThisMonth->format('Y-m-t');
        $stat->save();
        return $response->withJson($data);
    }

    function createVersion1Stats($startLastMonth, $startThisMonth) : array {
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

    function createVersion2Stats($startDate, $endDate) : array {
        $data = array();
        // get #memberships by membership type started or renewed in stat period
        $data["membership-statistics"] = array();
        $membershipStats = $data["membership-statistics"];
        $membershipTypes = [
            MembershipType::regular(),
            MembershipType::renewal(),
            MembershipType::regularReduced(),
            MembershipType::renewalReduced(),
            MembershipType::regularOrg(),
            MembershipType::renewalOrg(),
            MembershipType::temporary(),
        ];
        foreach ($membershipTypes as $type) {
            $membershipStats[$type->name] = $this->getMembershipStats($startDate, $endDate, $type->id);
        }
        $membershipStats["all"] = $this->getMembershipStats($startDate, $endDate);
        $membershipStats["all"]["paymentModes"] = $this->getMembershipPaymentStats($startDate, $endDate);
        // TODO: get #lendings
        // TODO: get #reservations
        return $data;
    }

    function yearly(RequestInterface $request, ResponseInterface $response, $args) {
        $data = array();
        $now = new DateTimeImmutable('now');

        $year = $now->format('Y');
        $userStats = $this->getYearlyUserStats($year);
        $data["user-statistics"] = $userStats;

        // activity stats
        $activityStats = $this->getYearlyLendingStats($year);
        $data["activity-statistics"] = $activityStats;

        // store statistic
        $this->logger->info("Storing yearly statistics for " . $year);
        $stat = Stat::firstOrCreate([
            'name' => $year,
            'version' => 1
        ]);
        $stat->stats = \json_encode($data);
        $stat->start_date = $now->format('Y-01-01');
        $stat->end_date = $now->format('Y-12-31');
        $stat->save();

        return $response->withJson($data);
    }

    /**
     * @param $year
     * @return array
     */
    private function getYearlyUserStats($year): array
    {
        $this->logger->info("Get yearly users stats with param " . $year);
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
        $this->logger->info("Get users stats with params " . $startLastMonth->format('Y-m-d') . ", " . $startThisMonth->format('Y-m-d'));
        $activeCount = \Api\Model\Contact::active()->members()->count();
        $expiredCount = \Api\Model\Contact::where('state', \Api\Model\UserState::EXPIRED)->count();
        $deletedCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCurrMonthCount = \Api\Model\Contact::members()->where('created_at', '>=', $startThisMonth)->count();
        $newUsersPrevMonthCount = \Api\Model\Contact::members()
            ->where('created_at', '>=', $startLastMonth)
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
        $newUsersCurrMonthCountStroom = \Api\Model\Contact::stroom()->members()->where('created_at', '>=', $startThisMonth)->count();
        $newUsersPrevMonthCountStroom = \Api\Model\Contact::stroom()
            ->where('created_at', '>=', $startLastMonth)
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

    private function getMembershipStats($startDate, $endDate, $membershipType = null): array
    {
        // get count by membership states: PENDING, ACTIVE, CANCELLED, EXPIRED
        // get stats for a particular membership type
        // TODO: also get stats by payment type??
        if (isset($membershipType)) {
            $activeCount = \Api\Model\Membership::active()->withSubscriptionId($membershipType)->count();
            $pendingCount = \Api\Model\Membership::pending()->withSubscriptionId($membershipType)->count();
            $expiredCount = \Api\Model\Membership::expired()->withSubscriptionId($membershipType)->count();
            $cancelledCount = \Api\Model\Membership::cancelled()->withSubscriptionId($membershipType)->count();
            $newActiveCount = \Api\Model\Membership::active()->withSubscriptionId($membershipType)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate)->count();
            $newPendingCount = \Api\Model\Membership::pending()->withSubscriptionId($membershipType)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate)->count();
        } else {
            $activeCount = \Api\Model\Membership::active()->count();
            $pendingCount = \Api\Model\Membership::pending()->count();
            $expiredCount = \Api\Model\Membership::expired()->count();
            $cancelledCount = \Api\Model\Membership::cancelled()->count();
            $newActiveCount = \Api\Model\Membership::active()
                ->where([['created_at', '>=', $startDate],
                         ['created_at', '<', $endDate]])->count();
            $newPendingCount = \Api\Model\Membership::pending()
                ->where([['created_at', '>=', $startDate],
                         ['created_at', '<', $endDate]])->count();
        }

        $membershipStats = array();
        $membershipStats["total-count"] = $activeCount + $expiredCount;
        $membershipStats["active-count"] = $activeCount;
        $membershipStats["pending-count"] = $pendingCount;
        $membershipStats["expired-count"] = $expiredCount;
        $membershipStats["cancelled-count"] = $cancelledCount;
        $membershipStats["new-active-count"] = $newActiveCount;
        $membershipStats["new-pending-count"] = $newPendingCount;
        return $membershipStats;       
    }

    private function getMembershipPaymentStats($startDate, $endDate, $paymentMode, $membershipType = null): array
    {
        if (isset($membershipType)) {
            $totalCount = \Api\Model\Membership::withSubscriptionId($membershipType)
                ->where(["last_payment_mode" => $paymentMode])->count();
            $newCount = \Api\Model\Membership::withSubscriptionId($membershipType)
                ->where(["last_payment_mode" => $paymentMode])
                ->where([['created_at', '>=', $startDate],
                         ['created_at', '<', $endDate]])->count();
        } else {
            $totalCount = \Api\Model\Membership::where(["last_payment_mode" => $paymentMode])->count();
            $newCount = \Api\Model\Membership::where(["last_payment_mode" => $paymentMode])
                ->where([['created_at', '>=', $startDate],
                         ['created_at', '<', $endDate]])->count();
        }
        $membershipPaymenStats = array();
        $membershipPaymenStats["total-count"] = $totalCount;
        $membershipPaymenStats["new-count"] = $newCount;
        return $membershipPaymenStats;
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
    private function getAccessoryStats(): array
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
        $this->logger->info("Get yearly lending stats with param " . $year);
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
        $this->logger->info("Get lending stats with params " . $startLastMonth->format('Y-m-d') . ", " . $startThisMonth->format('Y-m-d'));
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