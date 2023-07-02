<?php

namespace Api\Loan;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Mail\MailManager;
use Api\Model\Lending;
use Api\Model\Loan;
use Api\Model\Contact;
//use Api\ModelMapper\UserMapper;

/**
 * Class LoanManager
 * Keeps Loan model in sync with inventory
 * @package Api\Loan
 */
class LoanManager
{
    public static function instance($logger) {
        return new UserManager(SnipeitInventory::instance(), $logger, new MailManager(null, null, $logger));
    }
    private $inventory;
    private $logger;
    private $mailManager;
    private $lastSyncAttempt;
    private $lastSyncedLoans;

    /**
     * LoanManager constructor.
     */
    public function __construct(Inventory $inventory, $logger, MailManager $mailManager = null)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
        $this->mailManager = $mailManager;
        $this->lastSyncAttempt = null;
        $this->lastSyncedLoans = array();
    }

    public function sync() {
        $syncTime = new \DateTime();
        // TODO: create a table to store last synced action log (e.g. kb_context)

        // TODO: also update LE payments from API kb_payments
        // First sync checkouts, then updates and finally checkins to avoid rolling back checkin modifications due to an earlier checkout 
        // Ideally, all action logs are replayed chronologically, syncing all types of activity at once
        echo "Syncing activity\n";
        $offset = 0;
        $limit = 100;
        
        //$checkoutBody = $this->inventory->getActivityCheckout($offset, $limit);
        $activityBody = $this->inventory->getActivity($offset, $limit);
        $checkoutActionsCount = $activityBody->total;
        $actions = $activityBody->rows;
        if (is_array($actions)) {
            // Action logs are retrieved in descending chronological order -> order needs to be reversed
            $actions = array_reverse($actions);
            foreach($actions as $item) {
                $this->processActionLogItem($item);
            }
        }
/*         echo "Syncing update activity\n";
        $offset = 0;
        $limit = 100;
        $updateBody = $this->inventory->getActivityUpdate($offset, $limit);
        $updateActionsCount = $updateBody->total;
        $updateActions = $updateBody->rows;
        echo "update action count: " . $updateActionsCount . "\n";
//        echo "update actions: " . \json_encode($updateActions) . "\n";
        if (is_array($updateActions)) {
            // Action logs are retrieved in descending chronological order -> order needs to be reversed
            $updateActions = array_reverse($updateActions);
            foreach($updateActions as $item) {
                echo "Syncing update action with id " . $item->id . "\n";
                $this->processActionLogItem($item);
            }
        }

        echo "Syncing checkin activity\n";
        $offset = 0;
        $limit = 100;
        $checkinBody = $this->inventory->getActivityCheckin($offset, $limit);
        $checkinActionsCount = $checkinBody->total;
        $checkinActions = $checkinBody->rows;
        echo "checkin action count: " . $checkinActionsCount . "\n";
        //echo "checkin actions: " . \json_encode($checkinActions) . "\n";
        if (is_array($checkinActions)) {
            // Action logs are retrieved in descending chronological order -> order needs to be reversed
            $checkinActions = array_reverse($checkinActions);
            foreach($checkinActions as $item) {
                echo "Syncing checkin action with id " . $item->id . "\n";
                $this->processActionLogItem($item);
            }
        } */

        // Delete all other items
        echo "Deleting other items (doing nothing yet)\n";
        //Loan::outOfSync($syncTime)->delete();
    }

    private function processActionLogItem($item) {

        $itemAction = $item->action_type; // checkout, update or 'checkin from'
        if ($itemAction !== "checkout" && $itemAction !== "update" && $itemAction !== "checkin from") {
            return;
        }
        echo "Syncing $itemAction action with id $item->id\n";
        echo \json_encode($item) . "\n";
        // TODO: check if loan or lending should be used. As a start, use lending and update loan through triggers
        // TODO: add last_sync_timestamp to loan and loan_row, 
        // TODO: adjust query to inventory to exclued activity older than last sync timestamp
        // TODO: keep last sync timestamp (in a table kb_sync? in a file?)
        // find loan based on inventory item id and created_at datetime

        $inventoryUserId = isset($item->target) ? $item->target->id : null;
        $contact = Contact::where(['user_ext_id' => $inventoryUserId])->first();
        if (isset($item->target) && !isset($contact)) {
            echo "$itemAction action for unkown user with user inventory id " . $inventoryUserId . " (user deleted?) -> ignored\n";
            echo \json_encode($item) . "\n";
            return;
        }
        $inventoryItemId = isset($item->item) ? $item->item->id : null; // available in lending, but not on loan (only on loan row)!
        $createdAtString = isset($item->created_at) ? $item->created_at->datetime : null;
        $createdAt = \DateTime::createFromFormat("Y-m-d H:i:s", $createdAtString);
        // FIXME: itemActionDateTime not yet supported in our version of snipe it?
        $itemActionDatetime = isset($item->action_date) ? $item->action_date->datetime : null;
        $logMeta = $item->log_meta;
        $itemActionDatetime = $createdAt;
        if (!isset($itemActionDatetime) || !isset($inventoryItemId)) {
            echo "invalid $itemAction action, missing item action date (or created_at) and/or inventory item id -> ignored\n";
            echo \json_encode($item) . "\n";
            return;
        }
        // Same user can lend a tool multiple times
        // for checkout -> include start_date
        // for checkin & update -> only select from active lendings
        if ($itemAction === "checkout") {
            // Check if no active lending exists
            if (Lending::active()->where(['tool_id' => $inventoryItemId])->exists()) {
                // FIXME: this check needs all action logs to be processed in chronological order (regardless of action type)!
                echo "Cannot create lending for checkout: an active lending already exists\n";
            }
            $lending = Lending::where(['start_date' => $createdAt, 'tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        } else if ($itemAction === "checkin from") {
            $lending = Lending::active()->where(['tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        } else { // target id (and thus contact id) not known on update
            $lending = Lending::active()->where(['tool_id' => $inventoryItemId])->first();
        }
        //$lending = Lending::where(['tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        //$loanRow = LoanRow::where(['checked_out_at' => $createdAt, 'inventory_item_id' => $inventoryItemId])->first();
        ////$loan = Loan::where(['created_at' => $createdAt, 'contact_id' => $contact->id])->first();
        //$existingItem = isset($loanRow) ? Loan::find($loanRow->loan_id) : null;
        // For a checkout: if record exists, it is already synced
        if ($lending === null) {
            if ($itemAction === "checkout") {
                echo "creating new loan for checkout action id $item->id\n";
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
            } else {
                echo "lending not found or already closed\n";
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
                    if (isset($logMeta) && isset($logMeta->expected_checkin) && isset($logMeta->expected_checkin->new)) {
                        echo "updating due date of lending $lending->lending_id to " . $logMeta->expected_checkin->new . "\n";
                        $expectedCheckin = \DateTime::createFromFormat("Y-m-d H:i:s", $logMeta->expected_checkin->new);
                        $lending->due_date = $expectedCheckin;
                        $lending->last_sync_date = $createdAt;
                        $lending->save();
                    } else {
                        echo "Not an update of expected checkin -> update action $item->id ignored\n";
                    }
                }
                if ($itemAction === "checkin from") {
                    // FIXME: where to get actual returned date?
                    // actual returned date doesn't seem to be stored in snipe it repo (bug?) Only way to get it, is to process a notification at checkin event
                    $lending->returned_date = $createdAt;
                    $lending->last_sync_date = $createdAt;
                    $lending->save();
                }
            }

        }
    }

}
