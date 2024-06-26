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
use Api\Model\PaymentMode;
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
use Psr\Log\LoggerInterface;

class StatController
{
    private Inventory $inventory;
    private LoggerInterface $logger;
    private DateTimeZone $utc;


    /**
     * StatController constructor.
     */
    public function __construct(Inventory $inventory, LoggerInterface $logger)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
        $this->utc = new DateTimeZone("UTC");
    }


    /**
     * Returns monthly statistics
     * - asset count
     * - user count
     * - new user count
     * - expired / delete user count
     * - checkout count / checkin count
     * - membership count by type
     * @param $request
     * @param $response
     * @param $args
     */
    function monthly(RequestInterface $request, ResponseInterface $response, $args) : ResponseInterface {
        $this->logger->info("Klusbib GET '/stats/monthly' route (with params " . $request->getUri()->getQuery() . ")");
        parse_str($request->getUri()->getQuery(), $queryParams);
        $statMonth = $queryParams['stat-month'] ??  null;
        $statVersion = $queryParams['version'] ??  1;

        $startCurrentMonth = new DateTimeImmutable('first day of this month', $this->utc);
        if (isset($statMonth)) {
            $startThisMonth = DateTimeImmutable::createFromFormat('Y-m-d', $statMonth . '-01', $this->utc);
            if (!$startThisMonth) {
                // createFromFormat failed
                $error = "Invalid stat-month value $statMonth, expected 'YYYY-MM'";
                $this->logger->error($error);
                $errors = [];
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                                ->withJson(array_push($errors, $error));;
            }
            if ($startThisMonth > $startCurrentMonth) {
                $error = "Invalid stat-month value $statMonth: future dates not allowed";
                $this->logger->error($error);
                $errors = [];
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                                ->withJson(array_push($errors, $error));;
            }
        } else {
            $startThisMonth = $startCurrentMonth;
        }
        // Check if stat can be retrieved from database
        // it should exist and last update timestamp > end of month
        $statEntry = $startThisMonth->format('Ym');
        $stat = Stat::where(['name' => $statEntry, 'version' => $statVersion])->first();
        $endStat = $startThisMonth->add(new \DateInterval('P1M'));
        if (isset($stat) && $stat->updated_at > $endStat && $stat->end_date == $startThisMonth->format('Y-m-t')) {
            $this->logger->info("Statistics retrieved from database for $statEntry version $statVersion");
            $response = $response->withHeader('Content-type', 'application/json');
            $response->getBody()->write($stat->stats);
            // $response = $response->withJson(\json_decode($stat->stats, true));
            return $response; // stats already in JSON format -> don't use withJson which converts array to json
        }

        $startLastMonth = $startThisMonth->sub(new \DateInterval('P1M'));
        if ($statVersion == 1) {
            $data = $this->createVersion1Stats($startLastMonth, $startThisMonth);
        } else {
            $data = $this->createVersion2Stats($startThisMonth, $endStat);
        }

        // store statistic
        $this->logger->info("Storing monthly statistics for " . $startThisMonth->format('Ym'));

        if (!isset($stat)) {
            $stat = new Stat();
            $stat->name = $statEntry;
            $stat->version = $statVersion;
            $stat->start_date = $startThisMonth->format('Y-m-01');
        }
        $stat->stats = \json_encode($data, JSON_THROW_ON_ERROR);
        // update end_date to current date if month end not reached yet
        $now = new DateTimeImmutable('now');
        $stat->end_date = $now > $endStat ? $startThisMonth->format('Y-m-t') : $now->format('Y-m-d');
        $stat->save();
        return $response->withJson($data);
    }

    function createVersion1Stats($startLastMonth, $startThisMonth) : array {
        $data = [];

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
        // $activityStats = $this->getLendingStats($startLastMonth, $startThisMonth);
        // $data["activity-statistics"] = $activityStats;

        return $data;
    }

    function createVersion2Stats($startDate, $endDate) : array {
        $data = [];
        // get #memberships by membership type started or renewed in stat period
        $membershipStats = [];
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

        $paymentModes = PaymentMode::getPaymentModes();
        $paymentModeStats = [];
        foreach ($paymentModes as $paymentMode) {
            $paymentModeStats[$paymentMode] = $this->getMembershipPaymentStats($startDate, $endDate, $paymentMode);
        }
        $membershipStats["all"]["paymentModes"] = $paymentModeStats;
        $data["membership-statistics"] = $membershipStats;

        // user stats
        $userStats = $this->getUserStatsV2($startDate, $endDate);
        $data["user-statistics"] = $userStats;

        // tool stats
        $toolStats = $this->getToolStats();
        $data["tool-statistics"] = $toolStats;

        // activity stats
        // $activityStats = $this->getLendingStatsV2($startDate, "month");
        // $data["activity-statistics"] = $activityStats;

        // TODO: get #reservations
        return $data;
    }

    function yearly(RequestInterface $request, ResponseInterface $response, $args) {
        $this->logger->info("Klusbib GET '/stats/yearly' route (with params " . $request->getUri()->getQuery() . ")");
        $data = [];
        $now = new DateTimeImmutable('now');

        $year = $now->format('Y');
        $userStats = $this->getYearlyUserStats($year);
        $data["user-statistics"] = $userStats;

        // activity stats
        // $activityStats = $this->getYearlyLendingStats($year);
        // $data["activity-statistics"] = $activityStats;

        // store statistic
        $this->logger->info("Storing yearly statistics for " . $year);
        $stat = Stat::firstOrCreate([
            'name' => $year,
            'version' => 1
        ]);
        $stat->stats = \json_encode($data, JSON_THROW_ON_ERROR);
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
        $startYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-01-01', $this->utc);
        $endYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-12-31', $this->utc);
        $activeCount = \Api\Model\Contact::active()->members()->count();
        $expiredCount = \Api\Model\Contact::where('state', \Api\Model\UserState::EXPIRED)->count();
        $checkPaymentCount = \Api\Model\Contact::where('state', \Api\Model\UserState::CHECK_PAYMENT)->count();
        $disabledCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DISABLED)->count();
        $deletedCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DELETED)->count();
        $newUsersCount = \Api\Model\Contact::members()->createdBetweenDates($startYear, $endYear)->count();

        $userStats = [];
        $userStats["total-count"] = $activeCount + $expiredCount + $checkPaymentCount + $disabledCount;
        $userStats["active-count"] = $activeCount;
        $userStats["expired-count"] = $expiredCount;
        $userStats["check-payment-count"] = $checkPaymentCount;
        $userStats["disabled-count"] = $disabledCount;
        $userStats["deleted-count"] = $deletedCount;
        $userStats["new-users-count"] = $newUsersCount;

        // TODO: enrich with monthly stats for line graph showing evolution in users

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
        $newUsersCurrMonthCount = \Api\Model\Contact::members()->whereDate('created_at', '>=', $startThisMonth)->count();
        $newUsersPrevMonthCount = \Api\Model\Contact::members()->createdBetweenDates($startLastMonth, $startThisMonth)->count();
        $userStats = [];
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
        $newUsersCurrMonthCountStroom = \Api\Model\Contact::stroom()->members()->whereDate('created_at', '>=', $startThisMonth)->count();
        $newUsersPrevMonthCountStroom = \Api\Model\Contact::stroom()->createdBetweenDates($startLastMonth, $startThisMonth)->count();
        $stroomStats = [];
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
     * @param $startPeriod
     * @param $endPeriod
     * @return array
     */
    private function getUserStatsV2($startPeriod, $endPeriod): array
    {
        $this->logger->info("Get users stats with params " . $startPeriod->format('Y-m-d') . ", " . $endPeriod->format('Y-m-d'));
        $activeCount = \Api\Model\Contact::active()->members()->count();
        $expiredCount = \Api\Model\Contact::where('state', \Api\Model\UserState::EXPIRED)->count();
        $checkPaymentCount = \Api\Model\Contact::where('state', \Api\Model\UserState::CHECK_PAYMENT)->count();
        $deletedCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DELETED)->count();
        $disabledCount = \Api\Model\Contact::where('state', \Api\Model\UserState::DISABLED)->count();
        $newUsersCount = \Api\Model\Contact::members()->createdBetweenDates($startPeriod, $endPeriod)->count();
        $userStats = [];
        $userStats["total-count"] = $activeCount + $expiredCount + $checkPaymentCount + $disabledCount;
        $userStats["active-count"] = $activeCount;
        $userStats["expired-count"] = $expiredCount;
        $userStats["check-payment-count"] = $checkPaymentCount;
        $userStats["disabled-count"] = $disabledCount;
        $userStats["deleted-count"] = $deletedCount;
        $userStats["new-users-count"] = $newUsersCount;

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
                ->createdBetweenDates($startDate, $endDate)->count();
            $newPendingCount = \Api\Model\Membership::pending()->withSubscriptionId($membershipType)
                ->createdBetweenDates($startDate, $endDate)->count();
        } else {
            $activeCount = \Api\Model\Membership::active()->count();
            $pendingCount = \Api\Model\Membership::pending()->count();
            $expiredCount = \Api\Model\Membership::expired()->count();
            $cancelledCount = \Api\Model\Membership::cancelled()->count();
            $newActiveCount = \Api\Model\Membership::active()
                ->createdBetweenDates($startDate, $endDate)->count();
            $newPendingCount = \Api\Model\Membership::pending()
                ->createdBetweenDates($startDate, $endDate)->count();
        }

        $membershipStats = [];
        $membershipStats["total-count"] = $activeCount + $pendingCount + $expiredCount + $cancelledCount;
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
                ->createdBetweenDates($startDate, $endDate)->count();
        } else {
            $totalCount = \Api\Model\Membership::where(["last_payment_mode" => $paymentMode])->count();
            $newCount = \Api\Model\Membership::where(["last_payment_mode" => $paymentMode])
                ->createdBetweenDates($startDate, $endDate)->count();
        }
        $membershipPaymenStats = [];
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

        $toolStats = [];
        $toolStats["total-count"] = isset($tools) ? count($tools) : 0;
        $newTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::NEW;
        });
        $toolStats["new-count"] = is_countable($newTools) ? count($newTools) : 0;
        $availableTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::READY;
        });
        $toolStats["available-count"] = is_countable($availableTools) ? count($availableTools) : 0;
        $deployedTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::IN_USE;
        });
        $toolStats["deployed-count"] = is_countable($deployedTools) ? count($deployedTools) : 0;
        $maintenanceTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::MAINTENANCE;
        });
        $toolStats["maintenance-count"] = is_countable($maintenanceTools) ? count($maintenanceTools) : 0;
        $archivedTools = $tools->filter(function ($value, $key) {
            return $value->state === ToolState::DISPOSED;
        });
        $toolStats["archived-count"] = is_countable($archivedTools) ? count($archivedTools) : 0;
        return $toolStats;
    }

    /**
     * @return mixed
     */
    private function getAccessoryStats(): array
    {
        $accessoryStats = [];
        $accessories = $this->inventory->getAccessories();

        $accessoryStats["total-count"] = isset($accessories) ? count($accessories) : 0;
        return $accessoryStats;
    }

    // /**
    //  * @param $year
    //  * @return array
    //  */
    // private function getYearlyLendingStats($year): array
    // {
    //     $this->logger->info("Get yearly lending stats with param " . $year);
    //     $startYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-01-01', $this->utc);
    //     $endYear = DateTimeImmutable::createFromFormat('Y-m-d', $year . '-12-31', $this->utc);
    //     $checkoutCount = Lending::inYear($startYear->format("Y"))->count();
    //     $checkinCount = Lending::returnedInYear($startYear->format("Y"))->count();

    //     $activityStats = array();
    //     $activityStats["total-count"] = Lending::count();
    //     $activityStats["active-count"] = Lending::active()->count();
    //     $activityStats["overdue-count"] = Lending::overdue()->count();
    //     $activityStats["checkout-count"] = $checkoutCount;
    //     $activityStats["checkin-count"] = $checkinCount;

    //     // TODO: enrich with monthly stats
    //     return $activityStats;
    // }

    // /**
    //  * @param $startLastMonth
    //  * @param $startThisMonth
    //  * @return array
    //  */
    // private function getLendingStats($startLastMonth, $startThisMonth): array
    // {
    //     $this->logger->info("Get lending stats with params " . $startLastMonth->format('Y-m-d') . ", " . $startThisMonth->format('Y-m-d'));
    //     $checkoutPrevMonthCount = Lending::inYear($startLastMonth->format("Y"))->inMonth($startLastMonth->format("m"))->count();
    //     $checkoutCurrMonthCount = Lending::inYear($startThisMonth->format("Y"))->inMonth($startThisMonth->format("m"))->count();
    //     $checkinPrevMonthCount = Lending::returnedInYear($startLastMonth->format("Y"))->returnedInMonth($startLastMonth->format("m"))->count();
    //     $checkinCurrMonthCount = Lending::returnedInYear($startThisMonth->format("Y"))->returnedInMonth($startThisMonth->format("m"))->count();
    //     $activityStats = array();
    //     $activityStats["total-count"] = Lending::count();
    //     $activityStats["active-count"] = Lending::active()->count();
    //     $activityStats["overdue-count"] = Lending::overdue()->count();
    //     $activityStats["checkout-prev-month-count"] = $checkoutPrevMonthCount;
    //     $activityStats["checkout-curr-month-count"] = $checkoutCurrMonthCount;
    //     $activityStats["checkin-prev-month-count"] = $checkinPrevMonthCount;
    //     $activityStats["checkin-curr-month-count"] = $checkinCurrMonthCount;

    //     // Stroom
    //     $stroomStats = array();
    //     $stroomStats["total-count"] = Lending::stroom()->count();
    //     $stroomStats["active-count"] = Lending::stroom()->active()->count();
    //     $stroomStats["overdue-count"] = Lending::stroom()->overdue()->count();

    //     $checkoutPrevMonthCountStroom = Lending::stroom()->inYear($startLastMonth->format("Y"))->inMonth($startLastMonth->format("m"))->count();
    //     $checkoutCurrMonthCountStroom = Lending::stroom()->inYear($startThisMonth->format("Y"))->inMonth($startThisMonth->format("m"))->count();
    //     $checkinPrevMonthCountStroom = Lending::stroom()->returnedInYear($startLastMonth->format("Y"))->returnedInMonth($startLastMonth->format("m"))->count();
    //     $checkinCurrMonthCountStroom = Lending::stroom()->returnedInYear($startThisMonth->format("Y"))->returnedInMonth($startThisMonth->format("m"))->count();

    //     $stroomStats["checkout-prev-month-count"] = $checkoutPrevMonthCountStroom;
    //     $stroomStats["checkout-curr-month-count"] = $checkoutCurrMonthCountStroom;
    //     $stroomStats["checkin-prev-month-count"] = $checkinPrevMonthCountStroom;
    //     $stroomStats["checkin-curr-month-count"] = $checkinCurrMonthCountStroom;
    //     $activityStats["stroom"] = $stroomStats;
    //     return $activityStats;
    // }
    // /**
    //  * @param $startLastMonth
    //  * @param $startThisMonth
    //  * @return array
    //  */
    // private function getLendingStatsV2($startPeriod, $periodType = "month"): array
    // {
    //     $this->logger->info("Get lending stats with params " . $startPeriod->format('Y-m-d') . ", " . $periodType);
    //     if ($periodType == "year") {
    //         $checkoutCount = Lending::inYear($startPeriod->format("Y"))->count();
    //         $checkinCount = Lending::returnedInYear($startPeriod->format("Y"))->count();
    //     } else if ($periodType == "month") {
    //         $checkoutCount = Lending::inYear($startPeriod->format("Y"))->inMonth($startPeriod->format("m"))->count();
    //         $checkinCount = Lending::returnedInYear($startPeriod->format("Y"))->returnedInMonth($startPeriod->format("m"))->count();
    //     } else {
    //         return array();
    //     }
    //     $activityStats = array();
    //     $activityStats["total-count"] = Lending::count();
    //     $activityStats["active-count"] = Lending::active()->count();
    //     $activityStats["overdue-count"] = Lending::overdue()->count();
    //     $activityStats["checkout-count"] = $checkoutCount;
    //     $activityStats["checkin-count"] = $checkinCount;

    //     return $activityStats;
    // }
}