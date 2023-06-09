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
                $contactId = isset($item->target) ? $item->target->id : null;
                $inventoryItemId = isset($item->item) ? $item->item->id : null; // not available on loan, only on loan row!
                $createdAt = isset($item->created_at) ? $item->created_at->datetime : null;
                //$lending = Lending::where(['start_date' => $createdAt, 'tool_id' => $inventoryItemId, 'user_id' => $contactId])->first();
                $lending = Lending::where(['tool_id' => $inventoryItemId, 'user_id' => $contactId])->first();
                //$loanRow = LoanRow::where(['checked_out_at' => $createdAt, 'inventory_item_id' => $inventoryItemId])->first();
                ////$loan = Loan::where(['created_at' => $createdAt, 'contact_id' => $contactId])->first();
                //$existingItem = isset($loanRow) ? Loan::find($loanRow->loan_id) : null;
                $existingItem = $lending;
                if ($existingItem === null) {
                    // save will create new item
                    echo "creating new loan for checkout action id " . $item->id . " (doing nothing yet)\n";
                    $lending = new Lending();
                    $lending->user_id = $contactId;
                    $lending->tool_id = $inventoryItemId;
                    $lending->start_date = $createdAt;
                    //$lending->due_date = $createdAt + 7 days??;
                    $lending->last_sync_date = $syncTime;
                    $lending->save();
                } else {
                    // TODO: check last sync timestamp

                    // update item values if needed
                    // update last_sync_timestamp to skip it next time
                    echo "updating loan item " . $item->id . " (doing nothing yet)\n";
                    //$this->updateExistingItem($item, $existingItem);
    
                    $existingItem->user_id = $contactId;
                    $existingItem->tool_id = $inventoryItemId;
                    $existingItem->start_date = $createdAt;
                    $existingItem->last_sync_date = $syncTime;
                    $existingItem->save();
                }
            }
        }
        echo "Syncing checkin activity (doing nothing yet)\n";

        // Delete all other items
        echo "Deleting other items (doing nothing yet)\n";
        //Loan::outOfSync($syncTime)->delete();
    }

}
