<?php

namespace Api\Loan;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Mail\MailManager;
use Api\Model\Lending;
use Api\Model\Loan;
use Api\Model\Contact;
use Api\User\UserManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
//use Api\ModelMapper\UserMapper;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Log\LoggerInterface;

/**
 * Class LoanManager
 * Keeps Loan model in sync with inventory
 * @package Api\Loan
 */
class LoanManager
{
    public static function instance(LoggerInterface $logger, MailManager $mailManager) {
        return new LoanManager(SnipeitInventory::instance($logger), $logger, $mailManager);
    }
    private Inventory $inventory;
    private LoggerInterface $logger;
    private MailManager $mailManager;
    private $lastSyncAttempt;
    private $lastSyncedLoans;

    /**
     * LoanManager constructor.
     */
    public function __construct(Inventory $inventory, LoggerInterface $logger, MailManager $mailManager = null)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
        $this->mailManager = $mailManager;
        $this->lastSyncAttempt = null;
        $this->lastSyncedLoans = array();
    }

    public function sync() {
        $syncTime = new \DateTime();
        // Get last synced action id from kb_sync
        $syncData = Capsule::table('kb_sync')->first();
        $lastActionId = isset($syncData->last_inventory_action_id) ? $syncData->last_inventory_action_id : 0;
        $lastActionTimestamp = isset($syncData->last_inventory_action_timestamp) 
          ? Carbon::createFromFormat('Y-m-d H:i:s',  $syncData->last_inventory_action_timestamp) : null;
        
        // TODO: also update LE payments from API kb_payments
        // All action logs are replayed chronologically, syncing all types of activity at once
        echo "Syncing loans from inventory activity starting from $syncData->last_inventory_action_timestamp\n";
        //->format('Y-m-d H:i:s')
        if (isset($lastActionTimestamp)) {
          echo "Deleting all lendings after sync date " . $lastActionTimestamp->toDateTimeString() . "\n";
          Lending::whereDate('last_sync_date', '>', $lastActionTimestamp->toDateTimeString())->delete();
        } else {
            echo "Deleting all lendings\n";
            Lending::query()->delete();  
        }

        $limit = 100;
        // dummy call to get actions count
        try {
            $activityBody = $this->inventory->getActivity(0, 2);
        } catch (\Api\Exception\NotFoundException $ex) {
          echo "No activity (or insufficient rights to access it)\n";
          return;
        }
        $actionsCount = $activityBody->total;
        $offset = $actionsCount > $limit ? $actionsCount - $limit : 0;
        while ($offset >= 0) {
            $activityBody = $this->inventory->getActivity($offset, $limit);
            $newActionsCount = $activityBody->total;
            // TO TEST: make sure no actions are skipped when extra actions are added during sync
            if ($newActionsCount > $actionsCount) {
                // new activity entries have been added since previous query
                $extraActions = $newActionsCount - $actionsCount;
                $offset += $extraActions;
                $actionsCount = $newActionsCount;
                if ($extraActions > 0) {
                    // we need to repeat the query with a different offset to avoid missing entries
                    continue;
                }
            }

            $actions = $activityBody->rows;
            if (is_array($actions)) {
                // Action logs are retrieved in descending chronological order -> order needs to be reversed
                $actions = array_reverse($actions);
                foreach($actions as $item) {
                    
                    if ($this->processActionLogItem($item, $lastActionTimestamp, $lastActionId)) {
                        $lastActionId = $item->id;
                        $createdAtString = isset($item->created_at) ? $item->created_at->datetime : null;
                        $lastActionTimestamp = \DateTime::createFromFormat("Y-m-d H:i:s", $createdAtString);
                        $affected = Capsule::table('kb_sync')
                        ->update([
                            'last_inventory_action_id' => $lastActionId,
                            'last_inventory_action_timestamp' => $lastActionTimestamp
                        ]);
                    }
                }
            }
            $offset = $offset - $limit;
            usleep(500 * 1000);
        }

    }

    /**
     * @return true when log item is successfully processed
     */
    private function processActionLogItem($item, ?\DateTime $lastActionTimestamp , $lastActionId) : bool {

        $createdAtString = isset($item->created_at) ? $item->created_at->datetime : null;
        $createdAt = Carbon::createFromFormat("Y-m-d H:i:s", $createdAtString);
        // Filter already synced actions
        // Note multiple actions with same timestamp are possible (e.g. in case of bulk update of expected checkin), 
        // thus processing same action twice should not lead to errors
        // how to handle multiple log items with same creation timestamp? 
        if (isset($createdAt) && isset($lastActionTimestamp) 
            && ($createdAt < $lastActionTimestamp || $item->id == $lastActionId)) {
            // already processed
            $carbonLastActionTime = Carbon::instance($lastActionTimestamp);
            echo "skipping action $item->id : already processed (last action on " . $carbonLastActionTime->toDateTimeString() 
              . ", id = $lastActionId)\n";
            return false;
        }

        $itemAction = $item->action_type; // checkout, update or 'checkin from'
        if ($itemAction !== "checkout" && $itemAction !== "update" && $itemAction !== "checkin from") {
            // nothing to do, consider action item as successfully processed
            return true;
        }
        echo "Syncing $itemAction action with id $item->id\n";
        //echo \json_encode($item) . "\n";
        // TODO: check if loan or lending should be used. As a start, use lending and update loan through triggers

        $inventoryUserId = isset($item->target) ? $item->target->id : null;
        $contact = Contact::where(['user_ext_id' => $inventoryUserId])->first();
        if (isset($item->target) && !isset($contact)) {
            echo "$itemAction action ($item->id) for unkown user (user inventory id " . $inventoryUserId . " deleted?) -> ignored\n";
            return false;
        }
        $inventoryItemId = isset($item->item) ? $item->item->id : null; // available in lending, but not on loan (only on loan row)!
        // FIXME: itemActionDateTime not yet supported in our version of snipe it?
        //$itemActionDatetime = isset($item->action_date) ? $item->action_date->datetime : null;
        $itemActionDatetime = $createdAt;
        if (!isset($itemActionDatetime) || !isset($inventoryItemId)) {
            echo "invalid $itemAction action, missing item action date (or created_at) and/or inventory item id -> ignored\n";
            return false;
        }

        // At this point, we have a valid action with action datetime, target user (checkout and checkin only) and inventory item id

        // find loan (=lending)
        // Note: Same user can lend a tool multiple times
        // for checkout -> include start_date
        // for checkin & update -> only select from active lendings
        if ($itemAction === "checkout") {
            $lending = Lending::where(['start_date' => $createdAt, 'tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        } else if ($itemAction === "checkin from") {
            $lending = Lending::active()->where(['tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        } else { // target id (and thus contact id) not known on update
            $lending = Lending::active()->where(['tool_id' => $inventoryItemId])->first();
        }
        //$loanRow = LoanRow::where(['checked_out_at' => $createdAt, 'inventory_item_id' => $inventoryItemId])->first();
        ////$loan = Loan::where(['created_at' => $createdAt, 'contact_id' => $contact->id])->first();
        //$existingItem = isset($loanRow) ? Loan::find($loanRow->loan_id) : null;

        // For a checkout: if record exists, it is already synced
        if ($lending === null) {
            if ($itemAction === "checkout") {
                // Check if no active lending exists
                if (Lending::active()->where(['tool_id' => $inventoryItemId])->exists()) {
                    // FIXME: this check needs all action logs to be processed in chronological order (regardless of action type)!
                    echo "Cannot create lending for checkout: an active lending already exists for tool $inventoryItemId\n";
                    return false;
                }
                echo "creating new loan for checkout action id $item->id (checkout date: $createdAtString)\n";
                $lending = new Lending();
                $lending->user_id = isset($contact) ? $contact->id : null;
                $lending->tool_id = $inventoryItemId;
                $lending->start_date = $itemActionDatetime;
                //$lending->due_date = $createdAt + 7 days?? Does not seem included in api response, but stored in inventory.asset.expected_checkin
                $dueDate = clone $itemActionDatetime;
                $dueDate->add(new \DateInterval('P7D'));
                $lending->due_date = $dueDate;
                $lending->last_sync_date = $createdAt;
                $lending->save();
                return true;
            } else {
                echo "lending not found or already closed\n";
                return false;
            }
        } else {
            // Note: no need for a classic last sync timestamp check here, as action_log is inserted, but never updated afterwards
            //       but we do need to keep track of last action_log processed, and last_sync_timestamp is used here to keep last action_log created_at_ timestamp
            // For update and checkin: compare update timestamp with item action datetime. Only apply action log, if action datetime is more recent than last update
            // FIXME: should use special function to compare dates?
            if (isset($itemActionDatetime) 
                && $lending->last_sync_date < $itemActionDatetime
                && ($itemAction === "update" || $itemAction === "checkin from") ) {
                // update item values if needed

                if ($itemAction === "update") {
                    // expected checkin date is received through log_meta as json e.g. {"expected_checkin":{"old":"2021-10-24","new":"2021-10-27 00:00:00"}}
                    $logMeta = $item->log_meta;
                    if (isset($logMeta) && isset($logMeta->expected_checkin) && isset($logMeta->expected_checkin->new)) {
                        echo "updating due date of lending $lending->lending_id from " . $logMeta->expected_checkin->old . 
                        " to " . $logMeta->expected_checkin->new . "\n";
                        $expectedCheckin = \DateTime::createFromFormat("Y-m-d H:i:s", $logMeta->expected_checkin->new);
                        $lending->due_date = $expectedCheckin;
                        $lending->last_sync_date = $createdAt;
                        $lending->save();
                        return true;
                    } else {
                        echo "Not an update of expected checkin -> update action $item->id ignored\n";
                    }
                }
                if ($itemAction === "checkin from") {
                    echo "processing checkin for lending $lending->lending_id from action id $item->id (checkin date: $createdAtString)\n";
                    // FIXME: where to get actual returned date?
                    // actual returned date doesn't seem to be stored in snipe it repo (bug?) Only way to get it, is to process a notification at checkin event
                    $lending->returned_date = $createdAt;
                    $lending->last_sync_date = $createdAt;
                    $lending->save();
                    return true;
                }
            }

        }
        return false;
    }

}
