<?php

namespace Api\Loan;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Mail\MailManager;
use Api\Model\Lending;
use Api\Model\Loan;
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
        // TODO: also update LE payments from API kb_payments
        // First sync checkouts, then updates and finally checkins to avoid rolling back checkin modifications due to an earlier checkout 
        // Ideally, all action logs are replayed chronologically, syncing all types of activity at once
        echo "Syncing checkout activity\n";
        $offset = 0;
        $limit = 100;
        $checkoutBody = $this->inventory->getActivityCheckout($offset, $limit);
        $checkoutActionsCount = $checkoutBody->total;
        $checkoutActions = $checkoutBody->rows;
        echo "checkout action count: " . $checkoutActionsCount . "\n";
        echo "checkout actions: " . \json_encode($checkoutActions) . "\n";
        if (is_array($checkoutActions)) {
            foreach($checkoutActions as $item) {
                echo "Syncing checkout action with id " . $item->id . "\n";
                // TODO: check if loan or lending should be used. As a start, use lending and update loan through triggers
                // TODO: add last_sync_timestamp to loan and loan_row, 
                // TODO: adjust query to inventory to exclued activity older than last sync timestamp
                // TODO: keep last sync timestamp (in a table kb_sync? in a file?)
                // find loan based on inventory item id and created_at datetime
                $itemAction = $item->action_type; // checkout, update or 'checkin from'
                if ($itemAction !== "checkout" && $itemAction !== "update" && $itemAction !== "checkin from") {
                    continue;
                }
                $inventoryUserId = isset($item->target) ? $item->target->id : null;
                $contact = Contact::where(['user_ext_id' => $inventoryUserId])->first();
                $inventoryItemId = isset($item->item) ? $item->item->id : null; // not available on loan, only on loan row!
                $createdAt = isset($item->created_at) ? $item->created_at->datetime : null;
                $itemActionDatetime = isset($item->action_date) ? $item->action_date->datetime : null;
                //$lending = Lending::where(['start_date' => $createdAt, 'tool_id' => $inventoryItemId, 'user_id' => $contactId])->first();
                $lending = Lending::where(['tool_id' => $inventoryItemId, 'user_id' => $contactId])->first();
                //$loanRow = LoanRow::where(['checked_out_at' => $createdAt, 'inventory_item_id' => $inventoryItemId])->first();
                ////$loan = Loan::where(['created_at' => $createdAt, 'contact_id' => $contactId])->first();
                //$existingItem = isset($loanRow) ? Loan::find($loanRow->loan_id) : null;
                $existingItem = $lending;
                if ($existingItem === null) {
                    // save will create new item
                    echo "creating new loan for checkout action id " . $item->id . "\n";
                    $lending = new Lending();
                    $lending->user_id = isset($contact) ? $contact->id : null;
                    $lending->tool_id = $inventoryItemId;
                    $lending->start_date = $createdAt;
                    //$lending->due_date = $createdAt + 7 days?? Does not seem included in api response, but stored in inventory.action_logs.expected_checkin
                    $lending->last_sync_date = $syncTime;
                    $lending->save();
                } else {
                    // TODO: check last sync timestamp
                    // FIXME: should use special function to compare dates?
                    if (isset($itemActionDatetime) 
                        && $existingItem->last_sync_date < $itemActionDatetime) {
                        // only update existing item if not synced since action timestamp
                        // update item values if needed
                        // update last_sync_timestamp to skip it next time
                        echo "updating loan item " . $item->id . "\n";
                        //$this->updateExistingItem($item, $existingItem);
        
                        $existingItem->user_id = $contactId;
                        $existingItem->tool_id = $inventoryItemId;
                        $existingItem->start_date = $createdAt;
                        $existingItem->last_sync_date = $syncTime;
                        $existingItem->save();
                    }

                }
            }
        }
        echo "Syncing update activity (doing nothing yet)\n";
        echo "Syncing checkin activity (doing nothing yet)\n";

        // Delete all other items
        echo "Deleting other items (doing nothing yet)\n";
        //Loan::outOfSync($syncTime)->delete();
    }

}
