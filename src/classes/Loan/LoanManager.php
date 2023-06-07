<?php

namespace Api\Loan;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Mail\MailManager;
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
        $checkoutActions = $checkoutActions->rows;
        echo "checkout action count: " . $checkoutActionsCount;
        echo "checkout actions: " . \json_encode($checkoutActions);
        if (is_array($checkoutActions)) {
            foreach($checkoutActions as $item) {
                echo "Syncing checkout action with id " . $item->id . "\n";
                $existingItem = Loan::find($item->id);
                if ($existingItem === null) {
                    // save will create new item
                    echo "creating new loan for checkout action id " . $item->id . "\n";
                    $item->last_sync_date = $syncTime;
                    $item->save();
                } else {
                    // update item values
                    echo "updating loan item " . $item->id . "\n";
                    $this->updateExistingItem($item, $existingItem);
    
                    $existingItem->last_sync_date = $syncTime;
                    $existingItem->save();
                }
    
            }
    
        }
        echo "Syncing checkin activity\n";

        // Delete all other items
        echo "Deleting other items\n";
        //Loan::outOfSync($syncTime)->delete();
    }

}
